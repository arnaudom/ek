<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\ProjectFieldEdit
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
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
        $pcode = Database::getConnection('external_db', 'external_db')
                        ->query('SELECT pcode FROM {ek_project} WHERE id=:id', array(':id' => $id))->fetchField();
        $form['pcode'] = array(
            '#type' => 'hidden',
            '#default_value' => $pcode,
        );

        $form['for_id'] = array(
            '#type' => 'hidden',
            '#default_value' => $id,
        );

        $form['field'] = array(
            '#type' => 'hidden',
            '#default_value' => $field,
        );

        switch ($field) {

            case 'owner':
                $query = "SELECT owner FROM {ek_project} WHERE id=:id";
                $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
                $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(1);
                $list = array();

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => $users,
                    '#title' => t('Users'),
                    '#default_value' => $data
                );
                break;

            case 'client_id':

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => \Drupal\ek_address_book\AddressBookData::addresslist(1),
                    '#title' => t('client'),
                    '#default_value' => ''
                );
                break;

            case 'pname':
                $query = "SELECT pname FROM {ek_project} WHERE id=:id";
                $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();

                $form['value'] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlenght' => 150,
                    '#title' => t('Project name'),
                    '#default_value' => $data,
                );

                break;

            case 'status':
                $query = "SELECT status FROM {ek_project} WHERE id=:id";
                $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => array('open' => t('open'), 'awarded' => t('awarded'), 'completed' => t('completed'), 'closed' => t('closed')),
                    '#title' => t('status'),
                    '#default_value' => $data
                );
                break;

            case 'priority':
                $query = "SELECT priority FROM {ek_project} WHERE id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => array('1' => t('low'), '2' => t('medium'), '3' => t('high')),
                    '#title' => t('priority'),
                    '#default_value' => $data
                );
                break;

            case 'submission':
            case 'deadline':
            case 'start_date':
            case 'validation':
            case 'completion':
                $query = "SELECT " . $field . " FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['date'] = array(
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => t('date'),
                    '#default_value' => $data,
                );

                break;

            case 'perso_1':
            case 'perso_2':
            case 'perso_2':
                $query = "SELECT " . $field . " FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();
                $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(1);
                $list = array();
                foreach ($users as $uid => $name) {
                    if (ProjectData::validate_access($id, $uid)) {
                        $list[$name] = $name;
                    }
                }

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => $list,
                    '#title' => t('Users'),
                    '#default_value' => $data
                );
                break;

            case 'repo_1':
            case 'repo_2':
            case 'repo_3':
                $query = "SELECT " . $field . " FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();
                $query = "SELECT cid FROM {ek_project} WHERE id=:id";
                $p = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchObject();
                //$query = 'SELECT uid,name FROM {users_field_data} order by name';

                $dir = "private://projects/data/" . $p->cid . "/project_responsibility.txt";
                if (file_exists($dir)) {
                    
                } else {
                    $dir = drupal_get_path('module', 'ek_projects') . '/project_responsibility.txt';
                }
                $list = explode(',', file_get_contents($dir));

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => array_combine($list, $list),
                    '#title' => t('Responsibility'),
                    '#default_value' => $data
                );
                break;

            case 'task_1':
            case 'task_2':
            case 'task_3':
                $query = "SELECT " . $field . " FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['task'] = array(
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#title' => t('task'),
                    '#default_value' => $data,
                );

                break;

            case 'project_description':
            case 'project_comment':
                $query = "SELECT " . $field . " FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();
                $title = explode('_', $field);
                $form['text'] = array(
                    '#type' => 'textarea',
                    '#title' => t($title[1]),
                    '#default_value' => $data
                );
                break;

            case 'first_ship':
            case 'second_ship':
            case 'third_ship':
            case 'four_ship':
            case 'last_delivery':

                $query = "SELECT " . $field . " FROM {ek_project_shipment} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => t('date'),
                    '#default_value' => $data,
                );
                break;
            case 'supplier_offer':

                $query = "SELECT supplier_offer FROM {ek_project_description} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $supplier = AddressBookData::addresslist(2);

                $form['value'] = array(
                    '#type' => 'select',
                    '#size' => 1,
                    '#multiple' => true,
                    '#options' => $supplier,
                    '#title' => t('suppliers'),
                    '#default_value' => explode(',', $data),
                    '#attributes' => array('class' => ['form-select-chosen']),
                    '#attached' => array(
                        'library' => array('ek_admin/ek_admin_chosen'),
                    ),
                );
                break;

            case 'ship_status':

                $query = "SELECT " . $field . " FROM {ek_project_shipment} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#title' => t('status'),
                    '#default_value' => $data,
                );
                break;

            case 'currency':
                $query = "SELECT " . $field . " FROM {ek_project_finance} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'select',
                    '#options' => CurrencyData::listcurrency(1),
                    '#title' => t('currency'),
                    '#default_value' => $data
                );
                break;

            case 'tender_offer':
            case 'project_amount':
            case 'purchase_value':
            case 'payment':
            case 'discount_offer':
                $query = "SELECT " . $field . " FROM {ek_project_finance} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'textfield',
                    '#size' => 15,
                    '#title' => t('value'),
                    '#default_value' => $data,
                );

                break;

            case 'payment_terms':
            case 'incoterm':
            case 'lc_revision':
            case 'lc_status':
                $query = "SELECT " . $field . " FROM {ek_project_finance} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['value'] = array(
                    '#type' => 'textfield',
                    '#size' => 20,
                    '#title' => t('description'),
                    '#default_value' => $data,
                );

                break;

            case 'offer_validity':
            case 'offer_delivery':
            case 'lc_expiry':
                $query = "SELECT " . $field . " FROM {ek_project_finance} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['date'] = array(
                    '#type' => 'date',
                    '#id' => 'edit-date',
                    '#size' => 11,
                    '#title' => t('date'),
                    '#default_value' => $data,
                );

                break;

            case 'comment':
                $query = "SELECT " . $field . " FROM {ek_project_finance} d "
                        . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE p.id=:id";
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))
                        ->fetchField();

                $form['text'] = array(
                    '#type' => 'textarea',
                    '#title' => t('comment'),
                    '#default_value' => $data
                );
                break;
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['btn'] = array(
            '#id' => 'confirmbutton',
            '#type' => 'submit',
            '#value' => t('Save'),
            '#attributes' => array('class' => array('use-ajax-submit')),
        );

        $form['#attached']['library'][] = 'ek_projects/ek_projects_css';


        if ($form_state->get('message') != '') {
            $form['message'] = array(
                '#markup' => "<div class='red'>" . t('Data') . ": " . $form_state->get('message') . "</div>",
            );

            $form_state->set('message', '');
            $form_state->setRebuild();
        }

        if ($form_state->get('error') == '0') {
            $response = new AjaxResponse();
            $response->addCommand(new CloseDialogCommand());
            return $response;
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
        switch ($form_state->getValue('field')) {

            case 'status':
            case 'owner':
            case 'client_id':
            case 'priority':

                $fields = array(
                    $form_state->getValue('field') => $form_state->getValue('value')
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')->fields($fields)
                        ->condition('id', $form_state->getValue('for_id'))
                        ->execute();
                $value = $form_state->getValue('value');
                break;

            case 'pname':

                $fields = array(
                    'pname' => Xss::filter($form_state->getValue('value'))
                );
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

                $text = Xss::filter($form_state->getValue('date'));
                $fields = array(
                    $form_state->getValue('field') => $text
                );
                if (DateTime::createFromFormat('Y-m-d', $text)) {
                    $update = Database::getConnection('external_db', 'external_db')
                                    ->update('ek_project_description')
                                    ->fields($fields)
                                    ->condition('pcode', $form_state->getValue('pcode'))->execute();
                }
                $value = $text;

                break;

            case 'perso_1':
            case 'perso_2':
            case 'perso_2':
            case 'repo_1':
            case 'repo_2':
            case 'repo_3':
                $fields = array(
                    $form_state->getValue('field') => $form_state->getValue('value')
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = $form_state->getValue('value');

                break;

            case 'task_1':
            case 'task_2':
            case 'task_3':

                $text = Xss::filter($form_state->getValue('task'));
                $fields = array(
                    $form_state->getValue('field') => $text
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;


            case 'project_description':
            case 'project_comment':

                $text = Xss::filter($form_state->getValue('text')) . ' [' . \Drupal::currentUser()->getAccountName() . '] - ' . date('Y-m-d');
                $fields = array(
                    $form_state->getValue('field') => $text
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_description')
                        ->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;

            case 'supplier_offer':

                $fields = array(
                    'supplier_offer' => implode(',', $form_state->getValue('value'))
                );
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
                $fields = array(
                    $form_state->getValue('field') => $text
                );
                if (DateTime::createFromFormat('Y-m-d', $text) || $form_state->getValue('field') == 'ship_status') {
                    $update = Database::getConnection('external_db', 'external_db')
                                    ->update('ek_project_shipment')->fields($fields)
                                    ->condition('pcode', $form_state->getValue('pcode'))->execute();
                }
                $value = $text;
                break;


            case 'currency':
                $fields = array(
                    $form_state->getValue('field') => $form_state->getValue('value')
                );
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
                    $fields = array(
                        $form_state->getValue('field') => Xss::filter($form_state->getValue('value'))
                    );
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
                $fields = array(
                    $form_state->getValue('field') => $text
                );
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
                $fields = array(
                    $form_state->getValue('field') => $text
                );
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
                $fields = array(
                    $form_state->getValue('field') => $text
                );
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_finance')->fields($fields)
                        ->condition('pcode', $form_state->getValue('pcode'))
                        ->execute();
                $value = '';
                break;
        }

        if ($update) {
            $action = 'edit' . ' ' . str_replace('_', " ", $form_state->getValue('field'));
            $fields = array(
                'pcode' => $form_state->getValue('pcode'),
                'uid' => \Drupal::currentUser()->id(),
                'stamp' => time(),
                'action' => $action
            );
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_tracker')
                    ->fields($fields)
                    ->execute();


            $form_state->set('message', t('saved'));
            $form_state->set('error', 0);
            $form_state->setRebuild();

            $param = serialize(
                    array(
                        'id' => $form_state->getValue('for_id'),
                        'field' => $form_state->getValue('field'),
                        'value' => $value,
                        'pcode' => $form_state->getValue('pcode')
                    )
            );
            ProjectData::notify_user($param);
        } else {
            $form_state->set('error', 1);
            $form_state->set('message', t('error'));
            $form_state->setRebuild();
        }
    }

}
