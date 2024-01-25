<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\ProjectFieldEdit
 */

namespace Drupal\ek_projects\Form;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use DateTime;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_finance\CurrencyData;
use Drupal\ek_address_book\AddressBookData;

/**
 * Provides a form to edit fields in project page.
 */
class ProjectFieldEdit extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_edit_fileds';
    }

    /**
     * {@inheritdoc}
     * @param id : the project id
     * @param field : the field to be updated
     *
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $field = null) {
        $query = Database::getConnection('external_db', 'external_db')
            ->select('ek_project', 'p');
        $query->fields('p');
        $query->condition('p.id', $id);
        $query->leftJoin('ek_project_description', 'd', 'p.pcode = d.pcode');
        $query->fields('d');
        $query->leftJoin('ek_project_shipment', 's', 'p.pcode = s.pcode');
        $query->fields('s');
        $query->leftJoin('ek_project_finance', 'f', 'p.pcode = f.pcode');
        $query->fields('f');
        $data = $query->execute()->fetchObject();
        $form['pcode'] = [
            '#type' => 'hidden',
            '#default_value' => $data->pcode,
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#default_value' => $id,
        ];

        $form['field'] = [
            '#type' => 'hidden',
            '#default_value' => $field,
        ];

        switch ($field) {

            case 'owner':
                $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(1);
                $list = [];

                $form['value'] = [
                    '#type' => 'select',
                    '#options' => $users,
                    '#title' => $this->t('Users'),
                    '#default_value' => $data->owner
                ];
                break;

            case 'client_id':

                $form['value'] = [
                    '#type' => 'select',
                    '#options' => \Drupal\ek_address_book\AddressBookData::addresslist(1),
                    '#title' => $this->t('client'),
                    '#default_value' => ''
                ];
                break;

            case 'pname':

                $form['value'] = [
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlenght' => 150,
                    '#title' => $this->t('Project name'),
                    '#default_value' => $data->pname,
                ];

                break;

            case 'status':

                $form['value'] = [
                    '#type' => 'select',
                    '#options' => array('open' => $this->t('open'), 'awarded' => $this->t('awarded'), 'completed' => $this->t('completed'), 'closed' => $this->t('closed')),
                    '#title' => $this->t('status'),
                    '#default_value' => $data->status
                ];
                break;

            case 'priority':
                $form['value'] = [
                    '#type' => 'select',
                    '#options' => array('1' => $this->t('low'), '2' => $this->t('medium'), '3' => $this->t('high')),
                    '#title' => $this->t('priority'),
                    '#default_value' => $data->priority
                ];
                break;

            case 'submission':
            case 'deadline':
            case 'start_date':
            case 'validation':
            case 'completion':
                $form['date'] = [
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => $this->t('date'),
                    '#default_value' => $data->$field,
                ];

                break;

            case 'perso_1':
            case 'perso_2':
            case 'perso_2':
                $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(1);
                $list = [];
                foreach ($users as $uid => $name) {
                    if (ProjectData::validate_access($id, $uid)) {
                        $list[$name] = $name;
                    }
                }

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => $list,
                    '#title' => $this->t('Users'),
                    '#default_value' => $data->$field
                );
                break;

            case 'repo_1':
            case 'repo_2':
            case 'repo_3':
                $dir = "private://projects/data/" . $data->cid . "/project_responsibility.txt";
                if (file_exists($dir)) {
                    
                } else {
                    $dir = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_projects') . '/project_responsibility.txt';
                }
                $list = explode(',', file_get_contents($dir));

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => array_combine($list, $list),
                    '#title' => $this->t('Responsibility'),
                    '#default_value' => $data->$field
                );
                break;

            case 'task_1':
            case 'task_2':
            case 'task_3':

                $form['task'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#title' => $this->t('task'),
                    '#default_value' => $data->$field,
                ];

                break;

            case 'project_description':
            case 'project_comment':
                $title = explode('_', $field);
                $form['text'] = array(
                    '#type' => 'textarea',
                    '#title' => t($title[1]),
                    '#default_value' => $data->$field
                );
                break;

            case 'first_ship':
            case 'second_ship':
            case 'third_ship':
            case 'four_ship':
            case 'last_delivery':

                $form['value'] = [
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => $this->t('date'),
                    '#default_value' => $data->field,
                ];
                break;

            case 'supplier_offer':
                $supplier = AddressBookData::addresslist(2);

                $form['value'] = [
                    '#type' => 'select',
                    '#size' => 1,
                    '#multiple' => true,
                    '#options' => $supplier,
                    '#title' => $this->t('suppliers'),
                    '#default_value' => explode(',', $data->supplier_offer),
                    '#attributes' => ['class' => ['form-select-chosen']],
                    '#attached' => ['library' => ['ek_admin/ek_admin_chosen'],],
                ];
                break;

            case 'ship_status':

                $form['value'] = [
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#title' => $this->t('status'),
                    '#default_value' => $data->ship_status,
                ];
                break;

            case 'currency':
                $form['value'] = [
                    '#type' => 'select',
                    '#options' => CurrencyData::listcurrency(1),
                    '#title' => $this->t('currency'),
                    '#default_value' => $data->currency
                ];
                break;

            case 'tender_offer':
            case 'project_amount':
            case 'purchase_value':
            case 'payment':
            case 'discount_offer':

                $form['value'] = [
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#title' => $this->t('value'),
                    '#default_value' => $data->$field,
                ];

                break;

            case 'payment_terms':
            case 'incoterm':
            case 'lc_revision':
            case 'lc_status':

                $form['value'] = [
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#title' => $this->t('description'),
                    '#default_value' => $data->$field,
                ];

                break;

            case 'offer_validity':
            case 'offer_delivery':
            case 'lc_expiry':

                $form['date'] = [
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => $this->t('date'),
                    '#default_value' => $data->$field,
                ];

                break;

            case 'comment':

                $form['text'] = array(
                    '#type' => 'textarea',
                    '#title' => $this->t('comment'),
                    '#default_value' => $data->comment,
                );
                break;
        }

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

        $form['#attached']['library'][] = 'ek_projects/ek_projects_css';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {}

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
        
        switch ($form_state->getValue('field')) {

            case 'status':
            case 'owner':
            case 'client_id':
            case 'priority':

                $fields = [ $form_state->getValue('field') => $form_state->getValue('value')];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
                $value = $form_state->getValue('value');
                break;

            case 'pname':

                $fields = ['pname' => Xss::filter($form_state->getValue('value'))];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
                $value = $form_state->getValue('value');
                break;

            case 'submission':
            case 'deadline':
            case 'start_date':
            case 'validation':
            case 'completion':
                $fields = [$form_state->getValue('field') => $form_state->getValue('date')];
                if (DateTime::createFromFormat('Y-m-d', $form_state->getValue('date'))) {
                    $update = Database::getConnection('external_db', 'external_db')
                                    ->update('ek_project_description')
                                    ->fields($fields)
                                    ->condition('pcode', $form_state->getValue('pcode'))->execute();
                }
                $value = $form_state->getValue('field');

                break;

            case 'perso_1':
            case 'perso_2':
            case 'perso_2':
            case 'repo_1':
            case 'repo_2':
            case 'repo_3':
                $fields = [$form_state->getValue('field') => $form_state->getValue('value')];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = $form_state->getValue('value');

                break;

            case 'task_1':
            case 'task_2':
            case 'task_3':

                $text = Xss::filter($form_state->getValue('task'));
                $fields = [$form_state->getValue('field') => $text];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;


            case 'project_description':
            case 'project_comment':

                $text = Xss::filter($form_state->getValue('text')) . ' [' . \Drupal::currentUser()->getAccountName() . '] - ' . date('Y-m-d');
                $fields = [$form_state->getValue('field') => $text];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;

            case 'supplier_offer':

                $fields = ['supplier_offer' => implode(',', $form_state->getValue('value'))];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';


                break;


            case 'first_ship':
            case 'second_ship':
            case 'third_ship':
            case 'four_ship':
            case 'last_delivery':
            case 'ship_status':

                $text = Xss::filter($form_state->getValue('value'));
                $fields = [$form_state->getValue('field') => $text];
                if (DateTime::createFromFormat('Y-m-d', $text) || $form_state->getValue('field') == 'ship_status') {
                    $update = Database::getConnection('external_db', 'external_db')
                                    ->update('ek_project_shipment')->fields($fields)
                                    ->condition('pcode', $form_state->getValue('pcode'))->execute();
                }
                $value = $text;
                break;


            case 'currency':
                $fields = [$form_state->getValue('field') => $form_state->getValue('value')];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_finance')->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = $form_state->getValue('value');
                break;

            case 'tender_offer':
            case 'project_amount':
            case 'purchase_value':
            case 'payment':
            case 'discount_offer':
                if (is_numeric($form_state->getValue('value'))) {
                    $fields =[$form_state->getValue('field') => Xss::filter($form_state->getValue('value'))];
                    $update = Database::getConnection('external_db', 'external_db')
                            ->update('ek_project_finance')->fields($fields)
                            ->condition('pcode', $form_state->getValue('pcode'))
                            ->execute();
                    $value = $form_state->getValue('value');
                }
                break;

            case 'payment_terms':
            case 'incoterm':
            case 'lc_revision':
            case 'lc_status':
                $text = Xss::filter($form_state->getValue('value'));
                $fields = [$form_state->getValue('field') => $text];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_finance')
                        ->fields($fields)->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = $text;
                break;


            case 'offer_validity':
            case 'offer_delivery':
            case 'lc_expiry':

                $text = Xss::filter($form_state->getValue('date'));
                $fields = [$form_state->getValue('field') => $text];
                if (DateTime::createFromFormat('Y-m-d', $text)) {
                    $update = Database::getConnection('external_db', 'external_db')
                            ->update('ek_project_finance')->fields($fields)
                            ->condition('pcode', $form_state->getValue('pcode'))
                            ->execute();
                }
                $value = $text;
                break;

            case 'comment':
                $text = Xss::filter($form_state->getValue('text')) . ' [' . \Drupal::currentUser()->getAccountName() . '] - ' . date('Y-m-d');
                $fields = [$form_state->getValue('field') => $text];
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_finance')->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;
        }

        if ($update) {
            $action = 'edit' . ' ' . str_replace('_', " ", $form_state->getValue('field'));
            $fields = [
                'pcode' => $form_state->getValue('pcode'),
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => $action
            ];
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_tracker')
                    ->fields($fields)
                    ->execute();

            $param = serialize(
                    array(
                        'id' => $form_state->getValue('for_id'),
                        'field' => $form_state->getValue('field'),
                        'value' => $value,
                        'pcode' => $form_state->getValue('pcode')
                    )
            );
            ProjectData::notify_user($param);
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--status'>" . $this->t('saved') . "</div>"));
        } else {
            $response->addCommand(new AppendCommand('.alert', "<div class='messages messages--error'>" . $this->t('error') . "</div>"));
        }
        return $response;
    }

    public function dialogClose() {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand('#drupal-modal'));
        return $response;
    }
}
