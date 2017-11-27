<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\NewCurrencyForm
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Provides a form to create a new currency.
 */
class NewCurrencyForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_new_currency';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['alert'] = array(
            '#type' => 'item',
            '#prefix' => "<div id='alert'></div>",
        );
        
        $form['code'] = array(
            '#type' => 'textfield',
            '#title' => t('Currency code'),
            '#size' => 8,
            '#maxlength' => 3,
            '#required' => TRUE,
            '#suffix' => '',
        );

        $form['name'] = array(
            '#type' => 'textfield',
            '#size' => 30,
            '#title' => t('Currency name'),
            '#maxlength' => 100,
            '#required' => TRUE,
        );

        $form['rate'] = array(
            '#type' => 'textfield',
            '#size' => 8,
            '#title' => t('Exchange rate'),
            '#maxlength' => 10,
            '#default_value' => 0,
        );

        $form['active'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [0 => t('no'), 1 => t('yes')],
            '#title' => t('Active'),
            '#default_value' => 1,
        );

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['save'] = array(
            '#id' => 'buttonid',
            '#type' => 'submit',
            '#value' => t('Record'),
            '#attributes' => array('class' => array('button--record')),
            '#ajax' => [
                'callback' => [$this, 'saveForm'],
            ]
        );

        return $form;
    }

    public function saveForm(array &$form, FormStateInterface $form_state) {

        $error = 0;
        $response = new AjaxResponse();
        if (strlen($form_state->getValue('code')) < 3) {
            $error = 1;
            $command = new ReplaceCommand('#alert', "<div class='messages messages--error'>" . $this->t('Error: code invalid') . "</div>");
            return $response->addCommand($command);
       
            
        } else {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_currency', 'c');
            $data = $query
                        ->fields('c', ['id'])
                        ->condition('c.currency', $form_state->getValue('code'), '=')
                        ->execute()->fetchField();

            if ($data) {
                $error = 1;
                $command = new ReplaceCommand('#alert', "<div class='messages messages--error'>" . $this->t('Error: code exists') . "</div>");
                return $response->addCommand($command);
               
              
            }
        }

        if (!is_numeric($form_state->getValue('rate'))) {
            $error = 1;
            $command = new ReplaceCommand('#alert', "<div class='messages messages--error'>" . $this->t('Error: rate invalid') . "</div>");
            return $response->addCommand($command);
            
        }
        
        if($error != 1) {
            $name = Xss::filter($form_state->getValue('name'));

            $fields = array(
                'currency' => strtoupper($form_state->getValue('code')),
                'name' => ucfirst($name),
                'rate' => $form_state->getValue('rate'),
                'active' => $form_state->getValue('active'),
                'date' => date('Y-m-d H:i:s')
            );
            $insert = Database::getConnection('external_db', 'external_db')
                    ->insert('ek_currency')
                    ->fields($fields)->execute();
            
           
          if (isset($insert)) {
                drupal_set_message(t('Currency @c recorded', ['@c' => strtoupper($form_state->getValue('code'))]), 'status');
                $response = new AjaxResponse();
                $url = Url::fromRoute('ek_finance.currencies')->toString();
                $response->addCommand(new RedirectCommand($url));
                $response->addCommand(new CloseDialogCommand());
                return $response;
                
          }
        }

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
     
        
    }
    


}
