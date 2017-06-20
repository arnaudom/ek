<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\Merge.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to merge projects as sub project
 */
class Merge extends FormBase {

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
        return 'projects_merge';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        $form['info'] = array(
            '#type' => 'item',
            '#markup' => $this->t('This process will merge one project as sub project of existing project.'),
        );

        $form['source_project'] = array(
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => true,
            '#default_value' => $form_state->getValue('source_project') ? $form_state->getValue('source_project') : NULL,
            '#attributes' => array('placeholder' => t('Ex. 123')),
            '#title' => t('Project to be merged'),
            '#autocomplete_route_name' => 'ek_look_up_projects',
            '#autocomplete_route_parameters' => array('level' => 'main', 'status' => '0'),
            '#prefix' => '<div class="container-inline">',
        );

        
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Select') ,
                '#suffix' => '</div>',
            );
        

        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);
           

            //get data to post
            $p = explode(' ', $form_state->getValue('source_project'));
            $query = "SELECT p.id,pname,pcode,name FROM {ek_project} p INNER JOIN {ek_country} c ON p.cid=c.id WHERE pcode = :o";
            $project = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':o' => $p[1]))
                    ->fetchObject();
                        
                $form['detail'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('You are merging @s (@n), @c', ['@s' => $project->pcode, '@n' => $project->pname, '@c' => $project->name]),
                );



                $form['destination_project'] = array(
                    '#type' => 'textfield',
                    '#size' => 50,
                    '#maxlength' => 150,
                    '#required' => true,
                    '#default_value' => $form_state->getValue('destination_project') ? $form_state->getValue('destination_project') : NULL,
                    '#attributes' => array('placeholder' => t('Ex. 123')),
                    '#title' => t('Project destination'),
                    '#autocomplete_route_name' => 'ek_look_up_projects',
                    '#autocomplete_route_parameters' => array('level' => 'main', 'status' => '0'),
                    
                );                
        
                $form['actions']['submit'] = array(
                    '#type' => 'submit',
                    '#value' => $this->t('Confirm merge'),
                    '#suffix' => ''
                );
            
        }//if stp 2
       





        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 1) {

            $p = explode(' ', $form_state->getValue('source_project'));
            $query = "SELECT id FROM {ek_project} WHERE pcode = :p AND level =:l";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':p' => $p[1], ':l' => 'Main project'])
                    ->fetchField();
            if ($data) {
                $form_state->setValue('pid', $data);
                $form_state->set('step', 2);
                $form_state->setRebuild();
            } else {
                $form_state->setErrorByName('source_project', $this->t('Unknown project'));
            }
        }
        
        if ($form_state->get('step') == 3) {
            
            if($form_state->getValue('source_project') == $form_state->getValue('destination_project')) {
                $form_state->setErrorByName('destination_project', $this->t('Same project selected'));
            }
            $p = explode(' ', $form_state->getValue('destination_project'));
            $query = "SELECT id FROM {ek_project} WHERE pcode = :p AND level =:l";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':p' => $p[1], ':l' => 'Main project'])
                    ->fetchField();
            if ($data) {
                $form_state->setValue('topid', $data);
            } else {
                $form_state->setErrorByName('destination_project', $this->t('Unknown project'));
            }
        
         
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 3) {
            
            $p1 = explode(' ', $form_state->getValue('source_project'));
            $p2 = explode(' ', $form_state->getValue('destination_project'));
            
            $query = 'SELECT id,subcount FROM {ek_project} WHERE pcode =:p';
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':p' => $p2[1]])
                    ->fetchObject();
            $data->subcount++;
            Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')
                        ->fields(array('subcount' => $data->subcount))
                        ->condition('pcode', $p2[1])->execute();
            
            Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')
                        ->fields(array('level' => 'Sub project', 'main' => $data->id))
                        ->condition('pcode', $p1[1])->execute();

            drupal_set_message(t('@n project merged under @u', ['@n' => $p1[1], '@u' => $p2[1]]), 
                        'status');

            
        }//step 3
    }

}
