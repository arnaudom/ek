<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\Delete.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete a project from database
 */
class Delete extends FormBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
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
        return 'projects_delete';
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
            '#markup' => "<div class='messages messages--warning'>" .  $this->t('This process will delete existing project.') . "</div>",
        );

        $form['source_project'] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => true,
            '#default_value' => $form_state->getValue('source_project') ? $form_state->getValue('source_project') : null,
            '#attributes' => ['placeholder' => $this->t('Ex. 123')],
            '#title' => $this->t('Project to be delete'),
            '#autocomplete_route_name' => 'ek_look_up_projects',
            '#autocomplete_route_parameters' => ['level' => 'main', 'status' => '0'],
            '#prefix' => '<div class="container-inline">',
        ];


        $form['next'] = [
            '#type' => 'submit',
            '#value' => $this->t('Select'),
            '#suffix' => '</div>',
        ];


        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);

            // get data to delete
            $p = explode(' ', $form_state->getValue('source_project'));
            /*$query = "SELECT p.id,pname,pcode,name FROM {ek_project} p INNER JOIN {ek_country} c ON p.cid=c.id WHERE pcode = :o";
            $project = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':o' => $p[1]))
                    ->fetchObject();*/
            $data = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p')
                    ->fields('p');
            $data->leftJoin('ek_country', 'c', 'p.cid=c.id');
            $data->fields('c',['name']);
            $data->condition('pcode', trim($p[1]));
            $project = $data->execute()->fetchObject();
            $txt = $this->t('You are deleting:') . "<br/>";
            $txt .= $this->t('Reference: @p', ['@p' => $project->pcode]) . "<br/>";
            $txt .= $this->t('Name: @p', ['@p' => $project->pname]) . "<br/>";
            $txt .= $this->t('Country: @p', ['@p' => $project->name]) . "<br/>";

            $form['detail'] = [
                '#type' => 'item',
                '#markup' => "<div class='messages messages--warning'> " . $txt . "</div>",
            ];
            
            $form['confirmation'] = [
                '#type' => 'textfield',
                '#size' => 50,
                '#maxlength' => 100,
                '#required' => true,
                '#attributes' => array('placeholder' => $this->t('Ex.') . ": " . $project->pcode ) ,
                '#title' => $this->t('Confirm'),
                '#description' => $this->t('Key-in the full project reference number for confirmation.')
            ];

            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Confirm delete'),
                '#suffix' => ''
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $p = explode(' ', $form_state->getValue('source_project'));
            $data = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p')
                    ->fields('p',['id'])
                    ->condition('pcode', trim($p[1]))
                    ->execute();   
            $pid = $data->fetchField(); 

            if ($pid) {
                $form_state->setValue('pid', $pid);
                $form_state->set('pcode',trim($p[1]));
                $form_state->set('step', 2);                
                $form_state->setRebuild();

            } else {
                $form_state->setErrorByName('source_project', $this->t('Unknown project'));
            }
        }

        if ($form_state->get('step') == 3) {
            if ($form_state->get('pcode') !== $form_state->getValue('confirmation')) {
                $form_state->setErrorByName('confirmation', $this->t('Wrong confirmation'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {

            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_actionplan')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_description')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_shipment')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_finance')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_documents')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_tasks')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_tracker')
                ->condition('pcode', $form_state->get('pcode'))
                ->execute();
            Database::getConnection('external_db', 'external_db')
                ->delete('ek_project_chat')
                ->condition('title', $form_state->get('pcode'))
                ->execute();
        
            \Drupal::messenger()->addStatus(t('project @n deleted from database', ['@n' => $form_state->get('pcode')]));
        }
    }

}
