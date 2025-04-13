<?php

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * Provides a confirmation form before cloning an address book entry.
 */
class AddressBookCloneConfirmForm extends ConfirmFormBase {

  /**
   * The ID of the address book entry to clone.
   *
   * @var int
   */
  protected $abid;

  /**
   * Address book data.
   *
   * @var object
   */
  protected $addressData;

  /**
   * New type for the cloned entry.
   *
   * @var int
   */
  protected $newType;
 
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'address_book_clone_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $abid = NULL) {
    $this->abid = $abid;
    $canClone = $this->checkCloneability($abid);
    
    if ($canClone['clone'] == FALSE) {
      return $this->redirect('ek_address_book.view' , ['abid' => $this->abid]);
    }
    
    $this->addressData = $canClone['data'];
    $this->newType = $canClone['newtype'];
    $types = [1 => $this->t('client'), 2 => $this->t('supplier'), 3 => $this->t('other')];
    $form = parent::buildForm($form, $form_state);
    
    $form['description'] = [
      '#markup' => $this->t('<p><strong>Name:</strong> @name</p><p><strong>Type:</strong> @type</p>', [
        '@name' => $this->addressData->name,
        '@type' => $types[$this->addressData->type],
      ]),
      '#weight' => -10,
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clone this address book entry?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('ek_address_book.view' , ['abid' => $this->abid]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Perform the clone operation
    $newId = $this->performClone($this->addressData, $this->newType);
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['address_book_card']);
    // Set success message
    $this->messenger()->addMessage($this->t('Address book entry has been cloned successfully.'));
    
    // Redirect to the edit page of the new entry
    $form_state->setRedirect('ek_address_book.edit', ['abid' => $newId]);
  }

  /**
   * Check if an address book entry can be cloned.
   *
   * @param int $abid
   *   The address book ID to check.
   *
   * @return array
   *   An array containing clone status, data, and new type if applicable.
   */
  private function checkCloneability($abid) {
    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_address_book', 'ab');
    $query->fields('ab');
    $query->condition('id', $abid);
    $r = $query->execute()->fetchObject();
    
    if (!$r) {
      return ['clone' => FALSE];
    }
    
    // check the entry has not been cloned already
    // there should be only 3 types per name
    $clone = FALSE;
    $newtype = NULL;
    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'type']);
            $query->condition('name', $r->name);
            $query->condition('type', $r->type, '<>');
            $query->orderBy('type');
            $check = $query->execute()->fetchAllKeyed();
           if(count($check) == 0) { 
                $newtype = ($r->type == 1) ? 2 : 1;
                $clone = TRUE;
            }  elseif(count($check) == 1 && in_array("3", $check) && ($r->type == 2 || $r->type == 1)) {
                $newtype = ($r->type == 1) ? 2 : 1;
                $clone = TRUE;
            } 
    
    return [
      'clone' => $clone,
      'data' => $r,
      'newtype' => $newtype,
    ];
  }

  /**
   * Perform the actual cloning of an address book entry.
   *
   * @param object $r
   *   The address book data to clone.
   * @param int $newtype
   *   The new type value for the cloned entry.
   *
   * @return int
   *   The ID of the new entry.
   */
  private function performClone($r, $newtype) {
    // Copy main data
    $fields = [
      'name' => $r->name,
      'shortname' => $r->shortname,
      'address' => $r->address,
      'address2' => $r->address2,
      'postcode' => $r->postcode,
      'city' => $r->city,
      'country' => $r->country,
      'telephone' => $r->telephone,
      'fax' => $r->fax,
      'website' => $r->website,
      'type' => $newtype,
      'category' => $r->category,
      'activity' => $r->activity,
      'status' => 1,
      'stamp' => strtotime("now"),
      'logo' => $r->logo,
    ];
    
    $newid = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_address_book')
                    ->fields($fields)->execute();
    
    // Copy contacts
    $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_address_book_contacts', 'c');
    $data = $query
            ->fields('c')
            ->condition('c.abid', $r->id, '=')
            ->execute();
            
    while ($contact = $data->fetchObject()) {
      $fields = [
        'abid' => $newid,
        'contact_name' => $contact->contact_name,
        'salutation' => $contact->salutation,
        'title' => $contact->title,
        'telephone' => $contact->telephone,
        'mobilephone' => $contact->mobilephone,
        'email' => $contact->email,
        'card' => $contact->card,
        'department' => $contact->department,
        'link' => $contact->link,
        'comment' => $contact->comment,
        'main' => $contact->main,
        'stamp' => strtotime("now"),
      ];
      
      Database::getConnection('external_db', 'external_db')
              ->insert('ek_address_book_contacts')
              ->fields($fields)->execute();
    }
    
    // Create comment entry
    Database::getConnection('external_db', 'external_db')
            ->insert('ek_address_book_comment')
            ->fields(['abid' => $newid])->execute();
            
    return $newid;
  }
}