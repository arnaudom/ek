<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterQuotation.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to filter quotations.
 */
class FilterQuotation extends FormBase {

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
        return 'quotation_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $to = Database::getConnection('external_db', 'external_db')
                ->query("SELECT date FROM {ek_sales_quotation} order by date DESC limit 1")
                ->fetchObject();
        $from = Database::getConnection('external_db', 'external_db')
                ->query("SELECT date FROM {ek_sales_quotation} order by date limit 1")
                ->fetchObject();
        //$date1= date('Y-m-d', strtotime($data2." -30 days")) ;

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => isset($_SESSION['qfilter']['filter']) ? FALSE : TRUE,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['qfilter']['from']) ? $_SESSION['qfilter']['from'] : $from->date,
            //'#prefix' => "<div class='container-inline'>",
            '#title' => t('from'),
        );

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['qfilter']['to']) ? $_SESSION['qfilter']['to'] : $to->date,
            '#title' => t('to'),
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = array('%' => t('Any'));
            $client += \Drupal\ek_address_book\AddressBookData::addresslist(1);

            if (!empty($client)) {
                $form['filters']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => TRUE,
                    '#default_value' => isset($_SESSION['qfilter']['client']) ? $_SESSION['qfilter']['client'] : NULL,
                    '#attributes' => array('style' => array('width:200px;white-space:nowrap')),
                    '#title' => t('client'),
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . t('new') . "' href='" . $link . "'>" . t('client') . "</a>";
                $form['options']['client'] = array(
                    '#markup' => t("You do not have any @n in your record.", ['@n' => $new]),
                );
            }
        } else {

            $form['filters']['client'] = array(
                '#markup' => t('You do not have any client list.'),
                '#default_value' => 0,
            );
        }

        $form['filters']['status'] = array(
            '#type' => 'select',
            '#options' => array('%' => t('All'), 0 => t('Open'), 1 => t('Printed'), 2 => t('Invoiced')),
            '#default_value' => isset($_SESSION['qfilter']['status']) ? $_SESSION['qfilter']['status'] : '0',
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

        if (!empty($_SESSION['qfilter'])) {
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
        if (!$form_state->getValue('client')) {
            $form_state->setErrorByName('supplier', $this->t('You must select a client.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $_SESSION['qfilter']['from'] = $form_state->getValue('from');
        $_SESSION['qfilter']['to'] = $form_state->getValue('to');
        $_SESSION['qfilter']['status'] = $form_state->getValue('status');
        $_SESSION['qfilter']['client'] = $form_state->getValue('client');
        $_SESSION['qfilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['qfilter'] = array();
    }

}
