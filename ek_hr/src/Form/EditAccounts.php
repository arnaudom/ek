<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditAccounts.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;
use Drupal\ek_finance\AidList;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a form to record HR accounts
 */
class EditAccounts extends FormBase {

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
        $this->settings = new FinanceSettings();
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
        return 'hr_accounts_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        if ($this->moduleHandler->moduleExists('ek_finance')) {

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

                //verify if the settings table has the company
                $query = "SELECT count(coid) from {ek_hr_workforce_settings} where coid=:c";
                $row = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid')))->fetchField();

                if (!$row == 1) {

                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_hr_workforce_settings')
                            ->fields(array('coid' => $form_state->getValue('coid')))
                            ->execute();
                }


                $category = NEW HrSettings($form_state->getValue('coid'));
                $list = $category->HrAccounts[$form_state->getValue('coid')];



                if (empty($list)) {

                    $list = array(
                        $form_state->getValue('coid') => [
                            'pay_account' => '',
                            'fund1_account' => '',
                            'fund2_account' => '',
                            'fund3_account' => '',
                            'fund4_account' => '',
                            'fund5_account' => '',
                            'tax1_account' => '',
                            'tax2_account' => '',
                        ]
                    );

                    Database::getConnection('external_db', 'external_db')
                            ->update('ek_hr_workforce_settings')
                            ->fields(array('accounts' => serialize($list)))
                            ->condition('coid', $form_state->getValue('coid'))
                            ->execute();

                    $category = NEW HrSettings($form_state->getValue('coid'));
                    $list = $category->HrAccounts[$form_state->getValue('coid')];
                }
                $options = array('0' => NULL);
                $chart = $this->settings->get('chart');
                $options += AidList::listaid($form_state->getValue('coid'), array($chart['liabilities'], $chart['other_liabilities']), 1);

                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => t('Select finance account for each debit type'),
                );

                $param = [
                    'pay_account' => t('liability'),
                    'fund1_account' => $category->get('param', 'fund_1', ['name', 'value']),
                    'fund2_account' => $category->get('param', 'fund_2', ['name', 'value']),
                    'fund3_account' => $category->get('param', 'fund_3', ['name', 'value']),
                    'fund4_account' => $category->get('param', 'fund_4', ['name', 'value']),
                    'fund5_account' => $category->get('param', 'fund_5', ['name', 'value']),
                    'tax_account' => $category->get('param', 'tax', ['name', 'value']),
                ];
                foreach ($list as $key => $value) {

                    $name = $category->get('param', $param[$key], 'value') ? $category->get('param', $param[$key], 'value') : $param[$key];

                    $form[$key] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $options,
                        '#title' => str_replace('_', ' ', $key) . ' (' . $name . ')',
                        '#default_value' => $value,
                    );
                }


                $form['actions'] = array(
                    '#type' => 'actions',
                    '#attributes' => array('class' => array('container-inline')),
                );

                $form['actions']['submit'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Save'),
                    '#suffix' => ''
                );



                $form['#attached']['library'][] = 'ek_hr/ek_hr_css';
            }
        }//if finance 
        else {
            $form['info'] = array(
                '#type' => 'item',
                '#markup' => t('Finance module is not available'),
            );
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
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {

            $category = NEW HrSettings($form_state->getValue('coid'));
            $list = $category->HrAccounts[$form_state->getValue('coid')];

            foreach ($list as $key => $value) {
                $input = $form_state->getValue($key);
                $category->set(
                        'accounts', $key, $input
                );
            }

            $category->save();
            \Drupal::messenger()->addStatus(t('Data updated'));
        }//step 2
    }

}
