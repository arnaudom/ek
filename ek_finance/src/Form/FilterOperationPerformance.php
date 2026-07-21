<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterOperationPerformance.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter the operation performance report.
 */
class FilterOperationPerformance extends FormBase {

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
        return 'operation_performance_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $year = date('Y');

        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);
        $query = "SELECT id,name from {ek_company} where active=:t AND FIND_IN_SET (id, :c ) order by name";
        $company = Database::getConnection('external_db', 'external_db')
            ->query($query, [':t' => 1, ':c' => $company])
            ->fetchAllKeyed();

        $form['filters'] = [
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => TRUE,
            '#attributes' => ['class' => ['container-inline']],
        ];
        $form['filters']['filter'] = [
            '#type' => 'hidden',
            '#value' => 'filter',
        ];
        $options = [$year + 1, $year, $year - 1, $year - 2, $year - 3, $year - 4];
        $form['filters']['year'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => array_combine($options, $options),
            '#default_value' => isset($_SESSION['opfilter']['year']) ? $_SESSION['opfilter']['year'] : $year,
            '#title' => $this->t('year'),
        ];

        $form['filters']['coid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#required' => TRUE,
            '#title' => $this->t('company'),
            '#default_value' => isset($_SESSION['opfilter']['coid']) ? $_SESSION['opfilter']['coid'] : NULL,
        ];

        $form['filters']['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['filters']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        ];

        if (!empty($_SESSION['opfilter'])) {
            $form['filters']['actions']['reset'] = [
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => [],
                '#submit' => [[$this, 'resetForm']],
            ];
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['opfilter']['year'] = $form_state->getValue('year');
        $_SESSION['opfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['opfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['opfilter'] = [];
    }

}
