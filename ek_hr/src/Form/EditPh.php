<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditPh.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to create or edit public holidays
 */
class EditPh extends FormBase {

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
        return 'hr_edit_ph';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : NULL,
            '#title' => t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? TRUE : FALSE,
            '#required' => TRUE,
        );

        if (($form_state->getValue('coid')) == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='coid']" => array('value' => ''),
                    ),
                ),
            );
        }

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);
            $query = "SELECT * FROM {ek_hr_workforce_ph} WHERE coid=:c ORDER by date";
            $a = array(':c' => $form_state->getValue('coid'));
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);


            $header = array(
                'description' => array(
                    'data' => $this->t('Description'),
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'date' => array(
                    'data' => $this->t('Date'),
                ),
                'del' => $this->t('Delete'),
            );

            $form['ph-table'] = array(
                '#tree' => TRUE,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 'ph-table'),
                '#empty' => $this->t('No public holiday'),
            );


            While ($r = $data->fetchObject()) {


                $id = $r->id;


                $form['description'] = array(
                    '#id' => 'desc-' . $id,
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 255,
                    '#default_value' => $r->description,
                    '#attributes' => array('placeholder' => t('description')),
                );

                $form['date'] = array(
                    '#id' => 'date-' . $id,
                    '#type' => 'textfield',
                    '#size' => 11,
                    '#maxlength' => 12,
                    '#default_value' => date('Y-n-j', strtotime($r->date)),
                    '#attributes' => array('placeholder' => array('Y-M-D')),
                );

                $form['del'] = array(
                    '#id' => 'del-' . $id,
                    '#type' => 'checkbox',
                    '#attributes' => array(
                        'title' => t('delete'),
                        'onclick' => "jQuery('#$id').toggleClass('delete');"
                    ),
                );


                $form['ph-table'][$id] = array(
                    'description' => &$form['description'],
                    'date' => &$form['date'],
                    'del' => &$form['del'],
                );

                $form['ph-table']['#rows'][] = array(
                    'data' => array(
                        array('data' => &$form['description']),
                        array('data' => &$form['date']),
                        array('data' => &$form['del']),
                    ),
                    'id' => array($id)
                );

                unset($form['description']);
                unset($form['date']);
                unset($form['del']);
            }

            $form['description'] = array(
                '#id' => 'newdescription',
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 255,
                '#default_value' => '',
                '#attributes' => array('placeholder' => t('New public holiday')),
            );

            $form['date'] = array(
                '#id' => 'newdate',
                '#type' => 'date',
                '#size' => 12,
                '#maxlength' => 12,
                '#default_value' => '',

            );


            $form['del'] = array(
                '#type' => 'hidden',
            );


            $form['ph-table']['new'] = array(
                'description' => &$form['description'],
                'date' => &$form['date'],
                'del' => &$form['del'],
            );

            $form['ph-table']['#rows'][] = array(
                'data' => array(
                    array('data' => &$form['description']),
                    array('data' => &$form['date']),
                    array('data' => &$form['del']),
                ),
                'id' => array($id)
            );

            unset($form['description']);
            unset($form['date']);
            unset($form['del']);



            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Save'),
                '#suffix' => ''
            );

            $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 1) {

            $form_state->set('step', 2);
            $form_state->setRebuild();
        }

        if ($form_state->get('step') == 3) {


            if ($form_state->getValue('date') <> '') {
                if ($form_state->getValue('description') == '') {
                    $form_state->setErrorByName('newdescription', $this->t('You need to enter a description'));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        if ($form_state->get('step') == 3) {

            $list = $form_state->getValue('ph-table');
            foreach ($list as $key => $value) {

                if ($key <> 'new') {
                    if ($value['del'] == 1) {


                        Database::getConnection('external_db', 'external_db')
                                ->delete('ek_hr_workforce_ph')
                                ->condition('id', $key)
                                ->execute();

                        \Drupal::messenger()->addWarning(t('Public Holiday \'@l\' deleted', ['@l' => $value['description']]));
                    } elseif ($value['description'] != NULL && $value['date'] != NULL) {

                        $fields = array(
                            'description' => Xss::filter($value['description']),
                            'date' => date('Y-n-j', strtotime($value['date']))
                        );

                        $update = Database::getConnection('external_db', 'external_db')
                                ->update('ek_hr_workforce_ph')
                                ->fields($fields)
                                ->condition('id', $key)
                                ->execute();
                    }
                } elseif ($value['description'] != '' && $value['date'] != '') {

                    $fields = array(
                        'coid' => $form_state->getValue('coid'),
                        'description' => Xss::filter($value['description']),
                        'date' => date('Y-n-j', strtotime($value['date']))
                    );

                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_hr_workforce_ph')
                            ->fields($fields)
                            ->execute();
                    
                    \Drupal::messenger()->addStatus(t('Public Holiday \'@l\' is created', ['@l' => $value['description']]));
                }
            }


            if ($update){
                \Drupal::messenger()->addStatus(t('Data updated'));
            }
        }//step 3
    }

}
