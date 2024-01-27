<?php

/**
 * @file
 * Contains \Drupal\ek\Form\NewAccountForm
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a form to create a new account in finance chart.
 */
class NewAccountForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_new_chart';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $param = explode('-', $param);

        $form['coid'] = [
            '#type' => 'hidden',
            '#value' => $param[0],
        ];

        $form['for_class'] = [
            '#type' => 'hidden',
            '#value' => str_replace('%', '', $param[1]),
        ];

        $form['class'] = [
            '#type' => 'item',
            '#markup' => str_replace('%', '', $param[1]),
            '#prefix' => "<div class='container-inline'>",
        ];

        $form['aid'] = [
            '#type' => 'textfield',
            '#size' => 8,
            '#maxlength' => 3,
            '#required' => true,
            '#suffix' => '</div>',
        ];

        $form['aname'] = [
            '#type' => 'textfield',
            '#size' => 30,
            '#title' => $this->t('account name'),
            '#maxlength' => 50,
            '#required' => true,
        ];
        
        $form['alert'] = [
            '#type' => 'item',
            '#prefix' => "<div class='alert'>",
            '#suffix' => '</div>',
        ];

        $form['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $form['actions']['save'] = [
            '#id' => 'savebutton',
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#ajax' => [
                'callback' => [$this, 'formCallback'],
                'wrapper' => 'alert',
                'method' => 'replace',
                'effect' => 'fade',
            ],
        ];

        $form['actions']['close'] = [
            '#id' => 'closebutton',
            '#type' => 'submit',
            '#value' => $this->t('Close'),
            '#ajax' => [
                'callback' => [$this, 'dialogClose'],
                'effect' => 'fade',
                
            ],
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (strlen($form_state->getValue('aid')) < 3 || !is_numeric($form_state->getValue('aid'))) {
            $form_state->set('input', $this->t('Error: account value not valid'));
            $form_state->set('error', 1);
        }

        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_accounts', 'a');
            $query->fields('a', ['id']);
            $query->condition('coid', $form_state->getValue('coid'));
            $query->condition('aid', $form_state->getValue('for_class') . $form_state->getValue('aid'));
            $id = $query->execute()->fetchField();
        
            if ($id > 0) {
                $form_state->set('input', $this->t('Error: account already exist'));
                $form_state->set('error', 2);
            }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {}

    /**
     * {@inheritdoc}
     */
    public function formCallback(array &$form, FormStateInterface $form_state) {


        $response = new AjaxResponse();
        $clear = new InvokeCommand('.alert', "html", [""]);
        $response->addCommand($clear);

        if($form_state->get('error')) {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $form_state->get('input') . "</div>"));
            return $response;
        } 

            $aid = $form_state->getValue('for_class') . $form_state->getValue('aid');
            $aname = \Drupal\Component\Utility\Xss::filter($form_state->getValue('aname'));

            $fields = array(
                'aid' => $aid,
                'aname' => $aname,
                'atype' => 'detail',
                'astatus' => 1,
                'coid' => $form_state->getValue('coid'),
                'balance' => 0,
                'balance_base' => 0,
                'balance_date' => date('Y-m-d')
            );
            $insert = Database::getConnection('external_db', 'external_db')->insert('ek_accounts')->fields($fields)->execute();

            if ($insert) {
                $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" 
                .  $this->t('Account created') . ': ' . $aid . ' ' . $aname . '. ' . $this->t('Refresh list to view.') . "</div>"));
            }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }
}
