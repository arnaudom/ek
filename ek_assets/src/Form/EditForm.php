<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\EditForm.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;
use Drupal\ek_assets\Amortization;

/**
 * Provides a form to create and edit assets.
 */
class EditForm extends FormBase {

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
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_edit_asset';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $aid = array();
        $settings = new FinanceSettings();
        $chart = $settings->get('chart');

        $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => t("<a href='@url'>List</a>", ['@url' => $url]),
        );

        if ($id != '' && $id != 0) {

            $query = "SELECT * from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                    . "ON a.id = b.asid "
                    . "WHERE id=:id "
                    . "AND FIND_IN_SET (coid, :c)";
            $a = array(
                ':id' => $id,
                ':c' => $company,
            );

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchObject();
            $aid = AidList::listaid($data->coid, array($chart['assets']), 1);

            $current_amortization = Amortization::is_amortized($id);
            
            if ($this->moduleHandler->moduleExists('ek_hr')) {
                $check = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce')
                ->fields('ek_hr_workforce', ['name', 'id'])
                ->condition('id', $data->eid, '=')
                ->execute()
                ->fetchObject();
                if($check->name){
                    $data->eid = $check->id . ' | ' . $check->name;
                }
            }
        }

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id,
        );

        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => isset($data->coid) ? $data->coid : null,
            '#title' => t('Registered under'),
            '#required' => TRUE,
            '#ajax' => array(
                'callback' => array($this, 'get_category'),
                'wrapper' => 'category',
            ),
        );

        $form["asset_name"] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => $data->asset_name,
            '#maxlength' => 200,
            '#title' => t('Name'),
            '#attributes' => array('placeholder' => t('asset name')),
        );

        $form["asset_brand"] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => $data->asset_brand,
            '#maxlength' => 255,
            '#title' => t('Brand'),
            '#attributes' => array('placeholder' => t('asset brand')),
        );

        $form["asset_ref"] = array(
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => $data->asset_ref,
            '#maxlength' => 255,
            '#title' => t('Reference'),
            '#attributes' => array('placeholder' => t('reference')),
        );

        $form["unit"] = array(
            '#type' => 'textfield',
            '#size' => 10,
            '#default_value' => $data->unit,
            '#maxlength' => 255,
            '#title' => t('Quantity'),
            '#attributes' => array('placeholder' => t('unit(s)')),
        );

        $form["asset_comment"] = array(
            '#type' => 'textarea',
            '#default_value' => $data->asset_comment,
            '#description' => '',
            '#attributes' => array('placeholder' => t('description')),
        );

        //allocate asset to employee ID
        if ($this->moduleHandler->moduleExists('ek_hr')) {
            $form['e'] = array(
                '#type' => 'details',
                '#title' => t('HR link'),
                '#collapsible' => TRUE,
                '#open' => TRUE,
            );
            $form['e']["eid"] = array(
                '#type' => 'textfield',
                '#size' => 30,
                '#default_value' => $data->eid,
                '#maxlength' => 255,
                '#title' => t('Allocation'),
                '#attributes' => array('placeholder' => t('emloyee')),
                '#autocomplete_route_name' => 'ek_hr.employee.autocomplete',
            );
            $form['e']['eid_global'] = array(
                '#type' => 'checkbox',
                '#title' => t('Allow global allocation'),
                
            );
        }


        $query = "SELECT id,currency from {ek_currency} where active=:a order by currency";
        $currency = array('--' => '--');
        $currency += Database::getConnection('external_db', 'external_db')
                ->query($query, array(':a' => 1))
                ->fetchAllKeyed();

        if ($form_state->getValue('coid')) {
            $aid = AidList::listaid($form_state->getValue('coid'), array($chart['assets']), 1);
        }

        $form["aid"] = array(
            '#type' => 'select',
            '#size' => 1,
            '#required' => TRUE,
            '#options' => $aid,
            '#disabled' => $current_amortization,
            '#title' => t('Category'),
            '#default_value' => isset($data->aid) ? $data->aid : array(),
            '#attributes' => array(),
            '#prefix' => "<div id='category'  class='row'>",
            '#suffix' => '</div>',
        );

        $form['currency'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#disabled' => $current_amortization,
            '#options' => array_combine($currency, $currency),
            '#default_value' => $data->currency,
            '#title' => t('Currency'),
        );

        $form["asset_value"] = array(
            '#type' => 'textfield',
            '#size' => 25,
            '#disabled' => $current_amortization,
            '#default_value' => isset($data->asset_value) ? number_format($data->asset_value) : Null,
            '#maxlength' => 30,
            '#title' => t('Value'),
            '#attributes' => array(
                'placeholder' => t('value'),
                'class' => array('amount'),
                'onKeyPress' => "return(number_format(this,',','.', event))"
            ),
        );

        $form['date_purchase'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#disabled' => $current_amortization,
            '#required' => TRUE,
            '#default_value' => $data->date_purchase,
            '#title' => t('Date of purchase')
        );


        $form['i'] = array(
            '#type' => 'details',
            '#title' => t('Attachments'),
            '#collapsible' => TRUE,
            '#open' => ($data->asset_pic || $data->asset_doc) ? TRUE : FALSE,
        );

        $form['i']['asset_pic'] = array(
            '#type' => 'file',
            '#title' => t('Upload picture'),
            '#maxlength' => 50,
        );

        /* current image if any */
        if ($data->asset_pic != '') {
            $image = "<a href='" . file_create_url($data->asset_pic)
                    . "' target='_blank'><img class='thumbnail' src=" . file_create_url($data->asset_pic) . "></a>";
            $form['i']['picture_delete'] = array(
                '#type' => 'checkbox',
                '#title' => t('Delete picture'),
                '#attributes' => array('onclick' => "jQuery('#currentl').toggleClass( 'delete');"),
                '#prefix' => "<div class='container-inline'>",
            );
            $form['i']["currentpicture"] = array(
                '#markup' => "<p id='currentl'style='padding:2px;'>" . $image . "</p>",
                '#suffix' => '</div>',
            );
        }

        $form['i']['asset_doc'] = array(
            '#type' => 'file',
            '#title' => t('Upload attachment'),
            '#maxlength' => 50,
        );

        /* current doc if any */
        if ($data->asset_doc != '') {
            $parts = explode("/", $data->asset_doc);
            $parts = array_reverse($parts);
            $name = $parts[0];
            $doc = "<a href='" . file_create_url($data->asset_doc)
                    . "' target='_blank'><p>" . $name . "</p></a>";
            $form['i']['doc_delete'] = array(
                '#type' => 'checkbox',
                '#title' => t('Delete attachment'),
                '#attributes' => array('onclick' => "jQuery('#currents').toggleClass( 'delete');"),
                '#prefix' => "<div class='container-inline'>",
            );
            $form['i']["currentattachment"] = array(
                '#markup' => "<p id='currents' style='padding:2px;'>" . $doc . "</p>",
                '#suffix' => '</div>',
            );
        }


        $redirect = array(0 => t('view list'), 1 => t('set amotization'));

        $form['actions'] = array(
            '#type' => 'actions',
                //'#attributes' => array('class' => array('container-inline')),
        );
        $form['actions']['redirect'] = array(
            '#type' => 'radios',
            '#title' => t('Next'),
            '#default_value' => 0,
            '#options' => $redirect,
        );

        $form['actions']['save'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );


        return $form;
    }

    /**
     * callback functions
     */
    public function get_category(array &$form, FormStateInterface $form_state) {

        //return aid list
        return $form['aid'];
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if (!is_numeric($form_state->getValue('unit'))) {
            $form_state->setErrorByName('unit', $this->t('Quantity must be a numeric value'));
        }

        $value = str_replace(',', '', $form_state->getValue("asset_value"));
        if (!is_numeric($value)) {
            $form_state->setErrorByName('asset_value', $this->t('Non numeric value inserted: @v', ['@v' => $value]));
        }
        
        //HR
        if ($this->moduleHandler->moduleExists('ek_hr')) {
            if($form_state->getValue('eid') != '') {
              //check if employee exist
                
                $eid = explode('|', $form_state->getValue('eid'));
                $check = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce')
                ->fields('ek_hr_workforce', ['name', 'company_id'])
                ->condition('id', trim($eid[0]), '=')
                ->execute()
                ->fetchObject();
                $form_state->setValue('eid', NULL);
                
                if($check->name == NULL) {
                    $form_state->setErrorByName('eid', $this->t('This employee does not exist in the records.'));
                    
                } else {
                    $form_state->setValue('eid', $eid[0]); 
                        if($form_state->getValue('eid_global') == 0) {
                            //verify that the eid matches the company id
                            if($check->company_id != $form_state->getValue('coid')) {
                                $form_state->setErrorByName('eid', $this->t('This employee is not working for the company the asset is attached to.'
                                        . 'Check global allocation box if you want to allocate this asset.'));

                            } 
                        }
                }
                
            } 
        }    
        
        
        //Attachments
        $validators = array('file_validate_is_image' => array());
        //Picture

        $field = "asset_pic";

        // Check for a new uploaded logo.
        $file = file_save_upload($field, $validators, FALSE, 0);

        if (isset($file)) {
            $res = file_validate_image_resolution($file, '800x800', '100x100');
            // File upload was attempted.
            if ($file) {
                // Put the temporary file in form_values so we can save it on submit.
                $form_state->setValue($field, $file);
            } else {
                // File upload failed.
                $form_state->setErrorByName($field, $this->t('Picture could not be uploaded'));
            }
        } else {
            $form_state->setValue($field, 0);
        }

        //Doc
        $field = "asset_doc";
        $extensions = 'png gif jpg jpeg bmp txt doc docx xls xlsx odt ods odp pdf ppt pptx sxc rar rtf tiff zip';
        $validators = array('file_validate_extensions' => array($extensions));
        // Check for a new uploaded logo.
        $file = file_save_upload($field, $validators, FALSE, 0);
        if (isset($file)) {
            // File upload was attempted.
            if ($file) {
                // Put the temporary file in form_values so we can save it on submit.
                $form_state->setValue($field, $file);
            } else {
                // File upload failed.
                $form_state->setErrorByName($field, $this->t('Document could not be uploaded'));
            }
        } else {
            $form_state->setValue($field, 0);
        }
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        $asset_value = str_replace(',', '', $form_state->getValue("asset_value"));

        $fields = array(
            'asset_name' => Xss::filter($form_state->getValue('asset_name')),
            'asset_brand' => Xss::filter($form_state->getValue('asset_brand')),
            'asset_ref' => Xss::filter($form_state->getValue('asset_ref')),
            'coid' => $form_state->getValue('coid'),
            'unit' => $form_state->getValue('unit'),
            'aid' => $form_state->getValue('aid'),
            'asset_comment' => Xss::filter($form_state->getValue('asset_comment')),
            'asset_value' => $asset_value,
            'currency' => $form_state->getValue('currency'),
            'date_purchase' => Xss::filter($form_state->getValue('date_purchase')),
            'eid' => $form_state->getValue('eid'),
        );

        //attachments
        if ($form_state->getValue('picture_delete') == 1) {
            //delete existing file
            $query = "SELECT asset_pic FROM {ek_assets} WHERE id=:id";
            $filename = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('for_id')))->fetchField();
            $pic = drupal_realpath($filename);
            unlink($pic);
            drupal_set_message(t("Asset image deleted"), 'warning');
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets')
                    ->fields(array('asset_pic' => ''))
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        }

        if ($form_state->getValue('doc_delete') == 1) {
            //delete existing file
            $query = "SELECT asset_doc FROM {ek_assets} WHERE id=:id";
            $filename = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('for_id')))->fetchField();
            $pic = drupal_realpath($filename);
            unlink($pic);
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets')
                    ->fields(array('asset_doc' => ''))
                    ->condition('id', $form_state->getValue('for_id'))->execute();
            drupal_set_message(t("Attachment deleted"), 'warning');
        }

        if ($form_state->getValue('asset_pic') != 0) {
            if ($file = $form_state->getValue('asset_pic')) {

                $dir = "private://assets/" . $form_state->getValue('coid');
                file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                $picture = file_unmanaged_copy($file->getFileUri(), $dir);

                drupal_set_message(t("Picture uploaded"), 'status');
                $fields['asset_pic'] = $picture;
            
            }
        }

        if ($form_state->getValue('asset_doc') != 0) {
            if ($file = $form_state->getValue('asset_doc')) {

                $dir = "private://assets/" . $form_state->getValue('coid');
                file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
                $doc = file_unmanaged_copy($file->getFileUri(), $dir);

                drupal_set_message(t("Attachment uploaded"), 'status');
                $fields['asset_doc'] = $doc;
            }
        }
        
        
        
        if ($form_state->getValue('for_id') == 0) {
            $ref = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_assets')
                            ->fields($fields)->execute();
            $fields = array(
                'asid' => $ref,
            );
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_assets_amortization')
                    ->fields($fields)->execute();
        } else {
            //update existing
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets')
                    ->condition('id', $form_state->getValue('for_id'))
                    ->fields($fields)
                    ->execute();
            $ref = $form_state->getValue('for_id');
        }

        drupal_set_message(t('Asset recorded'), 'status');

        switch ($form_state->getValue('redirect')) {
            case 0 :
                $form_state->setRedirect('ek_assets.list');
                break;
            case 1 :
                $form_state->setRedirect('ek_assets.set_amortization', ['id' => $ref]);
                break;
        }
    }

}
