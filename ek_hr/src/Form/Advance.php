<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Advance.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

;

/**
 * Provides a form to record salary advance
 */
class Advance extends FormBase {

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
        return 'hr_advance_payroll';
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

                $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_hr_workforce', 'w')
                            ->fields('w', ['id','name','currency'])
                            ->condition('company_id', $form_state->getValue('coid'), '=')
                            ->orderBy('name', 'ASC');
           
            $employees = $query->execute();
            $i = 0;
           
            $header = array(
                'name' => array(
                    'data' => $this->t('Name'),
                    'id' => ['tour-item1'],
                ),
                'id' => array(
                    'data' => $this->t('ID'),
                    'id' => ['tour-item2'],
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'advance' => array(
                    'data' => $this->t('Advance'),
                    'id' => ['tour-item3'],
                ),
                'eid' => array(
                    'data' => '',
                ),
            );
            
            $form['items']['itemTable'] = array(
                '#tree' => TRUE,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 'itemTable'),
                '#empty' => '',
            );
            While ($e = $employees->fetchObject()) {
                $i++;
                $query = "SELECT advance FROM {ek_hr_workforce_pay} WHERE id=:id";
                $adv = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':id' => $e->id])
                        ->fetchField();
                
                $form['name'] = array(
                    '#id' => 'name-' . $e->id,
                    '#type' => 'item',
                    '#markup' => $e->name,
                );
                $form['id'] = array(
                    '#id' => 'id-' . $e->id,
                    '#type' => 'item',
                    '#markup' => "<span class='badge'>" . $e->id . "</span>",
                );
                $form['advance'] = array(
                    '#id' => 'advance-' . $e->id,
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#maxlength' => 30,
                    '#default_value' => (!NULL == $adv) ? $adv : 0, 
                    '#field_suffix' => "<span class=''>" . $e->currency . "</span>" ,
                );   
                $form['eid'] = array(
                    '#type' => 'hidden',
                    '#value' => $e->id,
                );                 
                //built edit rows for table
                $form['items']['itemTable'][$i] = array(
                    'name' => &$form['name'],
                    'id' => &$form['id'],
                    'advance' => &$form['advance'],
                    'eid' => &$form['eid'],
                );

                $form['items']['itemTable']['#rows'][$i] = array(
                    'data' => array(
                        array('data' => &$form['name']),
                        array('data' => &$form['id']),
                        array('data' => &$form['advance']),
                        array('data' => &$form['eid']),
                    ),
                    'id' => array($e->id),
                    'class' => '',
                );
                unset($form['name']);
                unset($form['id']);
                unset($form['advance']);
                unset($form['eid']);
                
            }

            
            $query = "SELECT current FROM {ek_hr_payroll_cycle} WHERE coid=:c";
            $a = array(':c' => $form_state->getValue('coid'));
            $current = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchField();
            $form['current'] = array(
                '#type' => 'hidden',
                '#value' => $current,
            );
            $form['count'] = array(
                '#type' => 'hidden',
                '#value' => $i,
            );
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Confirm advance for') . ' ' . $current,
                '#suffix' => ''
            );
            
        }//if step 2


        $form['#attached']['library'][] = 'ek_hr/ek_hr_css';





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
             
            $rows = $form_state->getValue('itemTable');
            if (!empty($rows)) {
                foreach ($rows as $key => $row) {
                    if ($row['advance'] != NULL && !is_numeric($row['advance'])) {
                        $form_state->setErrorByName("itemTable][$key][advance", $this->t('Non numeric value inserted: @v', ['@v' => $key]));
                    }

                }
            }

         }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {

            $rows = $form_state->getValue('itemTable');
            if (!empty($rows)) {
                foreach ($rows as $key => $row) {
                    if ($row['advance'] != NULL ) {
                        
                        $query = "SELECT id FROM {ek_hr_workforce_pay} WHERE id=:id";
                        $eid = Database::getConnection('external_db', 'external_db')
                            ->query($query, [':id' => $row['eid']])
                            ->fetchField();
                        if ($eid) {
                            Database::getConnection('external_db', 'external_db')
                                    ->update('ek_hr_workforce_pay')
                                    ->fields(['advance' => $row['advance'], 'month' => $form_state->getValue('current')])
                                    ->condition('id', $row['eid'])
                                    ->execute();

                        } else {
                            Database::getConnection('external_db', 'external_db')
                                    ->insert('ek_hr_workforce_pay')
                                    ->fields(['id' => $row['eid'], 'advance' => $row['advance'], 'month' => $form_state->getValue('current')])
                                    ->execute();
                       }
                    }

                }
            }
            \Drupal::messenger()->addStatus(t('Data recorded'));

        }
    }

}
