<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\FilterMemo.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides a form to filter finance memo.
 */
class FilterMemo extends FormBase {

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
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_expenses_memo', 'm');
        $query->fields('m', ['date']);
        $query->range(0, 1);
        $query->OrderBy('date', 'DESC');
        $to = $query->execute()->fetchObject();

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
            '#open' => isset($_SESSION['memfilter']['filter']) ? false : true,
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

        $form['pdf'] = array(
            '#title' => $this->t('Print range'),
            '#type' => 'link',
            '#url' => Url::fromRoute('ek_finance_manage_print_memo_range', ['category' => $category]),
        );

        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );

        $form['filters'][0]['keyword'] = array(
            '#type' => 'textfield',
            '#maxlength' => 100,
            '#size' => 30,
            '#attributes' => array('placeholder' => $this->t('Search with ref No.')),
            '#default_value' => isset($_SESSION['memfilter']['keyword']) ? $_SESSION['memfilter']['keyword'] : null,
        );


        $form['filters'][1]['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $entity,
            '#default_value' => isset($_SESSION['memfilter']['coid']) ? $_SESSION['memfilter']['coid'] : '%',
            '#title' => $this->t('Issuer'),
            '#prefix' => "<div class='table'><div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters'][1]['coid2'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $coid,
            '#default_value' => isset($_SESSION['memfilter']['coid2']) ? $_SESSION['memfilter']['coid2'] : 0,
            '#title' => $this->t('Payor'),
            '#attributes' => array('style' => array('width:150px;')),
            '#prefix' => "<div class='cell cellfloat'>",
            '#suffix' => '</div></div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );


        $form['filters'][2]['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['memfilter']['from']) ? $_SESSION['memfilter']['from'] : $from,
            '#prefix' => "<div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#title' => $this->t('from'),
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        $form['filters'][2]['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['memfilter']['to']) ? $_SESSION['memfilter']['to'] : $to,
            '#title' => $this->t('to'),
            '#prefix' => "<div class='cell cellfloat'>",
            '#suffix' => '</div></div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );


        $form['filters'][3]['status'] = array(
            '#type' => 'select',
            '#options' => array('%' => $this->t('Any'), 0 => $this->t('Not paid'), 1 => $this->t('Partial'), 2 => $this->t('Paid')),
            '#default_value' => isset($_SESSION['memfilter']['status']) ? $_SESSION['memfilter']['status'] : '%',
            '#title' => $this->t('status'),
            '#suffix' => '</div>',
            '#prefix' => "<div class='row'><div class='cell cellfloat'>",
            '#suffix' => '</div>',
            '#states' => array(
                'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                ),
            ),
        );

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $pcode = array('%' => $this->t('Any'));
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses_memo', 'm');
            $query->fields('m', ['id', 'pcode']);
            $query->condition('pcode', 'n/a', '<>');
            $query->distinct();
            $list = $query->execute()->fetchAllKeyed();

            $pcode += \Drupal\ek_projects\ProjectData::format_project_list($list);

            $form['filters'][3]['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($pcode, $pcode),
                '#default_value' => isset($_SESSION['memfilter']['pcode']) ? $_SESSION['memfilter']['pcode'] : '%',
                '#title' => $this->t('project'),
                '#attributes' => array('style' => array('width:150px;')),
                '#prefix' => "<div class='cell cellfloat'>",
                '#suffix' => '</div></div>',
                '#states' => array(
                    'invisible' => array(':input[name="keyword"]' => array('filled' => true),
                    ),
                ),
            );
        } else {
            $form['filters'][3]['pcode'] = array(
                '#type' => 'hidden',
                '#value' => '%',
                '#suffix' => '</div>',
            );
        }



        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
                //'#suffix' => "</div>",
        );

        if (!empty($_SESSION['memfilter'])) {
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
        if (!$form_state->getValue('keyword') == '') {
            //check input if filter not by keyword

            if (!is_numeric($form_state->getValue('keyword'))) {
                $form_state->setErrorByName("keyword", $this->t('Reference must be numeric value.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['memfilter']['category'] = $form_state->getValue('category');
        $_SESSION['memfilter']['from'] = $form_state->getValue('from');
        $_SESSION['memfilter']['to'] = $form_state->getValue('to');
        $_SESSION['memfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['memfilter']['coid2'] = $form_state->getValue('coid2');
        $_SESSION['memfilter']['pcode'] = $form_state->getValue('pcode');
        $_SESSION['memfilter']['status'] = $form_state->getValue('status');
        $_SESSION['memfilter']['keyword'] = Xss::filter($form_state->getValue('keyword'));
        $_SESSION['memfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['memfilter'] = array();
    }

}
