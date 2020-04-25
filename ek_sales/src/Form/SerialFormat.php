<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SerialFormat.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to setup the format of project serial reference
 */
class SerialFormat extends FormBase {

    /**
     *
     */
    public function __construct() {
        $this->settings = new \Drupal\ek_sales\SalesSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'sales_serial_format';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $s = $this->settings->get('serialFormat');
        $sample = ['', 'MYCO', 'TYPE', 'MM_YY', 'ABC', '123'];

        $string = isset($s['code'][1]) ? "<span id='e1'>" . $sample[$s['code'][1]] . "-</span>" : "<span id='e1'>" . $sample[1] . "</span>-";
        $string .= isset($s['code'][2]) ? "<span id='e2'>" . $sample[$s['code'][2]] . "-</span>" : "<span id='e2'>" . $sample[2] . "</span>-";
        $string .= isset($s['code'][3]) ? "<span id='e3'>" . $sample[$s['code'][3]] . "-</span>" : "<span id='e3'>" . $sample[3] . "</span>-";
        $string .= isset($s['code'][4]) ? "<span id='e4'>" . $sample[$s['code'][4]] . "-</span>" : "<span id='e4'>" . $sample[4] . "</span>-";
        $string .= isset($s['code'][5]) ? "<span id='e5'>" . $sample[$s['code'][5]] . "</span>" : "<span id='e5'>" . $sample[5] . "</span>";

        $form['sample'] = array(
            '#type' => 'item',
            '#markup' => '<h2>' . $string . '</h2>',
        );
        $elements = [
            0 => $this->t('hidden'),
            1 => $this->t('company'),
            2 => $this->t('document type'),
            3 => $this->t('date'),
            4 => $this->t('client code'),
            5 => $this->t('sequence number')
        ];

        $form['first'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('First element'),
            '#default_value' => isset($s['code'][1]) ? $s['code'][1] : 1,
        );

        $form['second'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Second element'),
            '#default_value' => isset($s['code'][2]) ? $s['code'][2] : 2,
        );

        $form['third'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Third element'),
            '#default_value' => isset($s['code'][3]) ? $s['code'][3] : 3,
        );

        $form['fourth'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Fourth element'),
            '#default_value' => isset($s['code'][4]) ? $s['code'][4] : 4,
        );

        $form['last'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [6 => $this->t('sequence number')],
            '#required' => true,
            '#title' => $this->t('Last element'),
            '#default_value' => isset($s['code'][6]) ? $s['code'][6] : 6,
            '#description' => $this->t('The sequence cannot be changed.'),
        );

        $form['increment'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#required' => true,
            '#title' => $this->t('Increment base'),
            '#default_value' => isset($s['increment']) ? $s['increment'] : 1,
            '#description' => $this->t('The sequence number to start from.'),
        );

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );

        $form['#attached']['library'][] = 'ek_sales/ek_sales_settings';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (!is_numeric($form_state->getValue('increment')) || $form_state->getValue('increment') < 0) {
            $form_state->setErrorByName("increment", $this->t('The increment value is should be a positive number.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        //verify coid exist first
        $query = 'SELECT coid from {ek_sales_settings} WHERE coid=:c';
        $coid = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':c' => 0))
                ->fetchField();

        if ($coid != '0') {
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_sales_settings')
                    ->fields(array('coid' => 0))
                    ->execute();
        }

        $elements = [
            'code' => [
                1 => $form_state->getValue('first'),
                2 => $form_state->getValue('second'),
                3 => $form_state->getValue('third'),
                4 => $form_state->getValue('fourth'),
                5 => 5
            ],
            'increment' => $form_state->getValue('increment')
        ];

        $this->settings->set('serialFormat', $elements);
        $save = $this->settings->save();

        if ($save) {
            \Drupal::messenger()->addStatus(t('The settings are recorded'));
        }
    }

}
