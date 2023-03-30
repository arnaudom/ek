<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\EditCompanyForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an company form.
 */
class EditCompanyForm extends FormBase
{

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler)
    {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_edit_company_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
        $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_country', 'c')
                        ->fields('c', ['id','name'])
                        ->condition('status', 1)
                        ->orderBy('name');
       
        $country = $query->execute()->fetchAllKeyed();

        if (isset($id) && !$id == null) {
            $form['for_id'] = array(
                '#type' => 'hidden',
                '#default_value' => $id,
            );

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_company', 'c')
                        ->condition('id', $id)
                        ->fields('c');
            $r = $query->execute()->fetchAssoc();
            $query = "SELECT * from {ek_company} WHERE id=:id";
        } else {
            $form['new_company'] = array(
                '#type' => 'hidden',
                '#default_value' => 1,
            );
        }

        $form['active'] = array(
            '#type' => 'select',
            '#options' => array(0 => $this->t('non active'), 1 => $this->t('active')),
            '#default_value' => isset($r['active']) ? $r['active'] : '1',
            '#required' => true,
        );

        $form['name'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#default_value' => isset($r['name']) ? $r['name'] : null,
            '#attributes' => array('placeholder' => $this->t('Name')),
            '#required' => true,
            '#description' => $this->t('name'),
            '#prefix' => "<div class='container-inline'>",
            '#attached' => array(
                'library' => array(
                    'ek_admin/ek_admin.script.sn',
                ),
            ),
        );

        $form['short'] = array(
            '#type' => 'textfield',
            '#id' => 'short_name',
            '#size' => 10,
            '#maxlength' => 5,
            '#required' => true,
            '#default_value' => isset($r['short']) ? $r['short'] : null,
            '#attributes' => array('placeholder' => $this->t('Short name')),
            '#suffix' => '</div>',
        );

        $form['alert'] = array(
            '#markup' => "<div id='alert'></div>",
        );

        $form['reg_number'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($r['reg_number']) ? $r['reg_number'] : null,
            '#attributes' => array('placeholder' => $this->t('registration number')),
            '#description' => $this->t('registration number'),
        );

        $form['address1'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#default_value' => isset($r['address1']) ? $r['address1'] : null,
            '#attributes' => array('placeholder' => $this->t('address line 1')),
            '#description' => $this->t('address line 1'),
        );

        $form['address2'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#default_value' => isset($r['address2']) ? $r['address2'] : null,
            '#attributes' => array('placeholder' => $this->t('address line 2')),
            '#description' => $this->t('address line 2'),
        );


        $form['city'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['city']) ? $r['city'] : null,
            '#attributes' => array('placeholder' => $this->t('city')),
            '#prefix' => "<div class='container-inline'>",
            '#description' => $this->t('city'),
        );

        $form['postcode'] = array(
            '#type' => 'textfield',
            '#size' => 10,
            '#maxlength' => 255,
            '#default_value' => isset($r['postcode']) ? $r['postcode'] : null,
            '#attributes' => array('placeholder' => $this->t('post code')),
            '#description' => $this->t('post code'),
            '#suffix' => "</div>",
        );
        
        $form['state'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 50,
            '#default_value' => isset($r['state']) ? $r['state'] : null,
            '#attributes' => array('placeholder' => $this->t('state')),
            '#prefix' => "<div class='container-inline'>",
            '#description' => $this->t('state'),
        );
        
        $form['country'] = array(
            '#type' => 'select',
            '#options' => array_combine($country, $country),
            '#default_value' => isset($r['country']) ? $r['country'] : null,
            '#description' => $this->t('country'),
            '#suffix' => "</div>",
        );

        $form['telephone'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['telephone']) ? $r['telephone'] : null,
            '#attributes' => array('placeholder' => $this->t('telephone')),
            '#description' => $this->t('telephone'),
        );

        $form['fax'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['fax']) ? $r['fax'] : null,
            '#attributes' => array('placeholder' => $this->t('fax')),
            '#description' => $this->t('fax'),
        );

        $form['mobile'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['mobile']) ? $r['mobile'] : null,
            '#attributes' => array('placeholder' => $this->t('mobile')),
            '#description' => $this->t('mobile phone'),
        );

        $form['email'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 255,
            '#default_value' => isset($r['email']) ? $r['email'] : null,
            '#attributes' => array('placeholder' => $this->t('email')),
            '#description' => $this->t('email'),
        );

        $form['contact'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#default_value' => isset($r['contact']) ? $r['contact'] : null,
            '#attributes' => array('placeholder' => $this->t('contact name')),
            '#description' => $this->t('contact'),
        );


        //correspondance address data

        $form['2'] = array(
            '#type' => 'details',
            '#title' => $this->t('Correspondance address'),
            '#collapsible' => true,
            '#collapsed' => false,
        );

        $form['2']['address3'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#default_value' => isset($r['address3']) ? $r['address3'] : null,
            '#attributes' => array('placeholder' => $this->t('address line 1')),
            '#description' => $this->t('address line 1'),
        );

        $form['2']['address4'] = array(
            '#type' => 'textfield',
            '#size' => 60,
            '#maxlength' => 255,
            '#default_value' => isset($r['address4']) ? $r['address4'] : null,
            '#attributes' => array('placeholder' => $this->t('address line 2')),
            '#description' => $this->t('address line 2'),
        );


        $form['2']['city2'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['city2']) ? $r['city2'] : null,
            '#attributes' => array('placeholder' => $this->t('city')),
            '#prefix' => "<div class='container-inline'>",
            '#description' => $this->t('city'),
        );

        $form['2']['postcode2'] = array(
            '#type' => 'textfield',
            '#size' => 10,
            '#maxlength' => 255,
            '#default_value' => isset($r['postcode2']) ? $r['postcode2'] : null,
            '#attributes' => array('placeholder' => $this->t('post code')),
            '#description' => $this->t('post code'),
            '#suffix' => "</div>",
        );

        $form['2']['state2'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 50,
            '#default_value' => isset($r['state']) ? $r['state'] : null,
            '#attributes' => array('placeholder' => $this->t('state')),
            '#prefix' => "<div class='container-inline'>",
            '#description' => $this->t('state'),
        );
        
        $form['2']['country2'] = array(
            '#type' => 'select',
            '#options' => array_combine($country, $country),
            '#default_value' => isset($r['country2']) ? $r['country2'] : null,
            '#description' => $this->t('country'),
            '#suffix' => "</div>",
        );

        $form['2']['telephone2'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['telephone2']) ? $r['telephone2'] : null,
            '#attributes' => array('placeholder' => $this->t('telephone')),
            '#description' => $this->t('telephone'),
        );

        $form['2']['fax2'] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#maxlength' => 255,
            '#default_value' => isset($r['fax2']) ? $r['fax2'] : null,
            '#attributes' => array('placeholder' => $this->t('fax')),
            '#description' => $this->t('fax'),
        );


        //Images data


        $form['i'] = array(
            '#type' => 'details',
            '#title' => $this->t('Images'),
            '#collapsible' => true,
            '#collapsed' => false,
        );
        if (null !== \Drupal\Core\StreamWrapper\PrivateStream::basePath()) {
            $form['i']['logo'] = array(
                '#type' => 'file',
                '#title' => $this->t('Upload a logo image'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => "</div>",
            );

            /* current image if any */
            if (isset($r['logo']) && $r['logo'] <> '') {
                $image = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($r['logo']) 
                        . "' target='_blank'><img class='thumbnail' src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($r['logo']) . "></a>";
                $form['i']['logo_delete'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('delete logo'),
                    '#attributes' => array('onclick' => "jQuery('#currentLogo').toggleClass( 'delete');"),
                    '#prefix' => "<div class='cell'>",
                );
                $form['i']["currentlogo"] = array(
                    '#markup' => "<p id='currentLogo' class = 'text-right'>" . $image . "</p>",
                    '#suffix' => "</div></div></div>",
                );
            } else {
                $form['i']["currentlogo"] = array(
                    '#type' => "item",
                    '#suffix' => "</div></div>",
                );
            }

            $form['i']['sign'] = array(
                '#type' => 'file',
                '#title' => $this->t('Upload a signature image'),
                '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
                '#suffix' => "</div>",
            );
            
            /* current image if any */
            if (isset($r['sign']) && $r['sign'] <> '') {
                $image = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($r['sign']) 
                        . "' target='_blank'><img class='thumbnail' src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($r['sign']) . "></a>";
                $form['i']['sign_delete'] = array(
                    '#type' => 'checkbox',
                    '#title' => $this->t('delete signature'),
                    '#attributes' => array('onclick' => "jQuery('#currentSign').toggleClass('delete');"),
                    '#prefix' => "<div class='cell'>",
                );
                $form['i']["currentsign"] = array(
                    '#markup' => "<p id='currentSign'  class = 'text-right'>" . $image . "</p>",
                    '#suffix' => "</div></div></div>",
                );
            } else {
                $form['i']["currentsign"] = array(
                    '#type' => "item",
                    '#suffix' => "</div></div>",
                );
            }
        } else {
            $form['i']['path'] = array(
                '#type' => 'item',
                '#markup' => $this->t("Set private data folder in <a href='@c'>configuration</a> before uploading files", ['@c' => '../../../admin/config/media/file-system']),
            );
        }

        // admin data
        $form['f'] = array(
            '#type' => 'details',
            '#title' => $this->t('Other settings'),
            '#collapsible' => true,
            '#collapsed' => false,
        );
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            // choice to load standard account or use othe company accunts


            if (!isset($id)) {
                // new company
                $option = [0 => $this->t('Default standard')];
                $t = $this->t('Copy from') . ":";
                $option[$t] = Database::getConnection('external_db', 'external_db')
                        ->query("SELECT id,name from {ek_company} order by name")
                        ->fetchAllKeyed();

                $form['f']['use_chart'] = array(
                    '#type' => 'select',
                    '#title' => $this->t('Select chart of accounts'),
                    '#options' => $option,
                    '#default_value' => null,
                    '#required' => true,
                    '#description' => $this->t('chart selection from other company will be copied into new entity'),
                );
            }
        }
        if (!$this->moduleHandler->moduleExists('ek_finance')) {
            $form['f']['accounts_year'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#maxlength' => 4,
                '#default_value' => isset($r['accounts_year']) ? $r['accounts_year'] : null,
                '#attributes' => array('placeholder' => $this->t('year')),
                '#description' => $this->t('financial year'),
            );

            $form['f']['accounts_month'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#maxlength' => 2,
                '#default_value' => isset($r['accounts_month']) ? $r['accounts_month'] : null,
                '#attributes' => array('placeholder' => $this->t('month. ex.12')),
                '#description' => $this->t('financial month'),
            );
        } else {
            $settings = new \Drupal\ek_admin\CompanySettings($id);
            $form['f']['accounts_year'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#disabled' => true,
                '#maxlength' => 4,
                '#default_value' => $settings->get('fiscal_year'),
                '#description' => $this->t('financial year'),
            );

            $form['f']['accounts_month'] = array(
                '#type' => 'textfield',
                '#size' => 6,
                '#disabled' => true,
                '#maxlength' => 2,
                '#default_value' => $settings->get('fiscal_month'),
                '#description' => $this->t('financial month'),
            );
        }
        $form['f']['itax_no'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($r['itax_no']) ? $r['itax_no'] : null,
            '#attributes' => array('placeholder' => $this->t('tax no.')),
            '#description' => $this->t('income tax no.'),
        );

        $form['f']['pension_no'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($r['pension_no']) ? $r['pension_no'] : null,
            '#attributes' => array('placeholder' => $this->t('pension no.')),
            '#description' => $this->t('pension no.'),
        );

        $form['f']['social_no'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($r['social_no']) ? $r['social_no'] : null,
            '#attributes' => array('placeholder' => $this->t('social no.')),
            '#description' => $this->t('social security no.'),
        );

        $form['f']['vat_no'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#maxlength' => 255,
            '#default_value' => isset($r['vat_no']) ? $r['vat_no'] : null,
            '#attributes' => array('placeholder' => $this->t('vat ref. no.')),
            '#description' => $this->t('vat ref. no.'),
        );


        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

        return $form;
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);

        if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
            $form_state->setErrorByName('email', $this->t('Invalid email'));
        }
        if ($form_state->getValue('accounts_year') <> '') {
            if (!is_numeric($form_state->getValue('accounts_year')) || !preg_match('/\d{4}/', $form_state->getValue('accounts_year'))) {
                $form_state->setErrorByName('accounts_year', $this->t('Financial year is wrong'));
            }
        }
        if ($form_state->getValue('accounts_month') <> '') {
            if (!is_numeric($form_state->getValue('accounts_month')) || !preg_match('/\d/', $form_state->getValue('accounts_month'))) {
                $form_state->setErrorByName('accounts_month', $this->t('Financial month is wrong'));
            }
        }

        $validators = array('file_validate_is_image' => array());
        $destination =  "private://admin/company" . $form_state->getValue('for_id') . "/images";
        \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        //LOGO
        $field = "logo";
        // Check for a new uploaded logo.
        $file = file_save_upload($field, $validators, $destination, 0);

        if (isset($file)) {
            $res = file_validate_image_resolution($file, '300x300', '100x100');
            // File upload was attempted.
            if ($file) {
                // Put the temporary file in form_values so we can save it on submit.
                $form_state->setValue($field, $file);
            } else {
                // File upload failed.
                $form_state->setErrorByName($field, $this->t('Logo could not be uploaded'));
            }
        } else {
            $form_state->setValue($field, 0);
        }

        //SIGN
        $field = "sign";
        // Check for a new uploaded signature.
        $file = file_save_upload($field, $validators, $destination, 0);

        if (isset($file)) {
            $res = file_validate_image_resolution($file, '300x300', '100x100');
            // File upload was attempted.
            if ($file) {
                // Put the temporary file in form_values so we can save it on submit.
                $form_state->setValue($field, $file);
            } else {
                // File upload failed.
                $form_state->setErrorByName($field, $this->t('Signature could not be uploaded'));
            }
        } else {
            $form_state->setValue($field, 0);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $fields = array(
            'name' => $form_state->getValue('name'),
            'short' => $form_state->getValue('short'),
            'reg_number' => $form_state->getValue('reg_number'),
            'address1' => $form_state->getValue('address1'),
            'address2' => $form_state->getValue('address2'),
            'address3' => $form_state->getValue('address3'),
            'address4' => $form_state->getValue('address4'),
            'city' => $form_state->getValue('city'),
            'city2' => $form_state->getValue('city2'),
            'state' => $form_state->getValue('state'),
            'state2' => $form_state->getValue('state2'),
            'postcode' => $form_state->getValue('postcode'),
            'postcode2' => $form_state->getValue('postcode2'),
            'country' => $form_state->getValue('country'),
            'country2' => $form_state->getValue('country2'),
            'telephone' => $form_state->getValue('telephone'),
            'telephone2' => $form_state->getValue('telephone2'),
            'fax' => $form_state->getValue('fax'),
            'fax2' => $form_state->getValue('fax2'),
            'email' => $form_state->getValue('email'),
            'contact' => $form_state->getValue('contact'),
            'mobile' => $form_state->getValue('mobile'),
            'accounts_year' => $form_state->getValue('accounts_year'),
            'accounts_month' => $form_state->getValue('accounts_month'),
            'active' => $form_state->getValue('active'),
            'itax_no' => $form_state->getValue('itax_no'),
            'pension_no' => $form_state->getValue('pension_no'),
            'social_no' => $form_state->getValue('social_no'),
            'vat_no' => $form_state->getValue('vat_no'),
        );

        if ($form_state->getValue('for_id') == '') {
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_company')
                    ->fields($fields)
                    ->execute();
            $id = $insert;
        } else {

            //update existing
            $update = Database::getConnection('external_db', 'external_db')->update('ek_company')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->fields($fields)
                    ->execute();

            $id = $form_state->getValue('for_id');
        }

        if ($this->moduleHandler->moduleExists('ek_finance') && $form_state->getValue('for_id') == '') {
            if ($form_state->getValue('use_chart') == 0) {
                //load standard accounts
                $file = drupal_get_path('module', 'ek_finance') . '/ek_standard_accounts.sql';
                $query = file_get_contents($file);
                $acc = Database::getConnection('external_db', 'external_db')->query($query);
                $balance_date = date('Y') . '-01-01';
                $acc = Database::getConnection('external_db', 'external_db')->update('ek_accounts')
                        ->condition('coid', 'x')
                        ->fields(array('coid' => $id, 'balance_date' => $balance_date))
                        ->execute();
            } else {
                //copy chart from other account
                $query = "SELECT * from {ek_accounts} WHERE coid=:c ORDER by aid";
                $acc = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => $form_state->getValue('use_chart')]);
                $date = date('Y') . '-01-01';
                while ($a = $acc->fetchObject()) {
                    $fields = [
                        'aid' => $a->aid,
                        'aname' => $a->aname,
                        'atype' => $a->atype,
                        'astatus' => $a->astatus,
                        'coid' => $id,
                        'link' => '',
                        'balance' => 0,
                        'balance_base' => 0,
                        'balance_date' => $date,
                    ];
                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_accounts')
                            ->fields($fields)
                            ->execute();
                }
            }
        }

        // images
        if ($form_state->getValue('logo_delete') == 1) {
            //delete existing file
            $query = "SELECT logo from {ek_company} where id=:id";
            $path = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $id))->fetchField();

            \Drupal::service('file_system')->delete($path);
            \Drupal::messenger()->addWarning(t("Logo image deleted"));
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_company')->fields(array('logo' => ''))->condition('id', $id)->execute();
        }

        if ($form_state->getValue('sign_delete') == 1) {
            //delete existing file
            $query = "SELECT sign from {ek_company} where id=:id";
            $path = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $id))->fetchField();

            \Drupal::service('file_system')->delete($path);
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_company')->fields(array('sign' => ''))->condition('id', $id)->execute();
            \Drupal::messenger()->addWarning(t("Signature image deleted"));
        }

        if (!$form_state->getValue('logo') == 0) {
            if ($file = $form_state->getValue('logo')) {
                $file->setPermanent();
                $file->save();
                $logo = $file->getFileUri();
                Database::getConnection('external_db', 'external_db')
                        ->update('ek_company')
                        ->fields(array('logo' => $logo))
                        ->condition('id', $id)->execute();
                \Drupal::messenger()->addStatus(t("New logo image uploaded"));
            }
        }

        if (!$form_state->getValue('sign') == 0) {
            if ($file = $form_state->getValue('sign')) {
                $file->setPermanent();
                $file->save();
                $sign = $file->getFileUri();
                Database::getConnection('external_db', 'external_db')
                        ->update('ek_company')
                        ->fields(array('sign' => $sign))
                        ->condition('id', $id)->execute();
                \Drupal::messenger()->addStatus(t("New signature image uploaded"));
            }
        }


        //insert default access for new
        if ($form_state->getValue('for_id') == '') {
            if (\Drupal::currentUser()->id() == 1) {
                $access = array('1');
            } else {
                $access = array('1', \Drupal::currentUser()->id());
            }
            $access = serialize(implode(",", $access));

            Database::getConnection('external_db', 'external_db')
                    ->update('ek_company')
                    ->fields(array('access' => $access))
                    ->condition('id', $id)
                    ->execute();
        }

        if (isset($insert) || isset($update)) {
            \Drupal::messenger()->addStatus(t("The company is recorded"));
            if ($_SESSION['install'] == 1) {
                unset($_SESSION['install']);
                $form_state->setRedirect('ek_admin.main');
            } else {
                $form_state->setRedirect('ek_admin.company.list');
            }
        }
    }
}
