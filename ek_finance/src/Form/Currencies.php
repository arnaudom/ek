<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\Currencies.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides a currencies management form.
 */
class Currencies extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_manage_currencies';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $request = null) {
        $param = serialize(['id' => 'currency']);
        $url = Url::fromRoute('ek_finance_modal', array('param' => $param));

        $form['add'] = array(
            '#type' => 'link',
            '#title' => $this->t('New currency'),
            '#attributes' => ['class' => ['button', 'use-ajax']],
            '#url' => $url,
        );

        $settings = new FinanceSettings();
        $baseCurrency = $settings->get('baseCurrency');

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_currency', 'c')
                ->fields('c');
        if(!null == $request->get('sort')) {
             $query->orderBy($request->get('order'), $request->get('sort'));
        } else {
            $query->orderBy('currency');
        }
               
        $data = $query->execute();
        $this->cur = $data;
        $header = array(
                'currency' => array(
                    'data' => $this->t('Currency'),
                    'field' => 'code',
                    'sort' => 'asc',
                    'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                ),
                'name' => array(
                    'data' => $this->t('Name'),
                ),
                'rate' => array(
                    'data' => $this->t('Exchange rate'),
                ),
                'active' => $this->t('Active'),
        );


        $form['1'] = array(
            '#type' => 'details',
            '#title' => $this->t('Active'),
            '#collapsible' => true,
            '#open' => true,
            //'#prefix' => "<div class='table'>",
        );

        $form['0'] = array(
            '#type' => 'details',
            '#title' => $this->t('Non Active'),
            '#collapsible' => true,
            '#open' => false,
            //'#prefix' => "<div class='table'>",
        );
        
        $form['1']['active-table'] = array(
                '#tree' => true,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 'active-table'),
                '#empty' => $this->t('No active currency'),
        );
        
        $form['0']['available-table'] = array(
                '#tree' => true,
                '#theme' => 'table',
                '#header' => $header,
                '#rows' => array(),
                '#attributes' => array('id' => 'available-table'),
                '#empty' => $this->t('Available currencies'),
        );
        
        while ($r = $data->fetchObject()) {
            $id = $r->id;
            if ($baseCurrency == $r->currency) {
                $value = 1;
                $disabled = true;
                $description = $this->t('selected base currency');
            } else {
                $value = $r->rate;
                $disabled = false;
                $description = '';
            }
            if ($r->active == 1) {
                $form['id'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->id,
                );
                
                $form['currency_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->currency
                );
                        
                $form['fx_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->rate,
                );
                $form['active_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->active,
                );

                $form['currency'] = array(
                    '#type' => 'item',
                    '#markup' => $r->currency,
                );


                $form['name'] = array(
                    '#type' => 'item',
                    '#markup' => $r->name,
                );

                $form['fx'] = array(
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#disabled' => $disabled,
                    '#maxlength' => 30,
                    '#default_value' => $r->rate,
                    '#attributes' => array('placeholder' => $this->t('rate')),
                    '#description' => $description,
                );

                $form['active'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 1,
                    '#attributes' => array('title' => $this->t('uncheck to remove')),
                );
                
                
                $form['1']['active-table'][$id] = array(
                    'currency' => &$form['currency'],
                    'name' => &$form['name'],
                    'rate' => &$form['fx'],
                    'active' => &$form['active'],
                    'id' => $form['id'],
                    'rate_' => &$form['fx_'],
                    'active_' => &$form['active_'],
                    'currency_' => &$form['currency_'],
                );

                $form['1']['active-table']['#rows'][] = array(
                    'data' => array(
                        array('data' => &$form['currency']),
                        array('data' => &$form['name']),
                        array('data' => &$form['fx']),
                        array('data' => &$form['active']),
                    ),
                    'id' => array($id)
                );

                unset($form['currency']);
                unset($form['name']);
                unset($form['fx']);
                unset($form['active']);
                unset($form['id']);
                unset($form['fx_']);
                unset($form['active_']);
                unset($form['currency_']);
                
                
                
            } else {
                $form['id'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->id,
                );
                
                $form['fx_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->rate,
                );
                
                $form['currency_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->currency,
                );
                
                $form['active_'] = array(
                    '#type' => 'hidden',
                    '#value' => $r->active,
                );
                
                $form['currency'] = array(
                    '#type' => 'item',
                    '#markup' => $r->currency,
                );
                $form['name'] = array(
                    '#type' => 'item',
                    '#markup' => $r->name,
                );


                $form['fx'] = array(
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#disabled' => $disabled,
                    '#maxlength' => 30,
                    '#default_value' => $r->rate,
                    '#attributes' => array('placeholder' => $this->t('rate')),
                    '#description' => $description,
                );

                $form['active'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#attributes' => array('title' => $this->t('select to activate')),
                );
                
                $form['0']['available-table'][$id] = array(
                    'currency' => &$form['currency'],
                    'name' => &$form['name'],
                    'rate' => &$form['fx'],
                    'active' => &$form['active'],
                    'id' => $form['id'],
                    'rate_' => &$form['fx_'],
                    'active_' => &$form['active_'],
                    'currency_' => &$form['currency_'],
                );

                $form['0']['available-table']['#rows'][] = array(
                    'data' => array(
                        array('data' => &$form['currency']),
                        array('data' => &$form['name']),
                        array('data' => &$form['fx']),
                        array('data' => &$form['active']),
                    ),
                    'id' => array($id)
                );

                unset($form['currency']);
                unset($form['name']);
                unset($form['fx']);
                unset($form['active']);
                unset($form['id']);
                unset($form['fx_']);
                unset($form['active_']);
                unset($form['currency_']);
            }
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));

        $form['#attached']['library'][] = 'ek_finance/ek_finance.dialog';

        return $form;
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $update = '';
        $list = $form_state->getValue('active-table');
        
        foreach ($list as $key => $value) {
            
            if( $value['rate_'] != $value['rate'] || $value['active_'] != $value['active']) {
          
                if (is_numeric($value['rate'])) {
                    $rate = round($value['rate'], 4);
                } else {
                    $rate = 0;
                }

                Database::getConnection('external_db', 'external_db')->update('ek_currency')
                        ->condition('id', $key)
                        ->fields(['rate' => $rate, 'active' => $value['active']])
                        ->execute();
                
                $update .= $value['currency_'] . ' ';
            }
        }
        
        $list = $form_state->getValue('available-table');
        
        foreach ($list as $key => $value) {
            
            if( $value['rate_'] != $value['rate'] || $value['active_'] != $value['active']) {
                if (is_numeric($value['rate'])) {
                    $rate = round($value['rate'], 4);
                } else {
                    $rate = 0;
                }

                Database::getConnection('external_db', 'external_db')->update('ek_currency')
                        ->condition('id', $key)
                        ->fields(['rate' => $rate, 'active' => $value['active']])
                        ->execute();
                
                $update .= $value['currency_'] . ' ';
            }
        }
        
        \Drupal::messenger()->addStatus( $this->t('Currency updated @c', ['@c' => $update]));
        if ($_SESSION['install'] == 1) {
            unset($_SESSION['install']);
            $form_state->setRedirect('ek_admin.main');
        } 
    }
}
