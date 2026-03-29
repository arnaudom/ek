<?php

namespace Drupal\ek_admin\Service;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Utility\Xss;

/**
 * Service for task API operations.
 */
class TaskApiService {

  use StringTranslationTrait;

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a TaskApiService object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->database = Database::getConnection('external_db', 'external_db');
    $this->logger = $logger_factory->get('ek_api');
  }

  /**
   * Get project tasks with filters.
   *
   * @param array $options
   *   Filter options:
   *   - project_code: Filter by project code
   *   - user_id: Filter by assigned user
   *   - status: 'active', 'completed', 'overdue', 'all'
   *   - limit: Max results (default 100)
   *   - offset: Offset for pagination
   *
   * @return array
   *   Array of task data.
   */
  public function getProjectTasks(array $options = []): array {
    $query = $this->database->select('ek_project_tasks', 't');
    $query->fields('t');
    
    // Join to get project info
    $query->leftJoin('ek_project', 'p', 't.pcode = p.pcode');
    $query->addField('p', 'id', 'project_id');
    $query->addField('p', 'pname', 'project_name');

    // Apply filters
    if (!empty($options['project_code'])) {
      $query->condition('t.pcode', $options['project_code']);
    }

    if (!empty($options['user_id'])) {
      $query->condition('t.uid', (int) $options['user_id']);
    }

    // Status filter
    $now = time();
    if (!empty($options['status'])) {
      switch ($options['status']) {
        case 'active':
          $query->condition('t.completion_rate', 100, '<');
          break;
        case 'completed':
          $query->condition('t.completion_rate', 100, '>=');
          break;
        case 'overdue':
          $query->condition('t.completion_rate', 100, '<');
          $query->condition('t.end', $now, '<');
          $query->condition('t.end', 0, '>');
          break;
      }
    }

    // Pagination
    $limit = isset($options['limit']) ? min((int) $options['limit'], 500) : 100;
    $offset = isset($options['offset']) ? (int) $options['offset'] : 0;
    $query->range($offset, $limit);

    // Order by
    $query->orderBy('t.end', 'ASC');
    $query->orderBy('t.priority', 'ASC');

    $results = $query->execute();
    $tasks = [];

    while ($row = $results->fetchObject()) {
      $tasks[] = $this->formatTaskResponse($row, 'project');
    }

    return $tasks;
  }

  /**
   * Get sales tasks with filters.
   *
   * @param array $options
   *   Filter options:
   *   - document_code: Filter by invoice/purchase serial
   *   - user_id: Filter by assigned user
   *   - source: 'invoice' or 'purchase'
   *   - status: 'active', 'completed', 'overdue', 'all'
   *   - limit: Max results
   *   - offset: Offset for pagination
   *
   * @return array
   *   Array of task data.
   */
  public function getSalesTasks(array $options = []): array {
    $tasks = [];
    $sources = [];

    // Determine which tables to query
    if (empty($options['source']) || $options['source'] === 'invoice') {
      $sources['invoice'] = 'ek_sales_invoice_tasks';
    }
    if (empty($options['source']) || $options['source'] === 'purchase') {
      $sources['purchase'] = 'ek_sales_purchase_tasks';
    }

    foreach ($sources as $sourceType => $table) {
      $query = $this->database->select($table, 't');
      $query->fields('t');

      // Join to get document info
      if ($sourceType === 'invoice') {
        $query->leftJoin('ek_sales_invoice', 'd', 't.serial = d.serial');
        $query->addField('d', 'id', 'document_id');
        $query->addField('d', 'title', 'document_title');
      }
      else {
        $query->leftJoin('ek_sales_purchase', 'd', 't.serial = d.serial');
        $query->addField('d', 'id', 'document_id');
        $query->addField('d', 'title', 'document_title');
      }

      // Apply filters
      if (!empty($options['document_code'])) {
        $query->condition('t.serial', $options['document_code']);
      }

      if (!empty($options['user_id'])) {
        $query->condition('t.uid', (int) $options['user_id']);
      }

      // Status filter
      $now = time();
      if (!empty($options['status'])) {
        switch ($options['status']) {
          case 'active':
            $query->condition('t.completion_rate', 100, '<');
            break;
          case 'completed':
            $query->condition('t.completion_rate', 100, '>=');
            break;
          case 'overdue':
            $query->condition('t.completion_rate', 100, '<');
            $query->condition('t.end', $now, '<');
            $query->condition('t.end', 0, '>');
            break;
        }
      }

      // Pagination (split between sources if querying both)
      $limit = isset($options['limit']) ? min((int) $options['limit'], 500) : 100;
      $offset = isset($options['offset']) ? (int) $options['offset'] : 0;
      
      if (count($sources) > 1) {
        $limit = (int) ceil($limit / 2);
        $offset = (int) ceil($offset / 2);
      }
      
      $query->range($offset, $limit);
      $query->orderBy('t.end', 'ASC');
      $query->orderBy('t.priority', 'ASC');

      $results = $query->execute();

      while ($row = $results->fetchObject()) {
        $row->source_type = $sourceType;
        $tasks[] = $this->formatTaskResponse($row, 'sales');
      }
    }

    // Sort combined results
    usort($tasks, function($a, $b) {
      if ($a['end_timestamp'] === $b['end_timestamp']) {
        return $a['priority'] <=> $b['priority'];
      }
      return ($a['end_timestamp'] ?? PHP_INT_MAX) <=> ($b['end_timestamp'] ?? PHP_INT_MAX);
    });

    return $tasks;
  }

  /**
   * Record a new project task.
   *
   * @param array $data
   *   Task data.
   *
   * @return array
   *   Result with task ID or error.
   */
  public function recordProjectTask(array $data): array {
    // Validate required fields
    $errors = $this->validateTaskData($data, 'project');
    if (!empty($errors)) {
      return ['success' => FALSE, 'errors' => $errors];
    }

    // Verify project exists
    $projectExists = $this->database->select('ek_project', 'p')
      ->fields('p', ['id'])
      ->condition('pcode', $data['project_code'])
      ->execute()
      ->fetchField();

    if (!$projectExists) {
      return [
        'success' => FALSE,
        'errors' => ['project_code' => 'Project not found: ' . $data['project_code']],
      ];
    }

    $fields = $this->prepareTaskFields($data, 'project');

    try {
      $taskId = $this->database->insert('ek_project_tasks')
        ->fields($fields)
        ->execute();

      $this->logger->notice('API: Created project task @id for @pcode', [
        '@id' => $taskId,
        '@pcode' => $data['project_code'],
      ]);

      return [
        'success' => TRUE,
        'task_id' => $taskId,
        'message' => 'Task created successfully',
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API: Failed to create project task: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'errors' => ['database' => 'Failed to create task'],
      ];
    }
  }

  /**
   * Record a new sales task.
   *
   * @param array $data
   *   Task data.
   *
   * @return array
   *   Result with task ID or error.
   */
  public function recordSalesTask(array $data): array {
    // Validate required fields
    $errors = $this->validateTaskData($data, 'sales');
    if (!empty($errors)) {
      return ['success' => FALSE, 'errors' => $errors];
    }

    // Determine table and verify document exists
    $source = $data['source'] ?? 'invoice';
    if ($source === 'invoice') {
      $table = 'ek_sales_invoice_tasks';
      $docTable = 'ek_sales_invoice';
    }
    elseif ($source === 'purchase') {
      $table = 'ek_sales_purchase_tasks';
      $docTable = 'ek_sales_purchase';
    }
    else {
      return [
        'success' => FALSE,
        'errors' => ['source' => 'Invalid source. Must be "invoice" or "purchase"'],
      ];
    }

    // Verify document exists
    $docExists = $this->database->select($docTable, 'd')
      ->fields('d', ['id'])
      ->condition('serial', $data['document_code'])
      ->execute()
      ->fetchField();

    if (!$docExists) {
      return [
        'success' => FALSE,
        'errors' => ['document_code' => ucfirst($source) . ' not found: ' . $data['document_code']],
      ];
    }

    $fields = $this->prepareTaskFields($data, 'sales');

    try {
      $taskId = $this->database->insert($table)
        ->fields($fields)
        ->execute();

      $this->logger->notice('API: Created @source task @id for @serial', [
        '@source' => $source,
        '@id' => $taskId,
        '@serial' => $data['document_code'],
      ]);

      return [
        'success' => TRUE,
        'task_id' => $taskId,
        'source' => $source,
        'message' => 'Task created successfully',
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API: Failed to create sales task: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'errors' => ['database' => 'Failed to create task'],
      ];
    }
  }

  /**
   * Update a project task.
   *
   * @param int $taskId
   *   The task ID.
   * @param array $data
   *   Fields to update.
   *
   * @return array
   *   Result.
   */
  public function updateProjectTask(int $taskId, array $data): array {
    // Verify task exists
    $existing = $this->database->select('ek_project_tasks', 't')
      ->fields('t')
      ->condition('id', $taskId)
      ->execute()
      ->fetchObject();

    if (!$existing) {
      return [
        'success' => FALSE,
        'errors' => ['task_id' => 'Task not found'],
      ];
    }

    $fields = $this->prepareUpdateFields($data);
    
    if (empty($fields)) {
      return [
        'success' => FALSE,
        'errors' => ['data' => 'No valid fields to update'],
      ];
    }

    try {
      $this->database->update('ek_project_tasks')
        ->fields($fields)
        ->condition('id', $taskId)
        ->execute();

      // Check for completion notification
      $wasComplete = ((int) $existing->completion_rate >= 100);
      $nowComplete = isset($fields['completion_rate']) && ((int) $fields['completion_rate'] >= 100);
      
      return [
        'success' => TRUE,
        'task_id' => $taskId,
        'message' => 'Task updated successfully',
        'completion_changed' => !$wasComplete && $nowComplete,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API: Failed to update project task @id: @message', [
        '@id' => $taskId,
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'errors' => ['database' => 'Failed to update task'],
      ];
    }
  }

  /**
   * Update a sales task.
   *
   * @param int $taskId
   *   The task ID.
   * @param string $source
   *   Source type: 'invoice' or 'purchase'.
   * @param array $data
   *   Fields to update.
   *
   * @return array
   *   Result.
   */
  public function updateSalesTask(int $taskId, string $source, array $data): array {
    $table = ($source === 'purchase') ? 'ek_sales_purchase_tasks' : 'ek_sales_invoice_tasks';

    // Verify task exists
    $existing = $this->database->select($table, 't')
      ->fields('t')
      ->condition('id', $taskId)
      ->execute()
      ->fetchObject();

    if (!$existing) {
      return [
        'success' => FALSE,
        'errors' => ['task_id' => 'Task not found in ' . $source . ' tasks'],
      ];
    }

    $fields = $this->prepareUpdateFields($data);
    
    if (empty($fields)) {
      return [
        'success' => FALSE,
        'errors' => ['data' => 'No valid fields to update'],
      ];
    }

    try {
      $this->database->update($table)
        ->fields($fields)
        ->condition('id', $taskId)
        ->execute();

      $wasComplete = ((int) $existing->completion_rate >= 100);
      $nowComplete = isset($fields['completion_rate']) && ((int) $fields['completion_rate'] >= 100);

      return [
        'success' => TRUE,
        'task_id' => $taskId,
        'source' => $source,
        'message' => 'Task updated successfully',
        'completion_changed' => !$wasComplete && $nowComplete,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('API: Failed to update sales task @id: @message', [
        '@id' => $taskId,
        '@message' => $e->getMessage(),
      ]);
      return [
        'success' => FALSE,
        'errors' => ['database' => 'Failed to update task'],
      ];
    }
  }

  /**
   * Delete a project task.
   *
   * @param int $taskId
   *   The task ID.
   *
   * @return array
   *   Result.
   */
  public function deleteProjectTask(int $taskId): array {
    $deleted = $this->database->delete('ek_project_tasks')
      ->condition('id', $taskId)
      ->execute();

    if ($deleted) {
      $this->logger->notice('API: Deleted project task @id', ['@id' => $taskId]);
      return ['success' => TRUE, 'message' => 'Task deleted successfully'];
    }

    return [
      'success' => FALSE,
      'errors' => ['task_id' => 'Task not found or already deleted'],
    ];
  }

  /**
   * Delete a sales task.
   *
   * @param int $taskId
   *   The task ID.
   * @param string $source
   *   Source type: 'invoice' or 'purchase'.
   *
   * @return array
   *   Result.
   */
  public function deleteSalesTask(int $taskId, string $source): array {
    $table = ($source === 'purchase') ? 'ek_sales_purchase_tasks' : 'ek_sales_invoice_tasks';

    $deleted = $this->database->delete($table)
      ->condition('id', $taskId)
      ->execute();

    if ($deleted) {
      $this->logger->notice('API: Deleted @source task @id', [
        '@source' => $source,
        '@id' => $taskId,
      ]);
      return ['success' => TRUE, 'message' => 'Task deleted successfully'];
    }

    return [
      'success' => FALSE,
      'errors' => ['task_id' => 'Task not found or already deleted'],
    ];
  }

  /**
   * Format task row for API response.
   *
   * @param object $row
   *   Database row.
   * @param string $type
   *   Task type: 'project' or 'sales'.
   *
   * @return array
   *   Formatted task data.
   */
  protected function formatTaskResponse(object $row, string $type): array {
    $endTimestamp = $this->parseTimestamp($row->end);
    $startTimestamp = $this->parseTimestamp($row->start);
    $now = time();

    $task = [
      'id' => (int) $row->id,
      'event' => $row->event,
      'task' => $row->task,
      'assigned_user_id' => (int) $row->uid,
      'assigned_user_name' => $this->getUserName((int) $row->uid),
      'start_date' => $startTimestamp ? date('Y-m-d', $startTimestamp) : NULL,
      'start_timestamp' => $startTimestamp,
      'end_date' => $endTimestamp ? date('Y-m-d', $endTimestamp) : NULL,
      'end_timestamp' => $endTimestamp,
      'completion_rate' => (int) ($row->completion_rate ?? 0),
      'priority' => (int) ($row->priority ?? 2),
      'priority_label' => $this->getPriorityLabel((int) ($row->priority ?? 2)),
      'color' => $row->color ?? '#80ff80',
      'notify_bitmask' => (int) ($row->notify ?? 0),
      'notify_options' => $this->decodeNotifyBitmask((int) ($row->notify ?? 0)),
      'notify_recipients' => $this->formatNotifyWho($row->notify_who ?? ''),
      'last_notified' => $row->last_notified ? date('c', (int) $row->last_notified) : NULL,
      'notify_count' => (int) ($row->notify_count ?? 0),
    ];

    // Add status
    $task['status'] = 'active';
    if ($task['completion_rate'] >= 100) {
      $task['status'] = 'completed';
    }
    elseif ($endTimestamp && $endTimestamp < $now) {
      $task['status'] = 'overdue';
    }

    // Add type-specific fields
    if ($type === 'project') {
      $task['project_code'] = $row->pcode;
      $task['project_id'] = isset($row->project_id) ? (int) $row->project_id : NULL;
      $task['project_name'] = $row->project_name ?? NULL;
    }
    else {
      $task['document_code'] = $row->serial;
      $task['document_id'] = isset($row->document_id) ? (int) $row->document_id : NULL;
      $task['document_title'] = $row->document_title ?? NULL;
      $task['source'] = $row->source_type ?? 'invoice';
    }

    return $task;
  }

  /**
   * Validate task data for creation.
   *
   * @param array $data
   *   The task data.
   * @param string $type
   *   Task type: 'project' or 'sales'.
   *
   * @return array
   *   Array of errors (empty if valid).
   */
  protected function validateTaskData(array $data, string $type): array {
    $errors = [];

    // Required fields
    if ($type === 'project') {
      if (empty($data['project_code'])) {
        $errors['project_code'] = 'Project code is required';
      }
    }
    else {
      if (empty($data['document_code'])) {
        $errors['document_code'] = 'Document code (serial) is required';
      }
      if (empty($data['source']) || !in_array($data['source'], ['invoice', 'purchase'])) {
        $errors['source'] = 'Source must be "invoice" or "purchase"';
      }
    }

    if (empty($data['task'])) {
      $errors['task'] = 'Task description is required';
    }

    if (empty($data['uid']) && empty($data['user_id'])) {
      $errors['user_id'] = 'Assigned user ID is required';
    }
    else {
      $uid = $data['uid'] ?? $data['user_id'];
      if (!$this->userExists((int) $uid)) {
        $errors['user_id'] = 'User not found: ' . $uid;
      }
    }

    if (empty($data['start']) && empty($data['start_date'])) {
      $errors['start_date'] = 'Start date is required';
    }

    // Validate notify_who if provided
    if (!empty($data['notify_who'])) {
      $invalidUsers = $this->validateNotifyWho($data['notify_who']);
      if (!empty($invalidUsers)) {
        $errors['notify_who'] = 'Invalid user IDs: ' . implode(', ', $invalidUsers);
      }
    }

    // Validate dates
    $start = strtotime($data['start'] ?? $data['start_date'] ?? '');
    $end = !empty($data['end'] ?? $data['end_date'] ?? '') ? strtotime($data['end'] ?? $data['end_date']) : NULL;
    
    if ($start === FALSE) {
      $errors['start_date'] = 'Invalid start date format';
    }
    if ($end !== NULL && $end !== FALSE && $end < $start) {
      $errors['end_date'] = 'End date must be after start date';
    }

    return $errors;
  }

  /**
   * Prepare task fields for database insert.
   *
   * @param array $data
   *   The input data.
   * @param string $type
   *   Task type.
   *
   * @return array
   *   Database fields.
   */
  protected function prepareTaskFields(array $data, string $type): array {
    $fields = [
      'event' => Xss::filter($data['event'] ?? ''),
      'task' => Xss::filter($data['task']),
      'uid' => (int) ($data['uid'] ?? $data['user_id']),
      'start' => strtotime($data['start'] ?? $data['start_date']),
      'end' => !empty($data['end'] ?? $data['end_date']) ? strtotime($data['end'] ?? $data['end_date']) : NULL,
      'completion_rate' => (int) ($data['completion_rate'] ?? 0),
      'priority' => (int) ($data['priority'] ?? 2),
      'color' => $data['color'] ?? '#80ff80',
      'notify' => $this->parseNotifyInput($data['notify'] ?? 0),
      'notify_who' => $this->normalizeNotifyWho($data['notify_who'] ?? ''),
      'weight' => 0,
    ];

    if ($type === 'project') {
      $fields['pcode'] = $data['project_code'];
    }
    else {
      $fields['serial'] = $data['document_code'];
    }

    return $fields;
  }

  /**
   * Prepare fields for update operation.
   *
   * @param array $data
   *   The input data.
   *
   * @return array
   *   Fields to update.
   */
  protected function prepareUpdateFields(array $data): array {
    $fields = [];
    $allowedFields = [
      'event', 'task', 'uid', 'user_id', 'start', 'start_date', 
      'end', 'end_date', 'completion_rate', 'priority', 'color',
      'notify', 'notify_who',
    ];

    foreach ($allowedFields as $field) {
      if (array_key_exists($field, $data)) {
        switch ($field) {
          case 'event':
          case 'task':
            $fields[$field] = Xss::filter($data[$field]);
            break;
          case 'uid':
          case 'user_id':
            if ($this->userExists((int) $data[$field])) {
              $fields['uid'] = (int) $data[$field];
            }
            break;
          case 'start':
          case 'start_date':
            $timestamp = strtotime($data[$field]);
            if ($timestamp !== FALSE) {
              $fields['start'] = $timestamp;
            }
            break;
          case 'end':
          case 'end_date':
            if (empty($data[$field])) {
              $fields['end'] = NULL;
            }
            else {
              $timestamp = strtotime($data[$field]);
              if ($timestamp !== FALSE) {
                $fields['end'] = $timestamp;
              }
            }
            break;
          case 'completion_rate':
            $fields['completion_rate'] = max(0, min(100, (int) $data[$field]));
            break;
          case 'priority':
            $fields['priority'] = max(1, min(3, (int) $data[$field]));
            break;
          case 'color':
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $data[$field])) {
              $fields['color'] = $data[$field];
            }
            break;
          case 'notify':
            $fields['notify'] = $this->parseNotifyInput($data[$field]);
            break;
          case 'notify_who':
            $fields['notify_who'] = $this->normalizeNotifyWho($data[$field]);
            break;
        }
      }
    }

    return $fields;
  }

  /**
   * Parse notify input (bitmask or array of option names).
   *
   * @param mixed $input
   *   The input value.
   *
   * @return int
   *   The bitmask value.
   */
  protected function parseNotifyInput($input): int {
    if (is_numeric($input)) {
      return (int) $input;
    }

    if (is_array($input)) {
      $bitmask = 0;
      $optionMap = [
        'daily' => TaskNotificationService::NOTIFY_DAILY,
        'weekly' => TaskNotificationService::NOTIFY_WEEKLY,
        'monthly' => TaskNotificationService::NOTIFY_MONTHLY,
        '5_days' => TaskNotificationService::NOTIFY_5_DAYS_BEFORE,
        '3_days' => TaskNotificationService::NOTIFY_3_DAYS_BEFORE,
        '1_day' => TaskNotificationService::NOTIFY_1_DAY_BEFORE,
        'overdue' => TaskNotificationService::NOTIFY_ON_OVERDUE,
        'complete' => TaskNotificationService::NOTIFY_ON_COMPLETE,
      ];

      foreach ($input as $option) {
        if (is_numeric($option)) {
          $bitmask |= (int) $option;
        }
        elseif (isset($optionMap[strtolower($option)])) {
          $bitmask |= $optionMap[strtolower($option)];
        }
      }
      return $bitmask;
    }

    return 0;
  }

  /**
   * Decode notify bitmask to array of option names.
   *
   * @param int $bitmask
   *   The bitmask.
   *
   * @return array
   *   Array of option names.
   */
  protected function decodeNotifyBitmask(int $bitmask): array {
    $options = [];
    $map = [
      TaskNotificationService::NOTIFY_DAILY => 'daily',
      TaskNotificationService::NOTIFY_WEEKLY => 'weekly',
      TaskNotificationService::NOTIFY_MONTHLY => 'monthly',
      TaskNotificationService::NOTIFY_5_DAYS_BEFORE => '5_days_before',
      TaskNotificationService::NOTIFY_3_DAYS_BEFORE => '3_days_before',
      TaskNotificationService::NOTIFY_1_DAY_BEFORE => '1_day_before',
      TaskNotificationService::NOTIFY_ON_OVERDUE => 'on_overdue',
      TaskNotificationService::NOTIFY_ON_COMPLETE => 'on_complete',
    ];

    foreach ($map as $flag => $name) {
      if ($bitmask & $flag) {
        $options[] = $name;
      }
    }

    return $options;
  }

  /**
   * Normalize notify_who input to comma-separated UIDs.
   *
   * @param mixed $input
   *   Array of UIDs or comma-separated string.
   *
   * @return string|null
   *   Comma-separated UIDs or NULL.
   */
  protected function normalizeNotifyWho($input): ?string {
    if (empty($input)) {
      return NULL;
    }

    if (is_array($input)) {
      $uids = array_filter(array_map('intval', $input));
    }
    else {
      $uids = array_filter(array_map('trim', explode(',', $input)));
      $uids = array_map('intval', $uids);
    }

    return !empty($uids) ? implode(',', $uids) : NULL;
  }

  /**
   * Validate notify_who users exist.
   *
   * @param mixed $input
   *   The notify_who input.
   *
   * @return array
   *   Array of invalid user IDs.
   */
  protected function validateNotifyWho($input): array {
    $invalid = [];
    
    if (is_array($input)) {
      $uids = $input;
    }
    else {
      $uids = array_filter(array_map('trim', explode(',', $input)));
    }

    foreach ($uids as $uid) {
      if (!$this->userExists((int) $uid)) {
        $invalid[] = $uid;
      }
    }

    return $invalid;
  }

  /**
   * Format notify_who UIDs to array with user info.
   *
   * @param string $notifyWho
   *   Comma-separated UIDs.
   *
   * @return array
   *   Array of user info.
   */
  protected function formatNotifyWho(string $notifyWho): array {
    if (empty($notifyWho)) {
      return [];
    }

    $users = [];
    $uids = array_filter(explode(',', $notifyWho));

    foreach ($uids as $uid) {
      $uid = (int) trim($uid);
      if ($uid > 0) {
        $users[] = [
          'uid' => $uid,
          'name' => $this->getUserName($uid),
        ];
      }
    }

    return $users;
  }

  /**
   * Get user name by UID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return string|null
   *   The username or NULL.
   */
  protected function getUserName(int $uid): ?string {
    static $cache = [];
    
    if (!isset($cache[$uid])) {
      $account = \Drupal\user\Entity\User::load($uid);
      $cache[$uid] = $account ? $account->getAccountName() : NULL;
    }
    
    return $cache[$uid];
  }

  /**
   * Check if user exists.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return bool
   *   TRUE if user exists.
   */
  protected function userExists(int $uid): bool {
    return $this->getUserName($uid) !== NULL;
  }

  /**
   * Get priority label.
   *
   * @param int $priority
   *   Priority value (1-3).
   *
   * @return string
   *   Priority label.
   */
  protected function getPriorityLabel(int $priority): string {
    $labels = [1 => 'high', 2 => 'medium', 3 => 'low'];
    return $labels[$priority] ?? 'medium';
  }

  /**
   * Parse timestamp from various formats.
   *
   * @param mixed $value
   *   The value.
   *
   * @return int|null
   *   Timestamp or NULL.
   */
  protected function parseTimestamp($value): ?int {
    if (empty($value) || $value === '0000-00-00' || $value === 0 || $value === '0') {
      return NULL;
    }
    
    if (is_numeric($value)) {
      $intVal = (int) $value;
      return ($intVal > 0 && $intVal < 4102444800) ? $intVal : NULL;
    }
    
    $parsed = strtotime($value);
    return ($parsed !== FALSE) ? $parsed : NULL;
  }

}