<?php

namespace Drupal\ek_documents\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for ek_documents API operations.
 */
class DocumentsService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $extdb;

  /**
   * Constructs a DocumentsService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, $logger_factory, FileSystemInterface $file_system) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('ek_documents');
    $this->fileSystem = $file_system;
    $this->extdb = Database::getConnection('external_db', 'external_db');
  }

  /**
   * Get documents owned by the current user.
   *
   * @param array $options
   *   Optional filters:
   *   - folder: (string) filter by folder/tag
   *   - search: (string) filename LIKE search
   *   - from: (string) start date Y-m-d
   *   - to: (string) end date Y-m-d
   *
   * @return array
   *   Flat list of document records.
   */
  public function getMyDocuments(array $options = []): array {
    $uid = \Drupal::currentUser()->id();
    $documents = [];

    $query = $this->extdb->select('ek_documents', 'd');
    $query->fields('d');
    $query->condition('uid', $uid);
    $query->orderBy('folder');
    $query->orderBy('filename');

    if (!empty($options['folder'])) {
      $query->condition('folder', $options['folder']);
    }

    if (!empty($options['search'])) {
      $query->condition('filename', '%' . $options['search'] . '%', 'LIKE');
    }

    if (!empty($options['from'])) {
      $query->condition('date', strtotime($options['from']), '>=');
    }

    if (!empty($options['to'])) {
      $query->condition('date', strtotime($options['to']), '<=');
    }

    $list = $query->execute();
    while ($l = $list->fetchObject()) {
      $size_kb = 0;
      if (is_numeric($l->size)) {
        $size_kb = round($l->size / 1000, 2);
      }
      $documents[] = [
        'id'         => (int) $l->id,
        'uid'        => (int) $l->uid,
        'fid'        => $l->fid,
        'filename'   => $l->filename,
        'uri'        => $l->uri,
        'folder'     => $l->folder,
        'comment'    => $l->comment,
        'date'       => date('Y-m-d', $l->date),
        'timestamp'  => (int) $l->date,
        'size_kb'    => $size_kb,
        'share'      => $l->share,
        'share_uid'  => $l->share_uid,
        'share_gid'  => $l->share_gid,
        'expire'     => $l->expire ? date('Y-m-d', $l->expire) : NULL,
      ];
    }

    return $documents;
  }

  /**
   * Delete a document owned by the current user.
   *
   * @param int $document_id
   *   The document ID from ek_documents.
   *
   * @return array
   *   Associative array with 'success' bool and 'message' or 'error' string.
   */
  public function deleteDocument(int $document_id): array {
    $uid = \Drupal::currentUser()->id();

    // Load the document record.
    $query = $this->extdb->select('ek_documents', 'd');
    $query->fields('d', ['id', 'uid', 'uri', 'filename']);
    $query->condition('id', $document_id);
    $doc = $query->execute()->fetchObject();

    if (!$doc) {
      return ['success' => FALSE, 'error' => 'Document not found.'];
    }

    // Validate ownership.
    $is_owner = ((int) $doc->uid === (int) $uid);
    $is_common_manager = ($doc->uid == 0 && \Drupal::currentUser()->hasPermission('manage_common_documents'));

    if (!$is_owner && !$is_common_manager) {
      return ['success' => FALSE, 'error' => 'Access denied. You are not the owner of this document.'];
    }

    // Delete the database record.
    $deleted = $this->extdb->delete('ek_documents')
      ->condition('id', $document_id)
      ->execute();

    if (!$deleted) {
      return ['success' => FALSE, 'error' => 'Failed to delete document record.'];
    }

    // Invalidate cache tags.
    \Drupal\Core\Cache\Cache::invalidateTags(['my_documents', 'common_documents', 'shared_documents', 'new_documents_shared']);

    // Remove the physical file.
    $uri = $doc->uri;
    if ($uri) {
      // Check if file is managed by Drupal file_managed.
      $fid = Database::getConnection()->select('file_managed', 'f')
        ->fields('f', ['fid'])
        ->condition('uri', $uri)
        ->execute()
        ->fetchField();

      if ($fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          $file->delete();
        }
      }
      else {
        // Unmanaged file: delete directly.
        if (file_exists($uri)) {
          @unlink($uri);
        }
      }
    }

    $this->logger->notice('user @uid deleted document id @id (@filename)', [
      '@uid' => $uid,
      '@id'  => $document_id,
      '@filename' => $doc->filename,
    ]);

    return ['success' => TRUE, 'message' => 'Document deleted successfully.'];
  }

  /**
   * Edit sharing settings for a document.
   *
   * @param int $document_id
   *   The document ID.
   * @param array $data
   *   - share_uid: array of user IDs to share with; empty array = unshare.
   *   - expire: optional expiration date string Y-m-d.
   *
   * @return array
   *   Associative array with 'success' bool and 'message' or 'error' string.
   */
  public function editShare(int $document_id, array $data): array {
    $uid = \Drupal::currentUser()->id();

    // Load the document record.
    $query = $this->extdb->select('ek_documents', 'd');
    $query->fields('d', ['id', 'uid', 'filename', 'uri']);
    $query->condition('id', $document_id);
    $doc = $query->execute()->fetchObject();

    if (!$doc) {
      return ['success' => FALSE, 'error' => 'Document not found.'];
    }

    // Validate ownership.
    $is_owner = ((int) $doc->uid === (int) $uid);
    $is_common_manager = ($doc->uid == 0 && \Drupal::currentUser()->hasPermission('manage_common_documents'));

    if (!$is_owner && !$is_common_manager) {
      return ['success' => FALSE, 'error' => 'Access denied. You are not the owner of this document.'];
    }

    // Parse share_uid: expect an array of numeric user IDs.
    $share_uids = isset($data['share_uid']) && is_array($data['share_uid'])
      ? array_filter($data['share_uid'], 'is_numeric')
      : [];

    if (empty($share_uids)) {
      $share = 0;
      $share_uid_str = 0;
    }
    else {
      $share = 1;
      // Stored as ,uid1,uid2, (comma-wrapped list matching ShareForm convention).
      $share_uid_str = ',' . implode(',', $share_uids) . ',';
    }

    // Parse expiration date.
    $expire = 0;
    if (!empty($data['expire'])) {
      $ts = strtotime($data['expire']);
      if ($ts !== FALSE) {
        $expire = $ts;
      }
    }

    $fields = [
      'share'     => $share,
      'share_uid' => $share_uid_str,
      'expire'    => $expire,
    ];

    $updated = $this->extdb->update('ek_documents')
      ->fields($fields)
      ->condition('id', $document_id)
      ->execute();

    if ($updated === FALSE) {
      return ['success' => FALSE, 'error' => 'Failed to update sharing settings.'];
    }

    // Record in user.data for each shared user (new document alert).
    if (!empty($share_uids)) {
      $userData = \Drupal::service('user.data');
      foreach (\Drupal\user\Entity\User::loadMultiple($share_uids) as $account) {
        if ($account) {
          $userData->set('ek_documents', $account->id(), $document_id, 'shared');
          
        }
      }

      ek_documents_message('share', $share_uids,"File shared in documents",$doc->uri,$doc->filename,NULL,2);
    }

    \Drupal\Core\Cache\Cache::invalidateTags(['shared_documents', 'new_documents_shared']);

    $this->logger->notice('user @uid updated share for document id @id, shared with: @uids', [
      '@uid'  => $uid,
      '@id'   => $document_id,
      '@uids' => implode(',', $share_uids),
    ]);

    return [
      'success' => TRUE,
      'message' => $share ? 'Document shared successfully.' : 'Document sharing removed.',
    ];
  }

  /**
   * Retrieve file metadata for download, with access check.
   *
   * @param int $document_id
   *   The document ID.
   *
   * @return array
   *   On success: array with 'success' TRUE and 'file' sub-array (uri, filename).
   *   On failure: array with 'success' FALSE and 'error' string.
   */
  public function downloadDocument(int $document_id): array {
    $uid = \Drupal::currentUser()->id();

    $query = $this->extdb->select('ek_documents', 'd');
    $query->fields('d', ['id', 'uid', 'uri', 'filename', 'share', 'share_uid', 'expire']);
    $query->condition('id', $document_id);
    $doc = $query->execute()->fetchObject();

    if (!$doc) {
      return ['success' => FALSE, 'error' => 'Document not found.'];
    }

    // Access checks:
    // 1. Owner.
    $is_owner = ((int) $doc->uid === (int) $uid);
    // 2. Common documents (uid=0, visible to all authenticated).
    $is_common = ($doc->uid == 0);
    // 3. Shared with all (share=2).
    $is_public = ($doc->share == 2);
    // 4. Shared with this specific user.
    $share_uid_list = ',' . $doc->share_uid . ',';
    $is_shared_with_user = ($doc->share == 1 && strpos($share_uid_list, ',' . $uid . ',') !== FALSE);

    // 5. Check expiration for shared documents.
    if ($is_shared_with_user || $is_public) {
      if (!empty($doc->expire) && $doc->expire != 0 && $doc->expire < time()) {
        return ['success' => FALSE, 'error' => 'Shared access has expired.'];
      }
    }

    if (!$is_owner && !$is_common && !$is_public && !$is_shared_with_user) {
      return ['success' => FALSE, 'error' => 'Access denied to this document.'];
    }

    // Verify file is accessible via stream wrapper (works for local and remote/S3).
    $handle = @fopen($doc->uri, 'r');
    if (!$handle) {
      return ['success' => FALSE, 'error' => 'File not found or not accessible.'];
    }
    fclose($handle);

    $name = \Drupal::currentUser()->getAccountName();
    $log = t("User @u has downloaded private document @d (file id @i)", ['@u' => $name, '@d' => $doc->filename, '@i' => $document_id]);
    $this->logger->notice($log);

    return [
      'success' => TRUE,
      'file' => [
        'uri'      => $doc->uri,
        'filename' => $doc->filename,
      ],
    ];
  }

  /**
   * Upload a file to the current user's document repository.
   *
   * @param array $file_data
   *   Must contain 'name' (filename) and 'tmp_name' (real path). Optional 'size'.
   * @param string $folder
   *   Tag or folder name for classification.
   * @param string|null $comment
   *   Optional comment.
   *
   * @return array
   *   On success: array with 'success' TRUE, 'document_id', 'filename', 'message'.
   *   On failure: array with 'success' FALSE and 'errors' array.
   */
  public function uploadDocument(array $file_data, string $folder = '', ?string $comment = NULL): array {
    $uid = \Drupal::currentUser()->id();
    $errors = [];

    if (empty($file_data['name'])) {
      $errors[] = 'Missing filename.';
    }
    if (empty($file_data['tmp_name']) || !file_exists($file_data['tmp_name'])) {
      $errors[] = 'Uploaded file not found.';
    }

    if (!empty($errors)) {
      return ['success' => FALSE, 'errors' => $errors];
    }

    $filename = basename($file_data['name']);

    // Prepare target directory.
    $dir = 'private://documents/users/' . $uid;
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Copy temp file to target directory.
    try {
      $destination = $dir . '/' . $filename;
      $uri = $this->fileSystem->copy($file_data['tmp_name'], $destination, FileSystemInterface::EXISTS_RENAME);
    }
    catch (\Exception $e) {
      $this->logger->error('File copy failed for user @uid: @msg', [
        '@uid' => $uid,
        '@msg' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'errors' => ['File could not be saved: ' . $e->getMessage()]];
    }

    // Use the actual filename after potential rename.
    $saved_filename = basename($uri);

    $fields = [
      'uid'       => $uid,
      'fid'       => NULL,
      'type'      => 0,
      'filename'  => $saved_filename,
      'uri'       => $uri,
      'folder'    => $folder,
      'comment'   => $comment ?? '',
      'date'      => time(),
      'size'      => isset($file_data['size']) ? $file_data['size'] : filesize($uri),
      'share'     => 0,
      'share_uid' => 0,
      'share_gid' => 0,
      'expire'    => 0,
    ];

    $document_id = $this->extdb->insert('ek_documents')
      ->fields($fields)
      ->execute();

    \Drupal\Core\Cache\Cache::invalidateTags(['my_documents']);

    $this->logger->notice('user @uid uploaded document @filename (id @id)', [
      '@uid'      => $uid,
      '@filename' => $saved_filename,
      '@id'       => $document_id,
    ]);

    return [
      'success'     => TRUE,
      'document_id' => (int) $document_id,
      'filename'    => $saved_filename,
      'message'     => 'File uploaded successfully.',
    ];
  }

}
