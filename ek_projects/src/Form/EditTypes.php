<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\EditTypes.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to edit project types
 */
class EditTypes extends FormBase {

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
        return 'project_types_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

        $link = Url::fromRoute('ek_projects_new', array(), array())->toString();
        $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_project_type', 'pt')
                            ->fields('pt');
        $query->orderBy('id');
        $data = $query->execute();
        $query = Database::getConnection('external_db', 'external_db')
                            ->select('ek_project', 'p')
                            ->fields('p',['category']);
        $query->distinct();
        $categories = $query->execute()->fetchCol();

        $form['p'] = array(
            '#type' => 'item',
            '#markup' => t('<a href="@t" >new project</a>', array('@t' => $link)),
        );

        $header = array(
            'group' => array(
                'data' => $this->t('Group'),
            ),
            'type' => array(
                'data' => $this->t('Name'),
                'field' => 'type',
                'sort' => 'asc',
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'comment' => array(
                'data' => $this->t('Description'),
            ),
            'short' => array(
                'data' => $this->t('Short name'),
            ),
            'del' => $this->t('Delete'),
            'id' => '',
        );

        $form['l-table'] = array(
            '#tree' => TRUE,
            '#theme' => 'table',
            '#header' => $header,
            '#rows' => array(),
            '#attributes' => array('id' => 'l-table'),
            '#empty' => $this->t('No type defined'),
        );


        While ($r = $data->fetchObject()) {

            $id = $r->id;

            $form['id'] = array(
                '#id' => 'id-' . $id,
                '#type' => 'hidden',
                '#value' => $id,
            );
            $form['group'] = array(
                '#id' => 'group-' . $id,
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 45,
                '#default_value' => $r->gp,
                '#attributes' => array('placeholder' => t('group'), 'title' => t('A group tag')),
                '#required' => TRUE,
            );
            $form['type'] = array(
                '#id' => 'type-' . $id,
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 45,
                '#default_value' => $r->type,
                '#attributes' => array('placeholder' => t('project type name')),
                '#required' => TRUE,
                '#disabled' => in_array($id,$categories) ? TRUE:FALSE,
            );

            $form['comment'] = array(
                '#id' => 'comment-' . $id,
                '#type' => 'textfield',
                '#size' => 30,
                '#maxlength' => 255,
                '#default_value' => $r->comment,
                '#attributes' => array('placeholder' => t('description')),
            );

            $form['short'] = array(
                '#id' => 'short-' . $id,
                '#type' => 'textfield',
                '#size' => 15,
                '#maxlength' => 5,
                '#default_value' => $r->short,
                '#required' => TRUE,
                '#attributes' => array('class' => array('short name')),
                '#disabled' => in_array($id,$categories) ? TRUE:FALSE,
            );

            $form['del'] = array(
                '#id' => 'del-' . $id,
                '#type' => 'checkbox',
                '#attributes' => array(
                    'title' => t('delete'),
                    'onclick' => "jQuery('#$id').toggleClass('delete');"
                ),
            );


            $form['l-table'][$id] = array(
                'group' => &$form['group'],
                'type' => &$form['type'],
                'comment' => &$form['comment'],
                'short' => &$form['short'],
                'del' => &$form['del'],
                'id' => &$form['id'],
            );

            $form['l-table']['#rows'][] = array(
                'data' => array(
                    array('data' => &$form['group']),
                    array('data' => &$form['type']),
                    array('data' => &$form['comment']),
                    array('data' => &$form['short']),
                    array('data' => &$form['del']),
                    array('data' => &$form['id']),
                ),
                'id' => array($id)
            );
            unset($form['id']);
            unset($form['group']);
            unset($form['type']);
            unset($form['comment']);
            unset($form['short']);
            unset($form['del']);
        }

        $form['group'] = array(
            '#id' => 'newgroup',
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 45,
            '#default_value' => '',
            '#attributes' => array('placeholder' => t('Group'), 'title' => t('A grouping tag')),
        );
        $form['type'] = array(
            '#id' => 'newtype',
            '#type' => 'textfield',
            '#size' => 25,
            '#maxlength' => 45,
            '#default_value' => '',
            '#attributes' => array('placeholder' => t('New type'), 'title' => t('Main category reference')),
        );

        $form['comment'] = array(
            '#id' => 'newcomment',
            '#type' => 'textfield',
            '#size' => 30,
            '#maxlength' => 255,
            '#default_value' => '',
            '#attributes' => array('placeholder' => t('Description'), 'title' => t('Meaningful description')),
        );

        $form['short'] = array(
            '#id' => 'newshort',
            '#type' => 'textfield',
            '#size' => 15,
            '#maxlength' => 5,
            '#default_value' => '',
            '#attributes' => array('class' => array('Short name'), 'title' => t('Used to build case ref. Should no be more than 5 letters')),
        );

        $form['del'] = array(
            '#type' => 'hidden',
        );


        $form['l-table']['new'] = array(
            'group' => &$form['group'],
            'type' => &$form['type'],
            'comment' => &$form['comment'],
            'short' => &$form['short'],
            'del' => &$form['del'],
        );

        $form['l-table']['#rows'][] = array(
            'data' => array(
                array('data' => &$form['group']),
                array('data' => &$form['type']),
                array('data' => &$form['comment']),
                array('data' => &$form['short']),
                array('data' => &$form['del']),
                array('data' => ''),
            ),
                //'id' => array($id)
        );
        unset($form['group']);
        unset($form['type']);
        unset($form['comment']);
        unset($form['short']);
        unset($form['del']);

        $form['categories'] = array(
                '#type' => 'hidden',
                '#value' => $categories,
            );

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#suffix' => ''
        );


        $form['#attached']['library'][] = 'ek_projects/ek_projects_css';



        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->getValue('comment') != '' || $form_state->getValue('short') != '') {
            if ($form_state->getValue('type') == '') {
                $form_state->setErrorByName('newtype', $this->t('You need to enter a type'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {


        $list = $form_state->getValue('l-table');
        $categories = $form_state->getValue('categories');
        foreach ($list as $key => $value) {

            if ($key != 'new') {
                if ($value['del'] == 1) {

                    if (in_array($value['id'],$categories)) {
                        \Drupal::messenger()->addWarning(t('Project type \'@l\' cannot be deleted because it is used.', ['@l' => $value['type']]));
                    } else {
                        Database::getConnection('external_db', 'external_db')
                                ->delete('ek_project_type')
                                ->condition('id', $key)
                                ->execute();
                        \Drupal::messenger()->addWarning(t('Project type \'@l\' deleted', ['@l' => $value['type']]));
                    }
                } else {

                    if (in_array($value['id'],$categories)) { //In use only update description
                        $fields = array(
                            'comment' => Xss::filter($value['comment']),
                            'gp' => Xss::filter($value['group']),
                        );
                    } else { 
                        //not in use can update all data
                        //check if field is less than 6 char
                        $short = Xss::filter($value['short']);
                        if(count_chars($short,3) > 5) {
                            $short = substr($short,0,5);
                        }
                        $fields = array(
                            'type' => Xss::filter($value['type']),
                            'gp' => Xss::filter($value['group']),
                            'comment' => Xss::filter($value['comment']),
                            'short' => $short
                        );
                    }


                    Database::getConnection('external_db', 'external_db')
                            ->update('ek_project_type')
                            ->fields($fields)
                            ->condition('id', $key)
                            ->execute();
                }
            } else {
                if ($value['type'] != '') {
                    $short = Xss::filter($value['short']);
                        if($short == '') {
                            $short = substr(Xss::filter($value['type']),0,5);
                        }
                        if(count_chars($short,3) > 5) {
                            $short = substr($short,0,5);
                        }
                    $fields = array(
                        'type' => Xss::filter($value['type']),
                        'gp' => Xss::filter($value['group']),
                        'comment' => Xss::filter($value['comment']),
                        'short' => $short
                    );

                    Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project_type')
                            ->fields($fields)
                            ->execute();
                    \Drupal::messenger()->addStatus(t('Project type \'@l\' is created', ['@l' => $value['type']]));
                }
            }
        }

        \Drupal::messenger()->addStatus(t('Data updated'));
    }

}
