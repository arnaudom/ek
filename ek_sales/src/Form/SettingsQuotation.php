<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsQuotation.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to manage quotation settings.
 */
class SettingsQuotation extends FormBase {

    /**
     *
     */
    public function __construct() {
        $this->salesSettings = new \Drupal\ek_sales\SalesSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'quotations_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $quote = $this->salesSettings->get('quotation');
        $form['setting']['#tree'] = true;
        $name = [1 => 'Item', 2 => 'Origin', 3 => 'Reference', 4 => 'Quantities', 5 => 'Price', 6 => 'Total'];

        for ($i = 1; $i <= 6; $i++) {
            $form['setting'][$i]['field'] = array(
                '#type' => 'textfield',
                '#value' => $this->t('column') . ' ' . $i,
                '#disabled' => true,
                '#size' => 20,
                '#prefix' => "<div class='container-inline'>",
            );

            $form['setting'][$i]['name'] = array(
                '#type' => 'textfield',
                '#default_value' => isset($quote[$i]['name']) ? $quote[$i]['name'] : $name[$i],
                '#size' => 20,
                '#maxlength' => 50,
                '#required' => true,
            );

            $form['setting'][$i]['active'] = array(
                '#type' => 'select',
                '#options' => array('0' => $this->t('hide'), '1' => $this->t('display')),
                '#default_value' => isset($quote[$i]['active']) ? $quote[$i]['active'] : 1,
                '#suffix' => '</div>',
            );
        }



        $form['actions']['record'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Record'),
        );

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
        $quotation = [];
        $i = 0;
        foreach ($form_state->getValue('setting') as $key => $data) {
            $i++;
            $quotation[$i]['field'] = $data['field'];
            $quotation[$i]['name'] = trim(Xss::filter($data['name']));
            $quotation[$i]['active'] = $data['active'];
        }

        $this->salesSettings->set('quotation', $quotation);
        $save = $this->salesSettings->save();

        if ($save) {
            \Drupal::messenger()->addStatus(t('The settings are recorded'));
        }
    }

}
