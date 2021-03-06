<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterPrintRange.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_finance\AidList;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter range of memo for printing.
 */
class FilterPrintRange extends FormBase {

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
        return 'memos_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $category = null) {
        $query = "SELECT date from {ek_expenses_memo} order by date DESC limit 1";
        $to = Database::getConnection('external_db', 'external_db')->query($query)->fetchObject();

        if (!$to) {
            $to = date('Y-m-d');
            $from = date('Y-m') . "-01";
        } else {
            $to = $to->date;
            $f = strtotime($to);
            $from = date('Y-m', $f) . '-01';
        }

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => ($_SESSION['memrgfilter']['filter'] == 1) ? false : true,
        );

        $coid = array('%' => $this->t('Any'));
        $coid += \Drupal\ek_admin\Access\AccessCheck::CompanyListByUid();

        if ($category != 'internal') {
            if (\Drupal::currentUser()->hasPermission('admin_memos')) {
                $entity = array('%' => $this->t('Any'));
                $entity += \Drupal\ek_admin\Access\AccessCheck::listUsers();
                $category = 'personal';
            } else {
                $entity = array(
                    \Drupal::currentUser()->id() => \Drupal::currentUser()->getAccountName()
                );
            }
        } else {
            $entity = $coid;
        }

        $form['category'] = array(
            '#type' => 'hidden',
            '#value' => $category,
        );

        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );


        $form['filters'][1]['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $entity,
            '#default_value' => isset($_SESSION['memrgfilter']['coid']) ? $_SESSION['memrgfilter']['coid'] : '%',
            '#title' => $this->t('Issuer'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['filters'][1]['coid2'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $coid,
            '#default_value' => isset($_SESSION['memrgfilter']['coid2']) ? $_SESSION['memrgfilter']['coid2'] : 0,
            '#title' => $this->t('Payor'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div>',
        );


        $form['filters'][2]['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['memrgfilter']['from']) ? $_SESSION['memrgfilter']['from'] : $from,
            '#prefix' => "<div class='row'><div class='cell'>",
            '#suffix' => '</div>',
            '#title' => $this->t('from'),
        );

        $form['filters'][2]['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['memrgfilter']['to']) ? $_SESSION['memrgfilter']['to'] : $to,
            '#title' => $this->t('to'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );


        $form['filters'][3]['status'] = array(
            '#type' => 'select',
            '#options' => array('%' => $this->t('Any'), 0 => $this->t('Not paid'), 1 => $this->t('Partial'), 2 => $this->t('Paid')),
            '#default_value' => isset($_SESSION['memrgfilter']['status']) ? $_SESSION['memrgfilter']['status'] : '%',
            '#title' => $this->t('status'),
            '#suffix' => '</div>',
            '#prefix' => "<div class='row'><div class='cell'>",
            '#suffix' => '</div>',
        );

        $form['filters'][3]['signature'] = array(
            '#type' => 'checkbox',
            '#default_value' => 0,
            '#attributes' => array('title' => $this->t('signature')),
            '#title' => $this->t('signature'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div>',
        );

        $stamps = array('0' => $this->t('no'), '1' => $this->t('original'), '2' => $this->t('copy'));
        $form['filters'][3]['stamp'] = array(
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => array('title' => $this->t('stamp')),
            '#title' => $this->t('stamp'),
            '#prefix' => "<div class='cell'>",
            '#suffix' => '</div></div></div>',
        );

        //
        // provide selector for templates
        //
        $list = array(0 => 'default');
        if (file_exists('private://finance/templates/expenses_memo/')) {
            $handle = opendir();
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    $list[$file] = $file;
                }
            }
        }


        $form['filters']['template'] = array(
            '#type' => 'select',
            '#options' => $list,
            '#default_value' => $_SESSION['memrgfilter']['template'],
            '#title' => $this->t('template'),
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['memrgfilter'])) {
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
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['memrgfilter']['category'] = $form_state->getValue('category');
        $_SESSION['memrgfilter']['from'] = $form_state->getValue('from');
        $_SESSION['memrgfilter']['to'] = $form_state->getValue('to');
        $_SESSION['memrgfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['memrgfilter']['coid2'] = $form_state->getValue('coid2');
        $_SESSION['memrgfilter']['status'] = $form_state->getValue('status');
        $_SESSION['memrgfilter']['stamp'] = $form_state->getValue('stamp');
        $_SESSION['memrgfilter']['signature'] = $form_state->getValue('signature');
        $_SESSION['memrgfilter']['template'] = $form_state->getValue('template');
        $_SESSION['memrgfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['memrgfilter'] = array();
    }

}
