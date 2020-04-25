<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\CompanyAccessForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides form to manage access.
 */
class CompanyAccessForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'ek_company_access_form';
    }
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        // @todo Evaluate this again in https://www.drupal.org/node/2503009.
        $form['#cache']['max-age'] = -1;
        $form['#cache']['tags'] = ['ek_access_control_form'];
        $account = \Drupal::currentUser();
        $uid = $account->id();
        //if user is has administrator role, the user can access all companies
        if (in_array('administrator', \Drupal::currentUser()->getRoles())) {
            $option = Database::getConnection('external_db', 'external_db')
                    ->query("SELECT id,name from {ek_company} order by name")
                    ->fetchAllKeyed();
        } else {
            $option = AccessCheck::CompanyListByUid();
        }
        $form['coid'] = array(
            '#type' => 'select',
            '#options' => $option,
            '#default_value' => null,
            '#required' => true,
            '#ajax' => array(
                'callback' => array($this, 'user_select_callback'),
                'wrapper' => 'replace_textfield_div',
            ),
        );

        if ($form_state->getValue('coid') <> '') {
            $query = Database::getConnection('external_db', 'external_db')->select('ek_company', 'c');
            $query->fields('c', ['access']);
            $query->condition('id', $form_state->getValue('coid'));
            $list_members = $query->execute()->fetchField();
            $list = unserialize($list_members);
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u', ['uid', 'name']);
            $query->condition('uid', 0, '>');
            $query->orderBy('name');
            $users = $query->execute();
            $default = explode(',', $list);
        }
        
        $form['list'] = array(
            '#type' => 'details',
            '#title' => t("Select users with access "),
            '#collapsible' => true,
            '#open' => isset($users) ? true : false,
            '#tree' => true,
            '#prefix' => '<div id="replace_textfield_div">',
            '#suffix' => '</div>',
        );
        
        if (isset($users)) {
            while ($u = $users->fetchObject()) {
                $class = in_array($u->uid, $default) ? 'select' : '';
                $value = in_array($u->uid, $default) ? 1 : 0;
                $obj = \Drupal\user\Entity\User::load($u->uid);
                $role = $obj->getRoles();
                $role = implode(',', $role);
                
                $form['list'][$form_state->getValue('coid')][$u->uid] = array(
                    '#type' => 'checkbox',
                    //'#id' => 'u' . $u->uid,
                    '#title' => $u->name . ' (' . $role . ')',
                    '#default_value' => $value,
                    '#attributes' => array('onclick' => "jQuery('#u" . $u->uid . "' ).toggleClass('select');"),
                    '#prefix' => "<div id='u" . $u->uid . "' class='" . $class . "'>",
                    '#suffix' => '</div>',
                );
            }
        }


        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));



        return $form;
    }

    public function user_select_callback($form, FormStateInterface $form_state)
    {
        return $form['list'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (!is_numeric($form_state->getValue('coid'))) {
            $form_state->setErrorByName('coid', $this->t('No company selected'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $list = $form_state->getValue('list');
        
        $access = array();
        foreach ($list[$form_state->getValue('coid')] as $key => $value) {
            if ($value == 1) {
                $access[] = $key;
            }
        }

        $access = implode(",", $access);
        $selected = serialize($access);

        $update = Database::getConnection('external_db', 'external_db')->update('ek_company')
                ->condition('id', $form_state->getValue('coid'))
                ->fields(array('access' => $selected))
                ->execute();
        if ($update) {
            \Drupal::messenger()->addStatus(t('Data updated'));
        }
        
        //////////////////////////
        //    WATCHDOG          //
        //////////////////////////
        $company = Database::getConnection('external_db', 'external_db')
                ->query("SELECT name from {ek_company} WHERE id=:id", [':id' => $form_state->getValue('coid')])
                ->fetchField();
        $name = \Drupal::currentUser()->getAccountName();
        $a = array('@u' => $name, '@c' => $company, '@d' => $access);
        $log = t("User @u has given access to company @c for users id @d", $a);
        \Drupal::logger('ek_admin')->notice($log);
    }
}
