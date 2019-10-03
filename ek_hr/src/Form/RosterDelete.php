<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\RosterDelete.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

/**
 * Provides a form to delete roster data
 */
class RosterDelete extends FormBase {


    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'roster_delete';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {


            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_hr_workforce_roster', 'r');
            $query->fields('r',['period']);
            $query->distinct();
            $data = $query->execute();
            
            $like = [];
            
            while($d = $data->fetchField()) {
                
                $parts = explode('-',$d);
                $like[$parts[0] . '-' . $parts[1]] = $parts[0] . '-' . $parts[1];
                
            }
            
            $like = array_unique($like);
            
            $form['content'] = [
                '#type' => 'checkboxes',
                '#options' => $like,
                '#title' => t('Select date(s) to delete (month-year)') . ':'
                
            ];
            
            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
                '#suffix' => ''
            );


            $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
        

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

        foreach($form_state->getValue('content') as $k => $v) {
            if($v != 0) {
                $v = $v . '%';
                Database::getConnection('external_db', 'external_db')
                            ->delete('ek_hr_workforce_roster')
                            ->condition('period', $v, 'LIKE')
                            ->execute();
                
                $flag = 1;
            }
            
        }
        if($flag == 1) {
            \Drupal::messenger()->addStatus(t('Roster deleted'));
        }
        
        
    }

}
