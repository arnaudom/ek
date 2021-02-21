<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SplitProject.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to split a project
 */

class SplitProject extends FormBase {

    use AjaxFormHelperTrait;

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
        return 'split_project';
    }

    /**
     * {@inheritdoc}
     * @param id: project id
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p')
                ->fields('p')
                ->condition('id', $id)
                ->execute()
                ->fetchObject();

        $country = AccessCheck::CountryListByUid();

        $form['data'] = [
            '#type' => 'hidden',
            '#value' => ['pcode' => $query->pcode, 'main' => $id,'subcount' => $query->subcount,'type' => $query->category],
        ];
        
        $n = $query->subcount+1;
        $form['level'] = [
            '#type' => 'item',
            '#markup' => '<h1>' . $this->t('Create a new sub project with ref @n',['@n' => $n]) . '</h1>',
        ];
                
        $form['cid'] = [
            '#type' => 'select',
            '#size' => 1,
            '#options' => $country,
            '#default_value' => $query->cid,
            '#required' => true,
            '#title' => $this->t('Country'),
        ];

        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $client = \Drupal\ek_address_book\AddressBookData::addresslist(1);

            $form['client'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $client,
                '#default_value' => $query->client_id,
                '#required' => true,
                '#title' => $this->t('Client'),
                '#attributes' => ['style' => ['width:300px;']],
            ];
        }

        $form['name'] = [
            '#type' => 'textfield',
            '#size' => 35,
            '#maxlength' => 50,
            '#required' => true,
            '#default_value' => $query->pname,
            '#title' => $this->t('Project name'),
        ];

        $form['access'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Access'),
            '#description' => $this->t('grant access to me only'),
            '#default_value' => 0,
        ];

        $form['notify'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Notify users'),
            '#default_value' => 1,
            '#states' => [
                'unchecked' => [
                    ':input[name="access"]' => ['checked' => true],
                ],
            ],
        ];
        
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['open'] = [
            '#type' => 'item',
            '#markup' => '<span id="open"></span>',
        ];
        $form['actions']['record'] = [
            '#type' => 'submit',
            '#id' => 'split-record',
            '#value' => $this->t('Create'),
            '#ajax' => [
                'callback' => '::ajaxSubmit',
            ],
            '#button_type' => 'primary',
        ];
        
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

        // static::ajaxSubmit() requires data-drupal-selector to be the same between
        // the various Ajax requests. 
        // @todo Remove this workaround once https://www.drupal.org/node/2897377 
        $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);

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
        
        //create the project
        //tag
        $company = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company','c')
                    ->fields('c',['short'])
                    ->condition('id', 1)
                    ->execute()
                    ->fetchField();


        //country
        $country = Database::getConnection('external_db', 'external_db')
                    ->select('ek_country','c')
                    ->fields('c',['name', 'code'])
                    ->condition('id', $form_state->getValue('cid'))
                    ->execute()
                    ->fetchObject();

        //type
        $type = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_type','c')
                    ->fields('c',['short'])
                    ->condition('id', $form_state->getValue('data')['type'])
                    ->execute()
                    ->fetchField();
            
        $type = str_replace('-', '_', $type);

        //ref
        $settings = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project_settings','c')
                    ->fields('c',['settings'])
                    ->condition('coid', 0)
                    ->execute()
                    ->fetchField();
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
        
        //client
        $client = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book','c')
                ->fields('c',['shortname'])
                ->condition('id', $form_state->getValue('client'))
                ->execute()
                ->fetchField();
        $client = str_replace('/', '|', $client);

        $sub = $form_state->getValue('data')['subcount'] + 1;
        Database::getConnection('external_db', 'external_db')
                    ->update('ek_project')->fields(['subcount' => $sub])
                    ->condition('id', $form_state->getValue('data')['main'])
                    ->execute();
        $pcode = $form_state->getValue('data')['pcode'] . '_sub' . $sub;
        $pname = Xss::filter(strip_tags($form_state->getValue('name')));
        $pname = strtolower($pname);
        $pname = ucfirst($pname);
        //main table
            $fields = array(
                'pname' => $pname,
                'client_id' => $form_state->getValue('client'),
                'cid' => $form_state->getValue('cid'),
                'date' => date('Y-m-d'),
                'category' => $form_state->getValue('data')['type'],
                'pcode' => $pcode,
                'status' => 'open',
                'level' => 'Sub project',
                'main' => $form_state->getValue('data')['main'],
                'priority' => 0,
                'subcount' => 0,
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
            \Drupal::service('file_system')->prepareDirectory($dir, 'FILE_CREATE_DIRECTORY' | 'FILE_MODIFY_PERMISSIONS');

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

            $form_state->set('pid',$pid);
        
    }
    
    /**
     * {@inheritDoc}
     */
    public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {  
        $command = new RedirectCommand('/projects/project/'. $form_state->get('pid'));
        $response = new AjaxResponse();
        return $response->addCommand($command);
    }

}
