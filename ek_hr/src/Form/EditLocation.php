<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditLocation.
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
 * Provides a form to create or edit locations
 */
class EditLocation extends FormBase {

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
        return 'location_edit';
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
            $query = "SELECT * from {ek_hr_location} where coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);


            $header = array(
                'name' => array(
                    'data' => $this->t('Name'),
                    'field' => 'name',
                    'sort' => 'asc',
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'description' => array(
                    'data' => $this->t('Description'),
                ),
                'finance' => array(
                    'data' => $this->t('Turnover'),
                ),
                'del' => $this->t('Delete'),
            );

            $form['l-table'] = array(
                '#tree' => TRUE,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 'l-table'),
                '#empty' => $this->t('No location'),
            );


            While ($r = $data->fetchObject()) {


                $id = $r->id;

                $form['name'] = array(
                    '#id' => 'name-' . $id,
                    '#type' => 'textfield',
                    '#size' => 25,
                    '#maxlength' => 255,
                    '#default_value' => $r->location,
                    '#attributes' => array('placeholder' => t('location name')),
                    '#required' => TRUE,
                );

                $form['desc'] = array(
                    '#id' => 'desc-' . $id,
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => $r->description,
                    '#attributes' => array('placeholder' => t('location description')),
                );

                $form['turnover'] = array(
                    '#id' => 'turnover-' . $id,
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#maxlength' => 255,
                    '#default_value' => number_format($r->turnover, 2),
                    '#attributes' => array('class' => array('amount')),
                );

                $form['del'] = array(
                    '#id' => 'del-' . $id,
                    '#type' => 'checkbox',
                    '#attributes' => array(
                        'title' => t('delete'),
                        'onclick' => "jQuery('#$id').toggleClass('delete');"
                    ),
                );


                $form['l-table'][$id] = array(
                    'name' => &$form['name'],
                    'description' => &$form['desc'],
                    'turnover' => &$form['turnover'],
                    'del' => &$form['del'],
                );

                $form['l-table']['#rows'][] = array(
                    'data' => array(
                        array('data' => &$form['name']),
                        array('data' => &$form['desc']),
                        array('data' => &$form['turnover']),
                        array('data' => &$form['del']),
                    ),
                    'id' => array($id)
                );

                unset($form['name']);
                unset($form['desc']);
                unset($form['turnover']);
                unset($form['del']);
            }

            $form['name'] = array(
                '#id' => 'newname',
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 255,
                '#default_value' => '',
                '#attributes' => array('placeholder' => t('New location')),
            );

            $form['desc'] = array(
                '#id' => 'newdesc',
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => '',
                '#attributes' => array('placeholder' => t('New location description')),
            );

            $form['turnover'] = array(
                '#id' => 'newturnover',
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 255,
                '#default_value' => '',
                '#attributes' => array('class' => array('amount')),
            );

            $form['del'] = array(
                '#type' => 'hidden',
            );


            $form['l-table']['new'] = array(
                'name' => &$form['name'],
                'description' => &$form['desc'],
                'turnover' => &$form['turnover'],
                'del' => &$form['del'],
            );

            $form['l-table']['#rows'][] = array(
                'data' => array(
                    array('data' => &$form['name']),
                    array('data' => &$form['desc']),
                    array('data' => &$form['turnover']),
                    array('data' => &$form['del']),
                ),
                'id' => array($id)
            );

            unset($form['name']);
            unset($form['desc']);
            unset($form['turnover']);
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


            if ($form_state->getValue('description') <> '') {
                if ($form_state->getValue('name') == '') {
                    $form_state->setErrorByName('newname', $this->t('You need to enter a name'));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



        if ($form_state->get('step') == 3) {

            $list = $form_state->getValue('l-table');
            foreach ($list as $key => $value) {

                if ($key <> 'new') {
                    if ($value['del'] == 1) {

                        $query = "SELECT count(id) from {ek_hr_workforce} WHERE location =:l";
                        $a = array(':l' => $value['name']);
                        $count = Database::getConnection('external_db', 'external_db')
                                ->query($query, $a)
                                ->fetchField();

                        if ($count > 0) {
                            \Drupal::messenger()->addWarning(t('Location \'@l\' cannot be deleted because it is used.', ['@l' => $value['name']]));
                        } else {
                            Database::getConnection('external_db', 'external_db')
                                    ->delete('ek_hr_location')
                                    ->condition('id', $key)
                                    ->execute();
                            \Drupal::messenger()->addWarning(t('Location \'@l\'  deleted', ['@l' => $value['name']]));
                        }
                    } else {

                        if (!is_numeric($value['turnover'])) {
                            $value['turnover'] = 0;
                        }

                        $query = "SELECT count(id) from {ek_hr_workforce} WHERE location =:l";
                        $a = array(':l' => $value['name']);
                        $count = Database::getConnection('external_db', 'external_db')
                                ->query($query, $a)
                                ->fetchField();

                        if ($count > 0) { //In use only update description or turnover
                            $fields = array(
                                'description' => Xss::filter($value['description']),
                                'turnover' => $value['turnover']
                            );
                        } else { //not in use can update all data               
                            $fields = array(
                                'location' => Xss::filter($value['name']),
                                'description' => Xss::filter($value['description']),
                                'turnover' => $value['turnover']
                            );
                        }


                        Database::getConnection('external_db', 'external_db')
                                ->update('ek_hr_location')
                                ->fields($fields)
                                ->condition('id', $key)
                                ->execute();
                    }
                } else {
                    if ($value['name'] <> '') {
                        if (!is_numeric($value['turnover'])) {
                            $value['turnover'] = 0;
                        }
                        $fields = array(
                            'coid' => $form_state->getValue('coid'),
                            'location' => Xss::filter($value['name']),
                            'description' => Xss::filter($value['description']),
                            'turnover' => $value['turnover']
                        );

                        Database::getConnection('external_db', 'external_db')
                                ->insert('ek_hr_location')
                                ->fields($fields)
                                ->execute();
                        
                        \Drupal::messenger()->addStatus(t('Location \'@l\' is created', ['@l' => $value['name']]));
                    }
                }
            }

            \Drupal::messenger()->addStatus(t('Data updated'));
        }//step 3
    }

}
