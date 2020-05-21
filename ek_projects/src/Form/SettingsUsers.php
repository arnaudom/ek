<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SettingsUsers
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_projects\ProjectData;

/**
 * Provides a form to record access control to sections (1to5)
 */
class SettingsUsers extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_edit_access_section';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $query = "SELECT settings from {ek_project_settings} WHERE coid=:c";
        $settings = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => 0])->fetchField();
        $s = unserialize($settings);

        $form['access_level'] = array(
            '#type' => 'checkbox',
            '#title' => t('Block file access level at page level'),
            '#default_value' => ($s['access_level'] == 1) ? 1 : 0,
        );

        //$users = db_query('SELECT uid,name,status FROM {users_field_data} WHERE uid>:u order by name', array(':u' => 0));
        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);

        $headerline = "<div class='table'  id='users_items'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . t("Login") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . t("Section 1") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . t("Section 2") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . t("Section 3") . "</div>
                      <div class='cell cellborder' id='tour-item5'>" . t("Section 4") . "</div>
                      <div class='cell cellborder' id='tour-item6'>" . t("Section 5") . "</div>
                   ";


        $header = [
            'login' => $this->t('Login'),
            's1' => $this->t('Section 1'),
            's2' => $this->t('Section 2'),
            's3' => $this->t('Section 3'),
            's4' => $this->t('Section 4'),
            's5' => $this->t('Section 5'),
        ];
        $form['list'] = array(
            '#type' => 'table',
            '#caption' => ['#markup' => '<h2>' . $this->t('Access') . '</h2>'],
            '#header' => $header,
        );
        $options = [];

        foreach ($users as $uid => $name) {
            $acc = \Drupal\user\Entity\User::load($uid);
            $status = ($acc->isBlocked()) ? ' (' . t('Blocked') . ')' : '';
            /**/
            $form['list'][$uid]['user'] = array(
                '#type' => 'item',
                '#markup' => '[' . $uid . '] ' . $name . $status,
            );

            $access = ProjectData::validate_section_access($uid);

            $form['list'][$uid]['s1'] = array(
                '#type' => 'checkbox',
                '#default_value' => in_array(1, $access) ? 1 : 0,
            );

            $form['list'][$uid]['s2'] = array(
                '#type' => 'checkbox',
                '#default_value' => in_array(2, $access) ? 1 : 0,
            );

            $form['list'][$uid]['s3'] = array(
                '#type' => 'checkbox',
                '#default_value' => in_array(3, $access) ? 1 : 0,
            );

            $form['list'][$uid]['s4'] = array(
                '#type' => 'checkbox',
                '#default_value' => in_array(4, $access) ? 1 : 0,
            );

            $form['list'][$uid]['s5'] = array(
                '#type' => 'checkbox',
                '#default_value' => in_array(5, $access) ? 1 : 0,
            );
        }

        $form['#tree'] = true;


        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );
        $form['actions']['access'] = array(
            '#id' => 'accessbutton',
            '#type' => 'submit',
            '#value' => t('Save'),
        );

        $form['#attached']['library'][] = 'ek_projects/ek_projects_css';

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
        $query = "SELECT settings from {ek_project_settings} WHERE coid=:c";
        $settings = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => 0])->fetchField();
        $s = unserialize($settings);

        $s['access_level'] = $form_state->getValue('access_level');
        Database::getConnection('external_db', 'external_db')
                ->update('ek_project_settings')
                ->condition('coid', 0)
                ->fields(['settings' => serialize($s)])
                ->execute();

        $n = 0;
        $i = 0;

        foreach ($form_state->getValue('list') as $key => $data) {
            $query = 'SELECT uid from {ek_project_users} WHERE uid=:u';
            $uid = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':u' => $key))
                    ->fetchField();
            $fields = [
                'section_1' => $data['s1'],
                'section_2' => $data['s2'],
                'section_3' => $data['s3'],
                'section_4' => $data['s4'],
                'section_5' => $data['s5'],
            ];

            if ($uid) {
                $update = Database::getConnection('external_db', 'external_db')
                        ->update('ek_project_users')
                        ->fields($fields)
                        ->condition('uid', $key)
                        ->execute();
                if ($update) {
                    $n++;
                }
            } else {
                $fields['uid'] = $key;
                $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_project_users')
                        ->fields($fields)
                        ->execute();
                if ($insert) {
                    $i++;
                }
            }
        }

        \Drupal::messenger()->addStatus(t("Updated @n, inserted @i user(s)", ['@n' => $n, '@i' => $i]));
    }

}
