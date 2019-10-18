<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\NewProject.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

;

/**
 * Provides a form to create a new project
 */
class NewProject extends FormBase {

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
        return 'new_project';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {


        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }



        $query = "SELECT id,type from {ek_project_type} ORDER by id";
        $type = Database::getConnection('external_db', 'external_db')->query($query)->fetchAllKeyed();
        $link = Url::fromRoute('ek_projects_types', array(), array())->toString();

        if (empty($type)) {
            $form['type'] = array(
                '#type' => 'item',
                '#markup' => t('You did not set any project type. Create a <a href="@t" >type</a> before proceeding or contact administrator.', array('@t' => $link)),
            );
        } else {
            $form['type'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $type,
                '#required' => TRUE,
                '#title' => t('Category'),
                '#description' => t('<a href="@t" >edit categories</a>', array('@t' => $link)),
            );


            if (($form_state->getValue('type')) == '') {
                $form['next'] = array(
                    '#type' => 'submit',
                    '#value' => t('Next') . ' >>',
                    '#states' => array(
                        // Hide data fieldset when class is empty.
                        'invisible' => array(
                            "select[name='type']" => array('value' => ''),
                        ),
                    ),
                );
            }
        }




        if ($form_state->get('step') == 2) {

            $form_state->set('step', 3);
            $country = AccessCheck::CountryListByUid();

            $form['cid'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => $country,
                '#required' => TRUE,
                '#title' => t('Country'),
            );

            if ($this->moduleHandler->moduleExists('ek_address_book')) {
                $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

                if (!empty($client)) {
                    $form['client'] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => $client,
                        '#required' => TRUE,
                        '#title' => t('Client'),
                        '#attributes' => array('style' => array('width:300px;')),
                    );
                } else {
                    $link = Url::fromRoute('ek_address_book.new', array())->toString();
                    $form['client'] = array(
                        '#markup' => t("You do not have any <a title='create' href='@cl'>client</a> in your record.", ['@cl' => $link]),
                    );
                }
            } else {

                $form['client'] = array(
                    '#markup' => t('You do not have any client list.'),
                );
            }

            $form['name'] = array(
                '#type' => 'textfield',
                '#size' => 35,
                '#maxlength' => 50,
                '#required' => TRUE,
                '#title' => t('Project name'),
                    //'#description' => t('project name'),
            );

            $form['level'] = array(
                '#type' => 'select',
                '#size' => 1,
                '#options' => array('Main project' => 'Main project', 'Sub project' => 'Sub project'),
                //'#required' => TRUE,
                '#title' => t('Project level'),
                '#description' => t('Main projects can have sub projects. Sub projects must be linked to a main project'),
            );

            $form['main'] = array(
                '#type' => 'textfield',
                '#size' => 48,
                '#maxlength' => 150,
                //'#required' => TRUE,
                //'#title' => t('main project reference'),
                '#attributes' => array('placeholder' => t('Ex. 123')),
                '#autocomplete_route_name' => 'ek_look_up_projects',
                '#autocomplete_route_parameters' => array('level' => 'main', 'status' => '0'),
                '#description' => t('main project reference'),
                '#states' => array(
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='level']" => array('value' => 'Main project'),
                    ),
                ),
            );

            $form['access'] = array(
                '#type' => 'checkbox',
                '#title' => t('Access'),
                '#description' => t('grant access to me only'),
                '#default_value' => 0,
            );


            $form['notify'] = array(
                '#type' => 'checkbox',
                '#title' => t('Notify users'),
                '#default_value' => 1,
                '#states' => array(
                    'unchecked' => array(
                        ':input[name="access"]' => array('checked' => TRUE),
                    ),
                ),
            );

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );

            $form['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Create'),
            );
        }


        $form['#attached']['library'][] = 'ek_projects/ek_projects_css';




        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if ($form_state->get('step') == 1) {

            $form_state->set('step', 2);
            $form_state->setRebuild();
        }

        if ($form_state->get('step') == 3) {

            if ($form_state->getValue('client') == '') {
                $form_state->setErrorByName("client", $this->t('You must have a client to create a project.'));
            }

            if ($form_state->getValue('level') == 'Sub project') {

                $main = explode(' ', $form_state->getValue('main'));
                $query = 'SELECT pcode from {ek_project} WHERE id=:id';
                $pcode = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => trim($main[0])))
                        ->fetchField();

                if (!$pcode) {
                    $form_state->setErrorByName("main", $this->t('Main reference cannot be found. Please check again.'));
                }
            }
        }
        /**/
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {



        if ($form_state->get('step') == 3) {
            //create the project
            //tag
            $query = 'SELECT short FROM {ek_company} WHERE id=:id';
            $tag = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => 1))->fetchField();


            //country
            $query = 'SELECT name,code FROM {ek_country} WHERE id=:id';
            $cdata = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('cid')))
                    ->fetchObject();

            //type
            $query = 'SELECT short FROM {ek_project_type} WHERE id=:id';
            $type = Database::getConnection('external_db', 'external_db')
                            ->query($query, array(':id' => $form_state->getValue('type')))->fetchField();
            $type = str_replace('-', '_', $type);

            //ref
            $query = "SELECT settings from {ek_project_settings} WHERE coid=:c";
            $settings = Database::getConnection('external_db', 'external_db')
                            ->query($query, [':c' => 0])->fetchField();
            $s = unserialize($settings);
            if ($s['code'] == '') {
                $s['code'] = [1, 2, 3, 4, 5, 6];
            }
            if ($s['increment'] == '' || $s['increment'] < 1) {
                $s['increment'] = 1;
            }
            $query = 'SELECT count(id) FROM {ek_project}';
            $count = Database::getConnection('external_db', 'external_db')->query($query)->fetchField();

            $ref = $count + $s['increment'];
            $main = NULL;

            //client
            $query = 'SELECT shortname from {ek_address_book} WHERE id=:id';
            $client = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $form_state->getValue('client')))
                    ->fetchField();
            $client = str_replace('/', '|', $client);

            if ($form_state->getValue('level') == 'Main project') {

                $pcode = '';
                foreach ($s['code'] as $k => $v) {

                    switch ($v) {
                        case 0 :
                            break;
                        case 1 :
                            $pcode .= $tag . '-';
                            break;
                        case 2 :
                            $pcode .= $type . '-';
                            break;
                        case 3 :
                            $pcode .= $cdata->code . '-';
                            break;
                        case 4 :
                            $pcode .= date('Y_m') . '-';
                            break;
                        case 5 :
                            $pcode .= $client . '-';
                            break;
                        case 6 :
                            $pcode .= $ref;
                            break;
                    }
                }

                //used this when short name content '-' sign(s)
                $pcode = str_replace('---', '-', $pcode);
                $pcode = str_replace('--', '-', $pcode);
                $level = 'Main project';
            } else {
                $main = explode(' ', $form_state->getValue('main'));
                $query = 'SELECT id,pcode,subcount from {ek_project} WHERE id=:id';
                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => trim($main[0])))
                        ->fetchObject();

                $sub = $data->subcount + 1;
                Database::getConnection('external_db', 'external_db')
                        ->update('ek_project')->fields(array('subcount' => $sub))
                        ->condition('id', $main[0])
                        ->execute();
                $pcode = $data->pcode . '_sub' . $sub;
                $level = 'Sub project';
                $main = $data->id;
            }

            $pname = Xss::filter(strip_tags($form_state->getValue('name')));
            $pname = strtolower($pname);
            $pname = ucfirst($pname);
            //main table 
            $fields = array(
                'pname' => $pname,
                'client_id' => $form_state->getValue('client'),
                'cid' => $form_state->getValue('cid'),
                'date' => date('Y-m-d'),
                'category' => $form_state->getValue('type'),
                'pcode' => $pcode,
                'status' => 'open',
                'level' => $level,
                'main' => $main,
                'subcount' => 0,
                'priority' => 0,
                'editor' => 0,
                'owner' => \Drupal::currentUser()->id(),
                'last_modified' => time() . '|' . \Drupal::currentUser()->id(),
                'notify' => \Drupal::currentUser()->id()
            );
            if ($form_state->getValue('access') == 1) {
                $fields['share'] = \Drupal::currentUser()->id();
            }

            $pid = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_project')->fields($fields)->execute();
            $fields = array(
                'pcode' => $pcode,
            );
            //AP table
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_actionplan')->fields($fields)->execute();
            //description table
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_description')->fields($fields)->execute();
            //shipment table
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_shipment')->fields($fields)->execute();
            //finance table
            Database::getConnection('external_db', 'external_db')
                    ->insert('ek_project_finance')->fields($fields)->execute();
            //create document folder
            $dir = "private://projects/documents/" . $ref;
            file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

            \Drupal::messenger()->addStatus(t('New project created with ref @r', ['@r' => $pcode]));
            Cache::invalidateTags(['project_last_block']);

            //notify users
            if ($form_state->getValue('notify') == 1) {
                $param = serialize(
                        array(
                            'id' => $pid,
                            'field' => 'new_project',
                            'value' => $data->serial,
                            'pname' => $pname,
                            'country' => $cdata->name,
                            'cid' => $form_state->getValue('cid'),
                            'pcode' => $pcode
                        )
                );
                \Drupal\ek_projects\ProjectData::notify_user($param);
            }


            $form_state->setRedirect('ek_projects_view', array('id' => $pid));
        }//step 3
    }

}
