<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\Payslip.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

;

/**
 * Provides a form to select payslip template
 */
class Payslip extends FormBase
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
        return 'payslips';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
    {
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



            $query = "SELECT id,name from {ek_hr_workforce} WHERE company_id=:coid order by id";
            $a = array(':coid' => $form_state->getValue('coid'));

            $employees = Database::getConnection('external_db', 'external_db')
                    ->query($query, $a)
                    ->fetchAllKeyed();

            $form['eid1'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $employees,
                '#title' => $this->t('Print from'),
                '#prefix' => '<div class="table"><div class="row "><div class="cell">',
                '#suffix' => '</div>',
            );


            krsort($employees);
            $form['eid2'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $employees,
                '#title' => $this->t('to'),
                '#prefix' => '<div class="cell">',
                '#suffix' => '</div></div>',
            );


            $month = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');

            $form['month'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($month, $month),
                '#title' => $this->t('month'),
                '#default_value' => date('m'),
                '#prefix' => '<div class="row "><div class="cell">',
                '#suffix' => '</div>',
            );

            $year = array(date('Y'), date('Y') - 1, date('Y') - 2, date('Y') - 3, date('Y') - 4);
            $form['year'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($year, $year),
                '#title' => $this->t('year'),
                '#prefix' => '<div class="cell">',
                '#suffix' => '</div>',
            );

            $list = array('default' => $this->t('Default payslip'));
            if (file_exists("private://hr/payslips")) {
                $handle = opendir("private://hr/payslips");
                while ($file = readdir($handle)) {
                    if ($file != '.' and $file != '..') {
                        $f = explode('.', $file);
                        $list += array($f[0] => $f[0]);
                    }
                }
            }
            $form['template'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $list,
                '#title' => $this->t('templates'),
                '#prefix' => '<div class="cell">',
                '#suffix' => '</div></div></div>',
            );

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Print'),
            );
        } else {
        }

        $form['#attached']['library'][] = 'ek_hr/ek_hr_css';


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['printpayslip']['coid'] = $form_state->getValue('coid');
        $_SESSION['printpayslip']['from'] = $form_state->getValue('eid1');
        $_SESSION['printpayslip']['to'] = $form_state->getValue('eid2');
        $_SESSION['printpayslip']['month'] = $form_state->getValue('year') . '-' . $form_state->getValue('month');
        $_SESSION['printpayslip']['template'] = $form_state->getValue('template');
        $_SESSION['printpayslip']['filter'] = 1;

        $form_state->set('step', 4);
        $form_state->setRebuild();
    }
}
