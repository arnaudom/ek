<?php

/**
 * @file
 * Contains \Drupal\ek_intelligence\Form\NewReport.
 */

namespace Drupal\ek_intelligence\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\ProjectData;
use Drupal\ek_address_book\AddressBookData;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to create new business report.
 */
class NewReport extends FormBase {

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
        return 'ek_intelligence_report_new';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['assign'] = array(
            '#type' => 'textfield',
            '#attributes' => array('placeholder' => $this->t('user assignment')),
            '#required' => true,
            '#title' => $this->t('Assignment'),
            '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
        );

        $form['type'] = array(
            '#type' => 'select',
            '#options' => array(1 => $this->t('briefing'), 2 => $this->t('report'), 3 => $this->t('training')),
            '#required' => true,
            '#title' => $this->t('Type'),
        );

        $form['description'] = array(
            '#type' => 'textfield',
            '#default_value' => '',
            '#title' => $this->t('Description'),
        );

        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => AccessCheck::CompanyListByUid(),
            '#title' => $this->t('Company'),
            '#required' => true,
        );

        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $form['pcode'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => ProjectData::listprojects(0),
                '#title' => $this->t('Project'),
            );
        } // project
        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $form['abid'] = array(
                '#type' => 'textfield',
                '#size' => 50,
                '#title' => $this->t('Client'),
                '#attributes' => array('placeholder' => $this->t('optional client ref.')),
                '#autocomplete_route_name' => 'ek.look_up_contact_ajax',
            );
        } // address book

        $form['email'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Notify via email'),
        );

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Register report'),
        );





        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $users = explode(',', $form_state->getValue('assign'));
        $error = '';
        $list = [];
        foreach ($users as $u) {
            if (trim($u) != null) {
                //check it is a registered user
                $query = Database::getConnection()->select('users_field_data', 'u');
                $query->fields('u', ['uid']);
                $query->condition('name', $u);
                $id = $query->execute()->fetchField();
                //$query = "SELECT uid from {users_field_data} WHERE name=:u";
                //$id = db_query($query, array(':u' => $u))->FetchField();
                if (!$id) {
                    $error .= $u . ' ';
                } else {
                    $list[] = $id;
                }
            }
        }

        if ($error <> '') {
            $form_state->setErrorByName('assign', $this->t('Invalid user(s)') . ': ' . $error);
        } else {
            $form_state->setValue('assign', $list);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $description = Xss::filter($form_state->getValue('description'));
        $pcode = '';
        if ($form_state->getValue('pcode') != '') {
            $pcode = $form_state->getValue('pcode');
        }
        $query = "SELECT count(id) as id FROM {ek_ireports}";
        $count = Database::getConnection('external_db', 'external_db')->query($query)
                ->fetchField();
        $type = array(1 => $this->t('BR'), 2 => $this->t('RP'), 3 => $this->t('TR'));
        $count++;
        $serial = $type[$form_state->getValue('type')] . '-' . $id . '-' . date('m') . '_' . date('y') . '-' . $count;
        if ($form_state->getValue('abid') != null) {
            $client = Xss::filter($form_state->getValue('abid'));
            $abid = AddressBookData::getid($client);
        }


        $fields = array(
            'serial' => $serial,
            'owner' => \Drupal::currentUser()->id(),
            'assign' => implode(',', $form_state->getValue('assign')),
            'edit' => date('U'),
            'description' => $description,
            'status' => 1,
            'pcode' => $pcode,
            'month' => date('m'),
            'year' => date('Y'),
            'coid' => $form_state->getValue('coid'),
            'abid' => $abid,
            'type' => $form_state->getValue('type'),
        );

        $result = Database::getConnection('external_db', 'external_db')->insert('ek_ireports')
                ->fields($fields)
                ->execute();


        if ($result) {
            if ($form_state->getValue('email') == 1) {
                $params = [
                    'subject' => $subject,
                    'body' => $body,
                    'from' => $currentuserMail,
                ];
                $assigned = $form_state->getValue('assign');
                foreach ($assigned as $key => $id) {
                    //$query = "SELECT mail from {users_field_data} WHERE name=:n";
                    //$email = db_query($query, array(':n' => trim($name) ))->fetchField();
                    $account = \Drupal\user\Entity\User::load($id);
                    if ($account) {
                        $send = \Drupal::service('plugin.manager.mail')->mail(
                                'ek_intelligence', 'notify_message', $account->getEmail(), $account->getPreferredLangcode(), $params, \Drupal::currentUser()->getEmail(), true
                        );
                        if ($send['result'] == false) {
                            $error .= $email . ' ';
                        }
                    }
                }


                if ($error != '') {
                    \Drupal::messenger()->addError(t('Error sending email to @m', ['@m' => $error]));
                } else {
                    \Drupal::messenger()->addStatus(t('Message sent'));
                }
            }

            $form_state->setRedirect('ek_intelligence.report');
        }
    }

}
