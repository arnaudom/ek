<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditMainParameters.
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
 * Provides a form to create or edit HR main parameters
 */
class EditMainParameters extends FormBase {

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
        return 'main_parameters_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => $this->t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
        );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
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

            $param = new HrSettings($form_state->getValue('coid'));
            $list = $param->HrParam;


            if (empty($list)) {
                //create a new list
                $list = array(
                        'fund_1' => [
                            'name' => array('description' => 'Fund 1 name', 'value' => 'Fund 1',),
                            'calcul' => array('description' => 'Fund 1 calculation (P=percent; T=table)', 'value' => 'P',),
                            'employer' => array('description' => 'employer Fund 1 contribution (%)', 'value' => '0',),
                            'employee' => array('description' => 'employee Fund 1 contribution (%)', 'value' => '0',),
                            'base' => array('description' => 'Fund 1 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C',),
                        ],
                        'fund_2' => [
                            'name' => array('description' => 'Fund 2 name', 'value' => 'Fund 2',),
                            'calcul' => array('description' => 'Fund 2 calculation (P=percent; T=table)', 'value' => 'P',),
                            'employer' => array('description' => 'employer Fund 2 contribution (%)', 'value' => '0',),
                            'employee' => array('description' => 'employee Fund 2 contribution (%)', 'value' => '0',),
                            'base' => array('description' => 'Fund 2 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C',),
                        ],
                        'fund_3' => [
                            'name' => array('description' => 'Fund 3 name', 'value' => 'Fund 3',),
                            'calcul' => array('description' => 'Fund 3 calculation (P=percent; T=table) ', 'value' => 'P',),
                            'employer' => array('description' => 'employer Fund 3 (%)', 'value' => '0',),
                            'employee' => array('description' => 'employee Fund 3 (%)', 'value' => '0',),
                            'base' => array('description' => 'Fund 3 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C',),
                        ],
                        'fund_4' => [
                            'name' => array('description' => 'Fund 4 name', 'value' => 'Fund 4',),
                            'calcul' => array('description' => 'Fund 4 calculation (P=percent; T=table) ', 'value' => 'P',),
                            'employer' => array('description' => 'employer Fund 4 (%)', 'value' => '0',),
                            'employee' => array('description' => 'employee Fund 4 (%)', 'value' => '0',),
                            'base' => array('description' => 'Fund 4 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C',),
                        ],
                        'fund_5' => [
                            'name' => array('description' => 'Fund 5 name', 'value' => 'Fund 5',),
                            'calcul' => array('description' => 'Fund 5 calculation (P=percent; T=table)', 'value' => 'P',),
                            'employer' => array('description' => 'employer Fund 5 (%)', 'value' => '0',),
                            'employee' => array('description' => 'employee Fund 5 (%)', 'value' => '0',),
                            'base' => array('description' => 'Fund 5 calculation base (C=contract,A=average,B=basic,G=gross,GOT=Gross-OTs)', 'value' => 'C',),
                        ],
                        'tax' => [
                            'name' => array('description' => 'income tax name', 'value' => 'Income tax',),
                            'calcul' => array('description' => 'income tax calculation (P=percent; T=table)', 'value' => 'P',),
                            'base' => array('description' => 'income tax calculation base (C=contract, A=average, B=basic, G=gross)', 'value' => 'C',),
                            'employee' => array('description' => 'income tax employee (%)', 'value' => '0',),
                            'employer' => array('description' => 'income tax employer(%)', 'value' => '0',),
                        ]
                    );

                Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce_settings')
                        ->fields(array('param' => serialize($list)))
                        ->condition('coid', $form_state->getValue('coid'))
                        ->execute();

                $category = new HrSettings($form_state->getValue('coid'));
                $list = $category->HrParam;
            }

            foreach ($list as $key => $value) {
                $form[$key] = array(
                    '#type' => 'details',
                    '#title' => str_replace('_', ' ', $key),
                    '#open' => true,
                    '#tree' => true,
                );

                $form[$key]['name'] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 100,
                    '#default_value' => $value['name']['value'],
                    '#description' => $value['name']['description'],
                );
                $form[$key]['calcul'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => ['P' => $this->t('percent'), 'T' => $this->t('tabulation')],
                    '#default_value' => $value['calcul']['value'],
                    '#description' => $value['calcul']['description'],
                );
                $form[$key]['employer'] = array(
                    '#type' => 'textfield',
                    '#size' => 6,
                    '#maxlength' => 6,
                    '#default_value' => $value['employer']['value'],
                    '#description' => $value['employer']['description'],
                );
                $form[$key]['employee'] = array(
                    '#type' => 'textfield',
                    '#size' => 6,
                    '#maxlength' => 6,
                    '#default_value' => $value['employee']['value'],
                    '#description' => $value['employee']['description'],
                );
                $o = [
                    'C' => $this->t('Contract'),
                    'B' => $this->t('Basic'),
                    'A' => $this->t('Other base'),
                    'G' => $this->t('Gross'),
                    'BF' => $this->t('Basic + fixed AW'),
                    'BMF' => $this->t('Basic - fixed AW'),
                    'GMFC' => $this->t('Gross - fixed AW & com.')
                    ];
                $form[$key]['base'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $o,
                    '#default_value' => $value['base']['value'],
                    '#description' => $value['base']['description'],
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
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {
            $settings = new HrSettings($form_state->getValue('coid'));
            $params = $settings->HrParam;

            foreach ($params as $key => $values) {
                $data = $form_state->getValue($key);
                foreach ($data as $param => $val) {
                    $val = Xss::filter($val);
                    $settings->set(
                        'param',
                        $key,
                        [$param, $val]
                    );
                }
            }

            $save = $settings->save();
            if ($save) {
                \Drupal::messenger()->addStatus('Data saved');
            }
        }
    }
}
