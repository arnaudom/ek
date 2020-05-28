<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\Transfer.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to transfer project data from one user to another
 */
class Transfer extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
      The module handler.
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
        return 'projects_transfer';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $form['username'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#required' => true,
            '#default_value' => $form_state->getValue('username') ? $form_state->getValue('username') : null,
            '#attributes' => array('placeholder' => $this->t('Enter user name')),
            '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
            '#prefix' => '<div class="container-inline">',
        );


        $form['next'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Select'),
            '#suffix' => '</div>',
        );


        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);

            //verify any data to post
            $query = "SELECT p.id,pname,pcode,name FROM {ek_project} p INNER JOIN {ek_country} c ON p.cid=c.id WHERE owner = :o";
            $projects = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':o' => $form_state->getValue('uid')));
            $projects->allowRowCount = true;
            if ($projects->rowcount() < 1) {
                $form['info'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('There is no data to transfer for this user'),
                );
            } else {
                $form['list'] = array(
                    '#type' => 'details',
                    '#title' => $this->t('List'),
                    '#open' => true,
                    '#attributes' => array('class' => array()),
                );


                $header = [
                    'pcode' => $this->t('Reference'),
                    'pname' => $this->t('Name'),
                    'cid' => $this->t('Country'),
                ];

                $options = [];

                while ($p = $projects->fetchObject()) {
                    $options[$p->id] = ['pcode' => $p->pcode, 'pname' => $p->pname, 'cid' => $p->name];
                }

                $form['list']['table'] = array(
                    '#type' => 'tableselect',
                    '#header' => $header,
                    '#options' => $options,
                    '#empty' => '',
                );

                $form['actions'] = array(
                    '#type' => 'actions',
                    '#attributes' => array('class' => array('container-inline')),
                );

                $form['tousername'] = array(
                    '#type' => 'textfield',
                    '#title' => $this->t('Transfer to'),
                    '#size' => 50,
                    '#required' => true,
                    '#default_value' => null,
                    '#attributes' => array('placeholder' => $this->t('Enter user name')),
                    '#autocomplete_route_name' => 'ek_admin.user_autocomplete',
                );

                $form['actions']['submit'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Confirm transfer'),
                    '#suffix' => ''
                );
            }
        }//if stp 2






        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {

            //$query = "SELECT uid FROM {users_field_data} WHERE name = :n";
            //$data = db_query($query, [':n' => $form_state->getValue('username')])
            //        ->fetchField();
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid']);
            $query->condition('name', $form_state->getValue('username'));
            $data = $query->execute()->fetchField();

            if ($data) {
                $form_state->setValue('uid', $data);
                $form_state->set('step', 2);
                $form_state->setRebuild();
            } else {
                $form_state->setErrorByName('username', $this->t('Unknown user'));
            }
        }

        if ($form_state->get('step') == 3) {
            if ($form_state->getValue('tousername') == $form_state->getValue('username')) {
                $form_state->setErrorByName('tousername', $this->t('Same user selected'));
            }

            //$query = "SELECT uid FROM {users_field_data} WHERE name = :n";
            //$data = db_query($query, [':n' => $form_state->getValue('tousername')])
            //        ->fetchField();
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid']);
            $query->condition('name', $form_state->getValue('tousername'));
            $data = $query->execute()->fetchField();
            if ($data) {
                $form_state->setValue('touid', $data);
            } else {
                $form_state->setErrorByName('tousername', $this->t('Unknown user'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {
            $i = 0;
            foreach ($form_state->getValue('table') as $id => $val) {
                if ($val != 0) {
                    $i++;
                    Database::getConnection('external_db', 'external_db')
                            ->update('ek_project')
                            ->fields(['owner' => $form_state->getValue('touid')])
                            ->condition('id', $val)
                            ->execute();
                }
            }

            if ($i > 0) {
                \Drupal::messenger()->addStatus(t('@n project(s) transferred to @u', ['@n' => $i, '@u' => $form_state->getValue('tousername')]));
            } else {
                \Drupal::messenger()->addWarning(t('No project to transfer'));
            }
        }//step 3
    }

}
