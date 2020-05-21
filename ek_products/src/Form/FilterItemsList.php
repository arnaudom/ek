<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\FilteritemsList.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter employee list.
 */
class FilteritemsList extends FormBase {

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
        return 'items_list_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $company = array('%' => t('Any'));
        $company += AccessCheck::CompanyListByUid();

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => isset($_SESSION['itemfilter']['filter']) ? false : true,
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => isset($_SESSION['itemfilter']['coid']) ? $_SESSION['itemfilter']['coid'] : null,
            '#title' => t('Entity'),
            '#required' => true,
                //'#prefix' => "<div class='container-inline'>",
        );


        $form['filters']['tag'] = array(
            '#type' => 'select',
            '#title' => t('Tags'),
            '#options' => array('%' => t('Any'), 'type' => t('Type'), 'department' => t('Department'), 'family' => t('Family'), 'collection' => t('Collection'), 'color' => t('Color')),
            '#default_value' => isset($_SESSION['itemfilter']['tag']) ? $_SESSION['itemfilter']['tag'] : 'working',
            '#ajax' => array(
                'callback' => array($this, 'tagvalue'),
                'wrapper' => 'tagvalue',
            ),
            '#prefix' => "<div class='container-inline'>",
        );

        if (isset($_SESSION['itemfilter']['tag']) || $form_state->getValue('tag') != '') {
            if ($form_state->getValue('tag') != '') {
                $f = $form_state->getValue('tag');
            } else {
                $f = $_SESSION['itemfilter']['tag'];
            }

            switch ($f) {
                case 'type':
                    $query = "SELECT DISTINCT type FROM {ek_items} ORDER BY type";
                    break;
                case 'department':
                    $query = "SELECT DISTINCT department FROM {ek_items} ORDER BY department";
                    break;
                case 'family':
                    $query = "SELECT DISTINCT family FROM {ek_items} ORDER BY family";
                    break;
                case 'collection':
                    $query = "SELECT DISTINCT collection FROM {ek_items} ORDER BY collection";
                    break;
                case 'color':
                    $query = "SELECT DISTINCT color FROM {ek_items} ORDER BY color";
                    break;
                case '%':
                    $query = null;
                    break;
            }

            if (!$query == null) {
                $options = Database::getConnection('external_db', 'external_db')->query($query)->fetchCol();
            } else {
                $options = array('%' => t('Any'));
            }
        } else {
            $options = array();
        }

        $form['filters']['tagvalue'] = array(
            '#type' => 'select',
            '#options' => array_combine($options, $options),
            '#default_value' => isset($_SESSION['itemfilter']['tagvalue']) ? $_SESSION['itemfilter']['tagvalue'] : null,
            '#prefix' => "<div id='tagvalue'>",
            '#suffix' => '</div></div>',
        );

        $form['filters']['status'] = array(
            '#type' => 'select',
            '#title' => t('Status'),
            '#options' => array('%' => t('Any'), '1' => t('Active'), '0' => t('Stop')),
            '#default_value' => isset($_SESSION['itemfilter']['status']) ? $_SESSION['itemfilter']['status'] : 'working',
        );
        $form['filters']['paging'] = array(
            '#type' => 'number',
            '#title' => t('items per page'),
            '#default_value' => isset($_SESSION['itemfilter']['paging']) ? $_SESSION['itemfilter']['paging'] : 25,
            '#min' => 25,
            '#max' => 10000,
            '#step' => 25,
            '#size' => 20,
            '#prefix' => "<div class='container-inline'>",
            '#suffix' => '</div>',
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['itemfilter'])) {
            $form['filters']['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Reset'),
                '#limit_validation_errors' => array(),
                '#submit' => array(array($this, 'resetForm')),
            );
        }
        return $form;
    }

    /**
     * Callback
     */
    public function tagvalue(array &$form, FormStateInterface $form_state) {
        return $form['filters']['tagvalue'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (!is_numeric($form_state->getValue('paging'))) {
            $form_state->setErrorByName('paging', $this->t('Items per page must be numeric'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['itemfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['itemfilter']['status'] = $form_state->getValue('status');
        $_SESSION['itemfilter']['tag'] = $form_state->getValue('tag');
        $_SESSION['itemfilter']['tagvalue'] = $form_state->getValue('tagvalue');
        $_SESSION['itemfilter']['paging'] = $form_state->getValue('paging');
        $_SESSION['itemfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['itemfilter'] = array();
    }

}
