<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SettingsUsers
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
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
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $s = unserialize($settings);

        $form['access_level'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Block file access level at page level'),
            '#default_value' => ($s['access_level'] == 1) ? 1 : 0,
        );
        
        if (isset($s['sections'])) {
            $s1 = $s['sections']['s1'];
            $s2 = $s['sections']['s2'];
            $s3 = $s['sections']['s3'];
            $s4 = $s['sections']['s4'];
            $s5 = $s['sections']['s5'];
        } else {
            $s1 = $this->t("Section 1");
            $s2 = $this->t("Section 2");
            $s3 = $this->t("Section 3");
            $s4 = $this->t("Section 4");
            $s5 = $this->t("Section 5");
        }

        $users = \Drupal\ek_admin\Access\AccessCheck::listUsers(0);
        $header = [
            'login' => $this->t('Login'),
            's1' => $this->t('Section 1'),
            's2' => $this->t('Section 2'),
            's3' => $this->t('Section 3'),
            's4' => $this->t('Section 4'),
            's5' => $this->t('Section 5'),
        ];
        $form['list'] = [
            '#type' => 'table',
            '#caption' => ['#markup' => '<h2>' . $this->t('Access') . '</h2>'],
            '#header' => $header,
        ];
        
        $form['list']['sections']['s0'] = [
                '#type' => 'item',
                '#markup' => $this->t('Section custom name'),
            ];
        $form['list']['sections']['s1'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 100,
                '#default_value' => $s1,
                '#required' => true,
            ];
        $form['list']['sections']['s2'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 100,
                '#default_value' => $s2,
                '#required' => true,
            ];
        $form['list']['sections']['s3'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 100,
                '#default_value' => $s3,
                '#required' => true,
            ];
        $form['list']['sections']['s4'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 100,
                '#default_value' => $s4,
                '#required' => true,
            ];
        $form['list']['sections']['s5'] = [
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 100,
                '#default_value' => $s5,
                '#required' => true,
            ];
        
        foreach ($users as $uid => $name) {
            $acc = \Drupal\user\Entity\User::load($uid);
            $status = ($acc->isBlocked()) ? ' (' . $this->t('Blocked') . ')' : '';
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
            '#value' => $this->t('Save'),
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
        
        $n = 0;
        $i = 0;
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_users', 'u'); 
        $query->fields('u', ['uid']);
        $uids = $query->execute()->fetchCol();

        foreach ($form_state->getValue('list') as $key => $data) {
            
            if($key == 'sections') {
                $sections = [
                    's1' => Xss::filter($data['s1']),
                    's2' => Xss::filter($data['s2']),
                    's3' => Xss::filter($data['s3']),
                    's4' => Xss::filter($data['s4']),
                    's5' => Xss::filter($data['s5']),
                ];
            } else {
                $fields = [
                    'section_1' => $data['s1'],
                    'section_2' => $data['s2'],
                    'section_3' => $data['s3'],
                    'section_4' => $data['s4'],
                    'section_5' => $data['s5'],
                ];

                if (in_array($key, $uids)) {
                    Database::getConnection('external_db', 'external_db')
                            ->update('ek_project_users')
                            ->fields($fields)
                            ->condition('uid', $key)
                            ->execute();
                    $n++;
                    
                } else {
                    $fields['uid'] = $key;
                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_users')
                            ->fields($fields)
                            ->execute();
                    $i++;
                    
                }
            }
        }
        
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $s = unserialize($settings);

        $s['access_level'] = $form_state->getValue('access_level');
        $s['sections'] = $sections;
        Database::getConnection('external_db', 'external_db')
                ->update('ek_project_settings')
                ->condition('coid', 0)
                ->fields(['settings' => serialize($s)])
                ->execute();

        \Drupal::messenger()->addStatus(t("Updated @n, inserted @i user(s)", ['@n' => $n, '@i' => $i]));
    }

}
