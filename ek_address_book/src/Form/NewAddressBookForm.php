<?php

/**
 * @file
 * Contains \Drupal\ek_address_book\Form\NewAddressBookForm.
 */

namespace Drupal\ek_address_book\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a new organization form.
 */
class NewAddressBookForm extends FormBase {

    /**
     * The country manager.
     *
     * @var \Drupal\Core\Locale\CountryManagerInterface
     */
    protected $countryManager;

    public function __construct(CountryManagerInterface $country_manager) {
        $this->countryManager = $country_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('country_manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_address_book_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $abid = NULL) {

        if (isset($abid)) {

            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $abid,
            );

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book', 'ab');
                $query->fields('ab');
                $query->condition('id', $abid, '=');
                $r = $query->execute()->fetchAssoc();
            

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book_contacts', 'abc');
                $query->condition('abid', $abid);
                $rc = $query->countQuery()->execute()->fetchField();
            
        }

        $form['name'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#description' => isset($abid) ? t('Organization name') : '',
            '#required' => true,
            '#default_value' => isset($r['name']) ? $r['name'] : null,
            '#attributes' => array('placeholder' => t('Organization name')),
            '#attached' => array(
                'library' => array(
                    'ek_address_book/ek_address_book.script.sn',
                ),
            ),
        );

        $form['shortname'] = array(
            '#type' => 'textfield',
            '#id' => 'short_name',
            '#description' => isset($abid) ? t('Short name') : '',
            '#size' => 10,
            '#maxlength' => 5,
            '#required' => true,
            '#default_value' => isset($r['shortname']) ? $r['shortname'] : null,
            '#attributes' => array('placeholder' => t('Short name')),
        );

        $form['address'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#description' => isset($abid) ? t('Address line 1') : '',
            '#maxlength' => 255,
            '#default_value' => isset($r['address']) ? $r['address'] : null,
            '#attributes' => array('placeholder' => t('Address line 1')),
        );

        $form['address2'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#description' => isset($abid) ? t('Address line 2') : '',
            '#maxlength' => 255,
            '#default_value' => isset($r['address2']) ? $r['address2'] : null,
            '#attributes' => array('placeholder' => t('Address line 2')),
        );
        
        $form['state'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#description' => isset($abid) ? t('State') : '',
            '#maxlength' => 50,
            '#default_value' => isset($r['state']) ? $r['state'] : null,
            '#attributes' => array('placeholder' => t('State')),
        );

        $form['postcode'] = array(
            '#type' => 'textfield',
            '#size' => 12,
            '#description' => isset($abid) ? t('Postcode') : '',
            '#maxlength' => 20,
            '#default_value' => isset($r['postcode']) ? $r['postcode'] : null,
            '#attributes' => array('placeholder' => t('Postcode')),
        );

        $form['city'] = array(
            '#type' => 'textfield',
            '#description' => isset($abid) ? t('City') : '',
            '#size' => 30,
            '#maxlength' => 100,
            '#default_value' => isset($r['city']) ? $r['city'] : null,
            '#attributes' => array('placeholder' => t('City')),
        );


        $countries = $this->countryManager->getList();
        $form['country'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($countries, $countries),
            '#required' => TRUE,
            '#default_value' => isset($r['country']) ? $r['country'] : NULL,
        );
        
        $form['reg'] = array(
            '#type' => 'textfield',
            '#size' => 15,
            '#description' => isset($abid) ? t('registration number') : '',
            '#maxlength' => 30,
            '#default_value' => isset($r['reg']) ? $r['reg'] : null,
            '#attributes' => array('placeholder' => t('reg. number')),
        );
        
        $form['telephone'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#description' => isset($abid) ? t('Telephone') : '',
            '#maxlength' => 30,
            '#default_value' => isset($r['telephone']) ? $r['telephone'] : null,
            '#attributes' => array('placeholder' => t('Telephone')),
        );

        $form['fax'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#description' => isset($abid) ? t('Fax No.') : '',
            '#maxlength' => 30,
            '#default_value' => isset($r['fax']) ? $r['fax'] : null,
            '#attributes' => array('placeholder' => t('Fax No.')),
        );

        $form['website'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#description' => isset($abid) ? t('Web site') : '',
            '#maxlength' => 100,
            '#default_value' => isset($r['website']) ? $r['website'] : null,
            '#attributes' => array('placeholder' => t('Web site')),
        );

        $form['type'] = array(
            '#type' => 'select',
            '#options' => array(1 => t('client'), 2 => t('supplier'), 3 => t('other')),
            '#default_value' => isset($r['type']) ? $r['type'] : null,
            '#description' => t('Organization type'),
            '#required' => TRUE,
        );

        $form['category'] = array(
            '#type' => 'select',
            '#options' => array(1 => t('Head office'), 2 => t('Store'), 3 => t('Factory'), 4 => t('Other')),
            '#default_value' => isset($r['category']) ? $r['category'] : null,
            '#description' => t('Organization category'),
            '#required' => TRUE,
        );

        $form['status'] = array(
            '#type' => 'select',
            '#options' => array(0 => t('inactive'), 1 => t('active')),
            '#default_value' => isset($r['status']) ? $r['status'] : '1',
            //'#title' => t('Status'),
            '#required' => TRUE,
        );


        $form['tags'] = array(
            '#type' => 'textfield',
            '#default_value' => isset($r['activity']) ? $r['activity'] : null,
            '#attributes' => array('class' => ['form-select-tag'], 'style' => array('width:200px;')),
            '#description' => t('Tags'),
            '#required' => FALSE,
            '#maxlength' => 200,
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_tageditor'),
                'drupalSettings' => array('auto_complete' => 'ek_address_book/tag_activity'),
            ),
        );

        //current logo if any
        if (isset ($r['logo']) && $r['logo'] <> '') {
            $logo = "<a href='" . file_create_url($r['logo']) . "' target='_blank'><img class='thumbnail' src=" . file_create_url($r['logo']) . "></a>";
            $form['delete_logo'] = array(
                '#type' => 'checkbox',
                '#title' => t('delete logo'),
                '#attributes' => array('onclick' => "jQuery('#logo ').toggleClass( 'delete');"),
                '#prefix' => "<div class='container-inline'>",
            );
            $form["currentlogo"] = array(
                '#markup' => "<p id='logo'style='padding:2px;'>" . $logo . "</p>",
                '#suffix' => '</div>',
            );
            //use to delete if upload new when submit   
            $form["logo_uri"] = array(
                    '#type' => "hidden",
                    '#value' => $r['logo'],
            );
        } else {
            $form['delete_logo'] = NULL;
            $form["currentlogo"] = NULL;
        }
        $form['logo'] = array(
            '#type' => 'file',
            '#title' => t('Upload logo'),
            '#maxlength' => 200,
        );

// insert the name cards
        $i = 0;
        $salutation = array('-', t('Mr.'), t('Mrs.'), t('Miss.'));
        if ($vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('salutation', 0, 1)) {
            foreach ($vocabulary as $item) {
                array_push($salutation, $item->name);
            }
        } 
        if (isset($rc) && $rc > 0) {

            //namecard exist
            $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc');
            $query->condition('abid', $abid, '=');
            $data = $query->execute();
            /*
            $query = "SELECT * from {ek_address_book_contacts} WHERE abid=:id order by id";
            $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $abid));*/

            while ($rc = $data->fetchAssoc()) {

                $form[$i] = array(
                    '#type' => 'details',
                    '#title' => t('Contact card No. @i', array('@i' => $i + 1)),
                    '#collapsible' => TRUE,
                    '#collapsed' => FALSE,
                        //'#attributes' => ($rc['main'] == 1) ? array('class' => array('select')) : array(),
                );

                $form[$i]['id' . $i] = array(
                    '#type' => 'hidden',
                    '#default_value' => $rc['id'],
                );

                $form[$i]['delete' . $i] = array(
                    '#type' => 'checkbox',
                    '#title' => t('Delete card'),
                    '#attributes' => array('onclick' => "jQuery('#edit-$i summary').toggleClass( 'delete');"),
                );

                $form[$i]['main' . $i] = array(
                    '#type' => 'checkbox',
                    '#title' => t('Set as primary'),
                    '#default_value' => isset($rc['main']) ? $rc['main'] : 0,
                    '#attributes' => array('title' => t('Set as primary'), 'onclick' => "jQuery('#edit-$i summary').toggleClass( 'select');"),
                );

                $form[$i]['salutation' . $i] = array(
                    '#type' => 'select',
                    '#options' => array_combine($salutation, $salutation),
                    '#maxlength' => 255,
                    '#required' => FALSE,
                    '#default_value' => isset($rc['salutation']) ? $rc['salutation'] : null,
                    '#prefix' => "<div class='container-inline'>",
                );

                $form[$i]['contact_name' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 60,
                    '#maxlength' => 255,
                    //'#required' => TRUE,
                    '#default_value' => isset($rc['contact_name']) ? $rc['contact_name'] : null,
                    '#attributes' => array('placeholder' => t('Contact name')),
                    '#suffix' => "</div>"
                );

                $form[$i]['title' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 60,
                    '#title' => t('title'),
                    '#maxlength' => 255,
                    '#required' => FALSE,
                    '#default_value' => isset($rc['title']) ? $rc['title'] : null,
                    '#attributes' => array('placeholder' => t('Title or function')),
                );

                $form[$i]['ctelephone' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#title' => t('Telephone'),
                    '#maxlength' => 30,
                    '#default_value' => isset($rc['telephone']) ? $rc['telephone'] : null,
                    '#attributes' => array('placeholder' => t('Telephone')),
                );

                $form[$i]['cmobilephone' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#title' => t('Mobile phone'),
                    '#maxlength' => 30,
                    '#default_value' => isset($rc['mobilephone']) ? $rc['mobilephone'] : null,
                    '#attributes' => array('placeholder' => t('Mobile phone')),
                );

                $form[$i]['email' . $i] = array(
                    '#type' => 'email',
                    '#size' => 50,
                    '#title' => t('Email'),
                    '#maxlength' => 100,
                    '#default_value' => isset($rc['email']) ? $rc['email'] : null,
                    '#attributes' => array('placeholder' => t('Email address')),
                );

                $form[$i]['image' . $i] = array(
                    '#type' => 'file',
                    '#title' => t('Upload a name card image'),
                    '#maxlength' => 40,
                );

                //current image if any
                if ($rc['card'] <> '') {
                    $image = "<a href='" . file_create_url($rc['card']) . "' target='_blank'><img class='thumbnail' src=" . file_create_url($rc['card']) . "></a>";
                    $form[$i]['image_delete' . $i] = array(
                        '#type' => 'checkbox',
                        '#title' => t('delete image'),
                        '#attributes' => array('onclick' => "jQuery('#current$i ').toggleClass( 'delete');"),
                        '#prefix' => "<div class='container-inline'>",
                    );
                    $form[$i]["currentimage" . $i] = array(
                        '#markup' => "<p id='current$i'style='padding:2px;'>" . $image . "</p>",
                        '#suffix' => '</div>',
                    );
                }


                $form[$i]['department' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 60,
                    '#title' => t('Department'),
                    '#maxlength' => 100,
                    '#default_value' => isset($rc['department']) ? $rc['department'] : null,
                    '#attributes' => array('placeholder' => t('Department or office')),
                );

                $form[$i]['link' . $i] = array(
                    '#type' => 'textfield',
                    '#size' => 60,
                    '#title' => t('Social network'),
                    '#maxlength' => 100,
                    '#default_value' => isset($rc['link']) ? $rc['link'] : null,
                    '#attributes' => array('placeholder' => t('Social network')),
                );

                $form[$i]['ccomment' . $i] = array(
                    '#type' => 'textarea',
                    '#default_value' => isset($rc['comment']) ? $rc['comment'] : null,
                    '#attributes' => array('placeholder' => t('Add note')),
                    '#rows' => 1
                );

                $i++;
            }
        }

        $form[$i] = array(
            '#type' => 'details',
            '#title' => t('New contact card No. @i', array('@i' => $i + 1)),
            '#collapsible' => TRUE,
            '#open' => TRUE,
        );

        $form[$i]['id' . $i] = array(
            '#type' => 'hidden',
            '#default_value' => 'new',
        );



        $form[$i]['contact_name' . $i] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#attributes' => array('placeholder' => t('Contact name')),
        );

        $form[$i]['main' . $i] = array(
            '#type' => 'checkbox',
            '#title' => t('Set as primary'),
            '#default_value' => 0,
            '#attributes' => array('title' => t('Set as primary')),
            // Hide data fieldset when field is empty.
            '#states' => array(
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['salutation' . $i] = array(
            '#type' => 'select',
            '#options' => array_combine($salutation, $salutation),
            '#required' => FALSE,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['title' . $i] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#required' => FALSE,
            '#attributes' => array('placeholder' => t('Title or function')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['ctelephone' . $i] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => t('Telephone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['cmobilephone' . $i] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 30,
            '#attributes' => array('placeholder' => t('Mobile phone')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['email' . $i] = array(
            '#type' => 'email',
            '#size' => 50,
            '#maxlength' => 50,
            '#attributes' => array('placeholder' => t('Email address')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['image' . $i] = array(
            '#type' => 'file',
            '#title' => t('Upload a name card image'),
            '#maxlength' => 100,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['department' . $i] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => t('Department or office')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['link' . $i] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 100,
            '#attributes' => array('placeholder' => t('Social network')),
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form[$i]['ccomment' . $i] = array(
            '#type' => 'textarea',
            '#rows' => 1,
            '#states' => array(
                // Hide data fieldset when field is empty.
                'invisible' => array(
                    "input[name='contact_name$i']" => array('value' => ''),
                ),
            ),
        );

        $form['cards'] = array(
            '#type' => 'hidden',
            '#default_value' => $i,
        );


        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));



        return $form;
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        // Check for a new uploaded logo.
            $field = "logo";
            $validators = array('file_validate_is_image' => array());
            $file = file_save_upload($field , $validators, FALSE, 0);

                if ($file != NULL && !empty($file)) {
                    $res = file_validate_image_resolution($file, '400x400');
                      // File upload was attempted.
                      if ($file) {
                        // Put the temporary file in form_values so we can save it on submit.
                        $form_state->setValue($field, $file) ;
                      }
                      else {
                        // File upload failed.
                       $form_state->setErrorByName($field, $this->t('Logo could not be uploaded'));
                      }
                } else {
                  $form_state->setValue($field, 0);
                  
                }
                
        for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {
            if ($form_state->getValue('contact_name' . $i) <> '') {
                // Handle file uploads.
                //$validators = array('file_validate_extensions' => array('ico png gif jpg jpeg apng svg'));
                $validators = array('file_validate_is_image' => array());
                $field = "image" . $i;
                // Check for a new uploaded .
                $file = file_save_upload($field, $validators, FALSE, 0);
                if ($file != NULL && !empty($file)) {
                    // File upload was attempted.
                    if ($file) {
                        $form_state->setValue($field, $file);
                    } else {
                        // File upload failed.
                        $form_state->setErrorByName($field, $this->t('Card No. @i could not be uploaded', array('@i' => $i + 1)));
                    }
                } else {
                    $form_state->setValue($field, 0);
                }
            } //if name
        } //loop
        $primary = 0;
        for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {

            if ($form_state->getValue('contact_name' . $i) <> '') {
                $primary += $form_state->getValue('main' . $i);
            }
        }
        if ($primary == 0)
            $form_state->setErrorByName('main', $this->t('There is not primary card. Please select one.'));
        if ($primary > 1)
            $form_state->setErrorByName('main', $this->t('You can only have 1 primary card.'));
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $fields = array(
            'name' => $form_state->getValue('name'),
            'shortname' => str_replace('/', '|', $form_state->getValue('shortname')),
            'reg' => Xss::filter($form_state->getValue('reg')),
            'address' => Xss::filter($form_state->getValue('address')),
            'address2' => Xss::filter($form_state->getValue('address2')),
            'city' => Xss::filter($form_state->getValue('city')),
            'postcode' => Xss::filter($form_state->getValue('postcode')),
            'state' => Xss::filter($form_state->getValue('state')),
            'country' => $form_state->getValue('country'),
            'telephone' => Xss::filter($form_state->getValue('telephone')),
            'fax' => Xss::filter($form_state->getValue('fax')),
            'website' => Xss::filter($form_state->getValue('website')),
            'type' => $form_state->getValue('type'),
            'category' => $form_state->getValue('category'),
            'activity' => Xss::filter($form_state->getValue('tags')),
            'stamp' => strtotime("now"),
        );


        if ($form_state->getValue('for_id') == '') {
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_address_book')
                    ->fields($fields)
                    ->execute();
            $id = $insert;

            //add a line in comment table
            $insert_comment = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_address_book_comment')
                    ->fields(['abid' => $id, 'comment' => ''])
                    ->execute();
        } else {

            //update existing
            $update = Database::getConnection('external_db', 'external_db')->update('ek_address_book')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->fields($fields)
                    ->execute();

            $id = $form_state->getValue('for_id');
        }
//logo
            //first delete current if requested
            $del = FALSE;
            if ($form_state->getValue('delete_logo') == 1) {

                \Drupal::service('file_system')->delete($form_state->getValue('logo_uri'));
                \Drupal::messenger()->addStatus(t("Old logo deleted"));
                $logo = '';
                $del = TRUE;
            } else {
                $logo = $form_state->getValue('logo_uri');
            }
            
            //second, upload if any image is available
            if (!$form_state->getValue('logo') == 0) {
                if ($file = $form_state->getValue('logo')) {

                    $dir = "private://address_book/cards/" . $id;
                    \Drupal::service('file_system')->prepareDirectory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                    $logo = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                    //Resize after copy
                    $image_factory = \Drupal::service('image.factory');
                    $image = $image_factory->get($logo);
                    $image->scale(60);
                    $image->save();
                    \Drupal::messenger()->addStatus(t("New logo uploaded"));
                  
                    //remove old if any
                    if(!isset($del) && $form_state->getValue('logo_uri') != '') {
                        \Drupal::service('file_system')->delete($form_state->getValue('logo_uri'));
                    }
                }
             }
             
             Database::getConnection('external_db', 'external_db')
                    ->update('ek_address_book')
                    ->condition('id', $id)
                    ->fields(['logo' => $logo])
                    ->execute();
             
             
//update contact card
        if ($form_state->getValue('cards') >= 0) {
            //update cards

            for ($i = 0; $i <= $form_state->getValue('cards'); $i++) {
                //Check first for deletion
                if ($form_state->getValue('delete' . $i) == 1) {

                    $query = Database::getConnection('external_db', 'external_db')
                      ->select('ek_address_book_contacts', 'abc');
                    $query->fields('abc',['card']);
                    $query->condition('id', $form_state->getValue('id' . $i), '=');
                    $file = $query->execute()->fetchField();
                   
                    if ($file) {
                        $file = str_replace('private://', '', $file);
                        $path = PrivateStream::basePath() . '/' . $file;
                        \Drupal::service('file_system')->delete($path);
                        $log = 'The name card file ' . $file . ' was deleted.';
                        \Drupal::logger('ek_address_book')->notice($log);
                    }
                    $del = Database::getConnection('external_db', 'external_db')
                            ->delete('ek_address_book_contacts')
                            ->condition('id', $form_state->getValue('id' . $i))
                            ->execute();
                    \Drupal::messenger()->addWarning(t('Deleted card for @i', ['@i' => $form_state->getValue('contact_name' . $i)]));
                    $a = array('@c' => $form_state->getValue('contact_name' . $i));
                    $log = 'The name card file ' . $a . ' was deleted.';
                    \Drupal::logger('ek_address_book')->notice($log);
                } else {
                    //verify  the name input and proceed only if not empty
                    if ($form_state->getValue('contact_name' . $i) <> '') {

                        // If the user uploaded a new card save it to a permanent location
                        // retrieve previous card file if any
                        $filename = '';
                        if (!$form_state->getValue('id' . $i) <> 'new') {
                            $query = Database::getConnection('external_db', 'external_db')
                                ->select('ek_address_book_contacts', 'abc');
                            $query->fields('abc',['card']);
                            $query->condition('id', $form_state->getValue('id' . $i), '=');
                            $filename = $query->execute()->fetchField();
                            
                            $old_file = $filename;

                            if ($form_state->getValue('image_delete' . $i) == 1) {
                                //delete existing file
                                $pic = \Drupal::service('file_system')->realpath($old_file);
                                unlink($pic);
                                \Drupal::messenger()->addWarning(t("Card image deleted for card @i", ['@i' => $i + 1]));
                                $filename = '';
                            }
                        }


                        if (!$form_state->getValue('image' . $i) == 0) {

                            $file = $form_state->getValue('image' . $i);
                            //unset($file);
                            $dir = "private://address_book/cards/" . $id;
                            \Drupal::service('file_system')->prepareDirectory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                            $filename = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);
                        }



                        $fields = array(
                            'abid' => $id,
                            'contact_name' => $form_state->getValue('contact_name' . $i),
                            'salutation' => $form_state->getValue('salutation' . $i),
                            'title' => $form_state->getValue('title' . $i),
                            'telephone' => $form_state->getValue('ctelephone' . $i),
                            'mobilephone' => $form_state->getValue('cmobilephone' . $i),
                            'email' => $form_state->getValue('email' . $i),
                            'card' => $filename,
                            'department' => $form_state->getValue('department' . $i),
                            'link' => $form_state->getValue('link' . $i),
                            'comment' => $form_state->getValue('ccomment' . $i),
                            'main' => $form_state->getValue('main' . $i),
                            'stamp' => strtotime("now"),
                        );


                        //verify if it is an existing or new entry
                        if ($form_state->getValue('id' . $i) == 'new') {

                            $insert2 = Database::getConnection('external_db', 'external_db')
                                    ->insert('ek_address_book_contacts')
                                    ->fields($fields)
                                    ->execute();
                        } else {

                            //update existing
                            $update2 = Database::getConnection('external_db', 'external_db')->update('ek_address_book_contacts')
                                    ->condition('id', $form_state->getValue('id' . $i))
                                    ->fields($fields)
                                    ->execute();
                        }
                    } else {
                        //\Drupal::messenger()->addWarning(t('Empty contact No. @i not recorded.', array('@i' => $i + 1)));
                    }
                }
            } //loop
        } else {
            \Drupal::messenger()->addWarning(t('No contact card available'));
        }



        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t('The address book entry is recorded'));
            Cache::invalidateTags(['address_book_card']);

            $form_state->setRedirect('ek_address_book.view', array('abid' => $id));
        }
    }

}
