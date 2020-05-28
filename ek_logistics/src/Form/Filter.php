<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\Filter.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to filter logistic.
 */
class Filter extends FormBase {

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
        return 'logistic_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $to = date('Y-m-d');
        $from = date('Y-m-d', strtotime($to . " -30 days"));

        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Filter'),
            '#open' => (isset($_SESSION['lofilter']['filter']) && $_SESSION['lofilter']['filter'] == 1) ? false : true,
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['filters']['filter'] = array(
            '#type' => 'hidden',
            '#value' => 'filter',
        );
        $form['filters']['from'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['lofilter']['from']) ? $_SESSION['lofilter']['from'] : $from,
            '#title' => $this->t('from'),
        );

        $form['filters']['to'] = array(
            '#type' => 'date',
            '#size' => 12,
            '#default_value' => isset($_SESSION['lofilter']['to']) ? $_SESSION['lofilter']['to'] : $to,
            '#title' => $this->t('to'),
        );


        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = array('%' => $this->t('Any'));

            if (strpos(\Drupal::request()->getRequestUri(), 'receiving')) {
                $client += \Drupal\ek_address_book\AddressBookData::addresslist(2);
                $parent = $this->t('supplier');
            } elseif (strpos(\Drupal::request()->getRequestUri(), 'delivery')) {
                $client += \Drupal\ek_address_book\AddressBookData::addresslist(1);
                $parent = $this->t('client');
            } else {
                $client += \Drupal\ek_address_book\AddressBookData::addresslist(1);
                $parent = $this->t('client');
            }

            if (!empty($client)) {
                $form['filters']['client'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#options' => $client,
                    '#required' => true,
                    '#default_value' => isset($_SESSION['lofilter']['client']) ? $_SESSION['lofilter']['client'] : null,
                    '#title' => $parent,
                );
            } else {
                $link = Url::fromRoute('ek_address_book.new', array())->toString();
                $new = "<a title='" . $this->t('new') . "' href='" . $link . "'>" . $parent . "</a>";
                $form['options']['client'] = array(
                    '#markup' => $this->t("You do not have any @n in your record.", ['@n' => $new]),
                );
            }
        } else {
            $form['filters']['client'] = array(
                '#markup' => $this->t('You do not have any client list.'),
                '#default_value' => 0,
            );
        }

        $form['filters']['status'] = array(
            '#type' => 'select',
            '#options' => array('%' => $this->t('Any'), 0 => $this->t('Open'), 
                1 => $this->t('Printed'), 2 => $this->t('Invoiced'), 3 => $this->t('Posted')),
            '#default_value' => isset($_SESSION['lofilter']['status']) ? $_SESSION['lofilter']['status'] : '0',
        );

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['filters']['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Apply'),
        );

        if (!empty($_SESSION['lofilter'])) {
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
            $form_state->setErrorByName('client', $this->t('You must select a client.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['lofilter']['from'] = $form_state->getValue('from');
        $_SESSION['lofilter']['to'] = $form_state->getValue('to');
        $_SESSION['lofilter']['status'] = $form_state->getValue('status');
        $_SESSION['lofilter']['client'] = $form_state->getValue('client');
        $_SESSION['lofilter']['filter'] = 1;
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['lofilter'] = array();
    }

}
