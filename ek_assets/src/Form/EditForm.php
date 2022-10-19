<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\EditForm.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $access = AccessCheck::GetCompanyByUser();
        $aid = [];
        $settings = new FinanceSettings();
        $chart = $settings->get('chart');

        $url = Url::fromRoute('ek_assets.list', [], [])->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t("<a href='@url'>List</a>", ['@url' => $url]),
        ];

        if ($id != '' && $id != 0) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_assets', 'a');
            $query->fields('a');
            $query->condition('id', $id);
            $query->condition('coid', $access, 'IN');
            $query->leftJoin('ek_assets_amortization', 'b', 'a.id = b.asid');
            $query->fields('b');
            $data = $query->execute()->fetchObject();

            $aid = AidList::listaid($data->coid, array($chart['assets']), 1);

            $current_amortization = Amortization::is_amortized($id);

            if ($this->moduleHandler->moduleExists('ek_hr')) {
                $check = Database::getConnection('external_db', 'external_db')
                        ->select('ek_hr_workforce')
                        ->fields('ek_hr_workforce', ['name', 'id'])
                        ->condition('id', $data->eid, '=')
                        ->execute()
                        ->fetchObject();
                if ($check->name) {
                    $data->eid = $check->id . ' | ' . $check->name;
                }
            }
        } else {
            $current_amortization = null;
        }

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id,
        ];

        $form['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#default_value' => isset($data->coid) ? $data->coid : null,
            '#title' => $this->t('Registered under'),
            '#required' => true,
            '#ajax' => [
                'callback' => [$this, 'get_category'],
                'wrapper' => 'category',
            ],
        ];

        $form["asset_name"] = [
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => isset($data->asset_name) ? $data->asset_name : null,
            '#maxlength' => 200,
            '#title' => $this->t('Name'),
            '#attributes' => ['placeholder' => $this->t('asset name')],
        ];

        $form["asset_brand"] = [
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => isset($data->asset_brand) ? $data->asset_brand : null,
            '#maxlength' => 255,
            '#title' => $this->t('Brand'),
            '#attributes' => ['placeholder' => $this->t('asset brand')],
        ];

        $form["asset_ref"] = [
            '#type' => 'textfield',
            '#size' => 40,
            '#default_value' => isset($data->asset_ref) ? $data->asset_ref : null,
            '#maxlength' => 255,
            '#title' => $this->t('Reference'),
            '#attributes' => ['placeholder' => $this->t('reference')],
        ];

        $form["unit"] = [
            '#type' => 'textfield',
            '#size' => 10,
            '#default_value' => isset($data->unit) ? $data->unit : null,
            '#maxlength' => 255,
            '#title' => $this->t('Quantity'),
            '#attributes' => ['placeholder' => $this->t('unit(s)')],
        ];

        $form["asset_comment"] = [
            '#type' => 'textarea',
            '#default_value' => isset($data->asset_comment) ? $data->asset_comment : null,
            '#description' => '',
            '#attributes' => ['placeholder' => $this->t('description')],
        ];

        //allocate asset to employee ID
        if ($this->moduleHandler->moduleExists('ek_hr')) {
            $form['e'] = [
                '#type' => 'details',
                '#title' => $this->t('HR link'),
                '#collapsible' => true,
                '#open' => true,
            ];
            $form['e']["eid"] = [
                '#type' => 'textfield',
                '#size' => 30,
                '#default_value' => isset($data->eid) ? $data->eid : null,
                '#maxlength' => 255,
                '#title' => $this->t('Allocation'),
                '#attributes' => ['placeholder' => $this->t('employee')],
                '#autocomplete_route_name' => 'ek_hr.employee.autocomplete',
            ];
            $form['e']['eid_global'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Allow global allocation'),
            ];
        }


        $query = "SELECT id,currency from {ek_currency} where active=:a order by currency";
        $currency = ['--' => '--'];
        $currency += Database::getConnection('external_db', 'external_db')
                ->query($query, array(':a' => 1))
                ->fetchAllKeyed();

        if ($form_state->getValue('coid')) {
            $aid = AidList::listaid($form_state->getValue('coid'), array($chart['assets']), 1);
        }

        $form["aid"] = [
            '#type' => 'select',
            '#size' => 1,
            '#required' => true,
            '#options' => $aid,
            '#disabled' => $current_amortization,
            '#title' => $this->t('Category'),
            '#default_value' => isset($data->aid) ? $data->aid : array(),
            '#prefix' => "<div id='category'  class='row'>",
            '#suffix' => '</div>',
        ];

        $form['currency'] = [
            '#type' => 'select',
            '#size' => 1,
            '#disabled' => $current_amortization,
            '#options' => array_combine($currency, $currency),
            '#default_value' => isset($data->currency) ? $data->currency : null,
            '#title' => $this->t('Currency'),
        ];

        $form["asset_value"] = [
            '#type' => 'textfield',
            '#size' => 25,
            '#disabled' => $current_amortization,
            '#default_value' => isset($data->asset_value) ? number_format($data->asset_value) : null,
            '#maxlength' => 30,
            '#title' => $this->t('Value'),
            '#attributes' => [
                'placeholder' => $this->t('value'),
                'class' => ['amount'],
                'onKeyPress' => "return(number_format(this,',','.', event))"
            ],
        ];

        $form['date_purchase'] = [
            '#type' => 'date',
            '#size' => 12,
            '#disabled' => $current_amortization,
            '#required' => true,
            '#default_value' => isset($data->date_purchase) ? $data->date_purchase : null,
            '#title' => $this->t('Date of purchase')
        ];


        $form['i'] = [
            '#type' => 'details',
            '#title' => $this->t('Attachments'),
            '#collapsible' => true,
            '#open' => (isset($data->asset_pic) || isset($data->asset_doc)) ? true : false,
        ];

        $form['i']['asset_pic'] = [
            '#type' => 'file',
            '#title' => $this->t('Upload picture'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => "</div>",
        ];

        /* current image if any */
        if (isset($data->asset_pic) && $data->asset_pic != '') {
            $image = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($data->asset_pic)
                    . "' target='_blank'><img class='thumbnail' src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($data->asset_pic) . "></a>";
            $form['i']['picture_delete'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Delete picture'),
                '#attributes' => ['onclick' => "jQuery('#currentPicture').toggleClass('delete');"],
                '#prefix' => "<div class='cell300 cellcenter'>",
            ];
            $form['i']["currentPicture"] = [
                '#markup' => "<p id='currentPicture' style='padding:2px;'>" . $image . "</p>",
                '#suffix' => '</div>',
            ];
        } else {
            $form['i']["currentPicture"] = [
                '#type' => "item",
                '#suffix' => "</div></div>",
            ];
        }

        $form['i']['asset_doc'] = [
            '#type' => 'file',
            '#title' => $this->t('Upload attachment'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => "</div>",
        ];

        /* current doc if any */
        if (isset($data->asset_doc) && $data->asset_doc != '') {
            $parts = explode("/", $data->asset_doc);
            $parts = array_reverse($parts);
            $name = $parts[0];
            $doc = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($data->asset_doc)
                    . "' target='_blank'><p>" . $name . "</p></a>";
            $form['i']['doc_delete'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Delete attachment'),
                '#attributes' => ['onclick' => "jQuery('#currentAttachment').toggleClass('delete');"],
                '#prefix' => "<div class='cell300 cellcenter'>",
            ];
            $form['i']["currentAttachment"] = [
                '#markup' => "<p id='currentAttachment' style='padding:2px;'>" . $doc . "</p>",
                '#suffix' => "</div></div></div>",
            ];
        } else {
            $form['i']["currentAttachment"] = [
                '#type' => "item",
                '#suffix' => "</div></div>",
            ];
        }


        $redirect = [0 => $this->t('view list'), 1 => $this->t('set amotization')];

        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['redirect'] = [
            '#type' => 'radios',
            '#title' => $this->t('Next'),
            '#default_value' => 0,
            '#options' => $redirect,
        ];

        $form['actions']['save'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];


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
            if ($form_state->getValue('eid') != '') {
                //check if employee exist

                $eid = explode('|', $form_state->getValue('eid'));
                $check = Database::getConnection('external_db', 'external_db')
                        ->select('ek_hr_workforce')
                        ->fields('ek_hr_workforce', ['name', 'company_id'])
                        ->condition('id', trim($eid[0]), '=')
                        ->execute()
                        ->fetchObject();
                $form_state->setValue('eid', null);

                if ($check->name == null) {
                    $form_state->setErrorByName('eid', $this->t('This employee does not exist in the records.'));
                } else {
                    $form_state->setValue('eid', $eid[0]);
                    if ($form_state->getValue('eid_global') == 0) {
                        //verify that the eid matches the company id
                        if ($check->company_id != $form_state->getValue('coid')) {
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
        $file = file_save_upload($field, $validators, false, 0);

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
        $file = file_save_upload($field, $validators, false, 0);
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
            'date_purchase' => $form_state->getValue('date_purchase'),
            'eid' => $form_state->getValue('eid'),
        );

        //attachments
        if ($form_state->getValue('picture_delete') == 1) {
            //delete existing file
            $query = "SELECT asset_pic FROM {ek_assets} WHERE id=:id";
            $path = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('for_id')))->fetchField();
            $pic = \Drupal::service('file_system')->realpath($path);
            unlink($pic);
            \Drupal::messenger()->addWarning(t("Asset image deleted"));
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets')
                    ->fields(array('asset_pic' => ''))
                    ->condition('id', $form_state->getValue('for_id'))
                    ->execute();
        }

        if ($form_state->getValue('doc_delete') == 1) {
            //delete existing file
            $query = "SELECT asset_doc FROM {ek_assets} WHERE id=:id";
            $path = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('for_id')))->fetchField();
            $pic = \Drupal::service('file_system')->realpath($path);
            unlink($pic);
            Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets')
                    ->fields(array('asset_doc' => ''))
                    ->condition('id', $form_state->getValue('for_id'))->execute();
            \Drupal::messenger()->addWarning(t("Attachment deleted"));
        }

        if ($form_state->getValue('asset_pic') != 0) {
            if ($file = $form_state->getValue('asset_pic')) {
                $dir = "private://assets/" . $form_state->getValue('coid');
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $picture = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);

                \Drupal::messenger()->addStatus(t("Picture uploaded"));
                $fields['asset_pic'] = $picture;
            }
        }

        if ($form_state->getValue('asset_doc') != 0) {
            if ($file = $form_state->getValue('asset_doc')) {
                $dir = "private://assets/" . $form_state->getValue('coid');
                \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
                $doc = \Drupal::service('file_system')->copy($file->getFileUri(), $dir);

                \Drupal::messenger()->addStatus(t("Attachment uploaded"));
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
            \Drupal\Core\Cache\Cache::invalidateTags(['ek.assets:' . $form_state->getValue('for_id')]);
        }
        \Drupal::messenger()->addStatus(t('Asset recorded'));
        \Drupal\Core\Cache\Cache::invalidateTags(['ek.assets_list']);

        switch ($form_state->getValue('redirect')) {
            case 0:
                $form_state->setRedirect('ek_assets.list');
                break;
            case 1:
                $form_state->setRedirect('ek_assets.set_amortization', ['id' => $ref]);
                break;
        }
    }

}
