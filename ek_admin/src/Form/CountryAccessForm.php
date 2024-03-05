<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\CountryAccessForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides form to manage access.
 */
class CountryAccessForm extends FormBase {

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
        return 'ek_country_access_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // @todo Evaluate this again in https://www.drupal.org/node/2503009.
        $form['#cache']['max-age'] = -1;
        $form['#cache']['tags'] = ['ek_access_control_form'];

        $option = [];
        $t = (string) $this->t('active');
        $option[$t] = AccessCheck::CountryList(1);

        $t = (string) $this->t('non active');
        $option[$t] = AccessCheck::CountryList(0);

        $form['cid'] = [
            '#type' => 'select',
            '#options' => $option,
            '#default_value' => null,
            '#required' => true,
            '#ajax' => [
                'callback' => [$this, 'user_select_callback'],
                'wrapper' => 'replace_textfield_div',
            ],
        ];
        
        if ($form_state->getValue('cid') <> '') {
            $query = Database::getConnection('external_db', 'external_db')->select('ek_country', 'c');
            $query->fields('c', ['access']);
            $query->condition('id', $form_state->getValue('cid'));
            $list_members = $query->execute()->fetchField();
            $list = unserialize($list_members);
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid', 'name']);
            $query->condition('uid', 0, '>');
            $query->orderBy('name');
            $users = $query->execute();
            $default = explode(',', $list);
        }

        $form['list'] = [
            '#type' => 'details',
            '#title' => $this->t("Select users with access "),
            '#collapsible' => true,
            '#open' => isset($users) ? true : false,
            '#tree' => true,
            '#prefix' => '<div id="replace_textfield_div">',
            '#suffix' => '</div>',
        ];

        if (isset($users)) {
            while ($u = $users->fetchObject()) {
                $class = in_array($u->uid, $default) ? 'select' : '';
                $obj = \Drupal\user\Entity\User::load($u->uid);
                $role = $obj->getRoles();
                $role = '(' .  implode(',', $role) . ')';
                if($obj->isBlocked()) {
                    $role = '<strong>[' . t('Bloked') . ']</strong> ' . $role ;
                }
                $form['list'][$form_state->getValue('cid')][$u->uid] = [
                    '#type' => 'checkbox',
                    '#title' => $obj->toLink($u->name)->toString() . " " . $role,
                    '#default_value' => in_array($u->uid, $default) ? 1 : 0,
                    '#attributes' => ['onclick' => "jQuery('#u" . $u->uid . "' ).toggleClass('select');"],
                    '#prefix' => "<div id='u" . $u->uid . "' class='" . $class . "'>",
                    '#suffix' => '</div>',
                ];
            }
        }

        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Record')];

        return $form;
    }

    public function user_select_callback($form, FormStateInterface $form_state) {
        return $form['list'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (!is_numeric($form_state->getValue('cid'))) {
            $form_state->setErrorByName('cid', $this->t('No country selected'));
        }
        
        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $sort = [];
            $list = $form_state->getValue('list');
            foreach ($list[$form_state->getValue('cid')] as $key => $value) {
                if ($value == 0) {
                    //check if project access for removed user
                    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p');
                    $query->fields('p', ['id', 'pcode']);
                    $query->condition('cid', $form_state->getValue('cid'));
                    $or = $query->orConditionGroup()
                        ->condition('owner', $key)
                        ->condition('share', "%," . $key . "%,", 'like');
                    $query->condition($or);
                    $data = $query->execute();
                    while ($d = $data->fetchObject()) {
                        $sort[$key][] = $d->pcode;
                    }
                }
            }
            
            if (!empty($sort)) {
                $list = "";
                foreach ($sort as $k => $v) {
                    $u = User::load($k);
                    $pcode = "";
                    foreach ($v as $c => $code) {
                        $pcode .= \Drupal\ek_projects\ProjectData::geturl($code, 0, 0, 1) . " ";
                    }
                    $list .= "<li>" . $u->getAccountName() . ": " . $pcode . "</li>";
                }
                $render = [
                    '#markup' =>"<ul>" . $list . "</ul>",
                ];
                $list = \Drupal::service('renderer')->render($render);
                $form_state->setErrorByName(
                    'cid',
                    $this->t('Some users need to be removed from project(s) before country access @l', ['@l' => $list])
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $list = $form_state->getValue('list');
        $access = array();
        foreach ($list[$form_state->getValue('cid')] as $key => $value) {
            if ($value == 1) {
                $access[] = $key;
            }
        }


        $access = implode(",", $access);
        $selected = serialize($access);

        $update = Database::getConnection('external_db', 'external_db')->update('ek_country')
                ->condition('id', $form_state->getValue('cid'))
                ->fields(array('access' => $selected))
                ->execute();
        if ($update) {
            \Drupal::messenger()->addStatus(t('Data updated'));
        }

        //////////////////////////
        //    WATCHDOG          //
        //////////////////////////
        $cuntry = Database::getConnection('external_db', 'external_db')
                ->query("SELECT name from {ek_country} WHERE id=:id", [':id' => $form_state->getValue('cid')])
                ->fetchField();
        $name = \Drupal::currentUser()->getAccountName();
        $a = array('@u' => $name, '@c' => $cuntry, '@d' => $access);
        $log = $this->t("User @u has given access to country @c for users id @d", $a);
        \Drupal::logger('ek_admin')->notice($log);
    }
}
