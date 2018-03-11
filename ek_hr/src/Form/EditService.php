<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditService.
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
 * Provides a form to create or edit HR services
 */
class EditService extends FormBase {

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
        return 'hr_service_edit';
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

        if ($form_state->getValue('coid') == '') {
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

            $query = "SELECT * from {ek_hr_service} where coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $data = Database::getConnection('external_db', 'external_db')->query($query, $a);

            $query = "SELECT id,name from {ek_hr_workforce} WHERE company_id=:c";
            $employee = array(0 => t('none'));
            $employee += Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchAllKeyed();

            $header = array(
                'name' => array(
                    'data' => $this->t('Name'),
                    'field' => 'name',
                    'sort' => 'asc',
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'eid' => array(
                    'data' => $this->t('Manager'),
                ),
                'del' => $this->t('Delete'),
            );

            $form['s_table'] = array(
                '#tree' => TRUE,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 's_table'),
                '#empty' => $this->t('No service'),
            );


            While ($r = $data->fetchObject()) {


                $id = $r->sid;

                $form['name'] = array(
                    '#id' => 'name-' . $id,
                    '#type' => 'textfield',
                    '#size' => 25,
                    '#maxlength' => 255,
                    '#default_value' => $r->service_name,
                    '#attributes' => array('placeholder' => t('service name')),
                    '#required' => TRUE,
                );

                $form['eid'] = array(
                    '#id' => 'eid-' . $id,
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $employee,
                    '#default_value' => $r->eid,
                );

                $form['del'] = array(
                    '#id' => 'del-' . $id,
                    '#type' => 'checkbox',
                    '#attributes' => array(
                        'title' => t('delete'),
                        'onclick' => "jQuery('#$id').toggleClass('delete');"
                    ),
                );


                $form['s_table'][$id] = array(
                    'name' => &$form['name'],
                    'eid' => &$form['eid'],
                    'del' => &$form['del'],
                );

                $form['s_table']['#rows'][] = array(
                    'data' => array(
                        array('data' => &$form['name']),
                        array('data' => &$form['eid']),
                        array('data' => &$form['del']),
                    ),
                    'id' => array($id)
                );

                unset($form['name']);
                unset($form['eid']);
                unset($form['del']);
            }

            $form['name'] = array(
                '#id' => 'newname',
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 255,
                '#default_value' => '',
                '#attributes' => array('placeholder' => t('New service')),
            );

            $form['eid'] = array(
                '#id' => 'neweid',
                '#type' => 'select',
                '#size' => 1,
                '#options' => $employee,
                '#default_value' => '',
            );


            $form['del'] = array(
                '#type' => 'hidden',
            );


            $form['s_table']['new'] = array(
                'name' => &$form['name'],
                'eid' => &$form['eid'],
                'del' => &$form['del'],
            );

            $form['s_table']['#rows'][] = array(
                'data' => array(
                    array('data' => &$form['name']),
                    array('data' => &$form['eid']),
                    array('data' => &$form['del']),
                ),
                'id' => array($id)
            );

            unset($form['name']);
            unset($form['eid']);
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

        if ($form_state->get('step') == 2) {
            
        }

        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



        if ($form_state->get('step') == 3) {

            foreach ($form_state->getValue('s_table') as $key => $value) {

                if ($key <> 'new') {
                    if ($value['del'] == 1) {

                        $query = "SELECT count(id) from {ek_hr_workforce} WHERE service =:s";
                        $a = array(':s' => $key);
                        $count = Database::getConnection('external_db', 'external_db')
                                ->query($query, $a)
                                ->fetchField();

                        if ($count > 0) {
                            \Drupal::messenger()->addWarning(t('Service \'@l\' cannot be deleted because it is used.', ['@l' => $value['name']]));
                        } else {
                            Database::getConnection('external_db', 'external_db')
                                    ->delete('ek_hr_location')
                                    ->condition('id', $key)
                                    ->execute();
                            \Drupal::messenger()->addWarning(t('Service \'@l\' has been deleted', ['@l' => $value['name']]));
                        }
                    } else {

                        $input = Xss::filter($value['name']);
                        $fields = array(
                            'service_name' => $input,
                            'eid' => $value['eid'],
                        );



                        Database::getConnection('external_db', 'external_db')
                                ->update('ek_hr_service')
                                ->fields($fields)
                                ->condition('sid', $key)
                                ->execute();
                    }
                } else {
                    if ($value['name'] <> '') {
                        $input = Xss::filter($value['name']);
                        $fields = array(
                            'coid' => $form_state->getValue('coid'),
                            'service_name' => $input,
                            'eid' => $value['eid'],
                        );

                        Database::getConnection('external_db', 'external_db')
                                ->insert('ek_hr_service')
                                ->fields($fields)
                                ->execute();

                        \Drupal::messenger()->addStatus(t('Service \'@l\' is created', ['@l' => $value['name']]));
                    }
                }
            }
            
            \Drupal::messenger()->addStatus(t('Data updated'));
        }//step 3
    }

}
