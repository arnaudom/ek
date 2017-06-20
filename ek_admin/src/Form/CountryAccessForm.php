<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\CountryAccessForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

/**
 * Provides form to manage access.
 */
class CountryAccessForm extends FormBase {

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

        $option = array();
        $t = (string) t('active');
        $query = "SELECT id,name from {ek_country} where status=:s order by name";
        $r1 = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => 1))->fetchAllKeyed();
        $option[$t] = $r1;

        $t = (string) t('non active');
        $query = "SELECT id,name from {ek_country}  where status=:s order by name";
        $r2 = Database::getConnection('external_db', 'external_db')->query($query, array(':s' => 0))->fetchAllKeyed();
        $option[$t] = $r2;

        $form['cid'] = array(
            '#type' => 'select',
            '#options' => $option,
            '#default_value' => NULL,
            '#required' => TRUE,
            '#ajax' => array(
                'callback' => array($this, 'user_select_callback'),
                'wrapper' => 'replace_textfield_div',
            ),
        );



        if ($form_state->getValue('cid') <> '') {
            $query = "SELECT access from {ek_country} where id=:id";
            $list_members = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $form_state->getValue('cid')))->fetchField();
            $list = unserialize($list_members);
            $query = 'SELECT uid,name from {users_field_data} where uid>:u order by name';
            $users = db_query($query, array(':u' => 0));

            $default = explode(',', $list);
        }

        $form['list'] = array(
            '#type' => 'details',
            '#title' => t("Select users with access "),
            '#collapsible' => TRUE,
            '#open' => isset($users) ? TRUE : FALSE,
            '#tree' => TRUE,
            '#prefix' => '<div id="replace_textfield_div">',
            '#suffix' => '</div>',
        );

        if (isset($users)) {
            while ($u = $users->fetchObject()) {
                $class = in_array($u->uid, $default) ? 'select' : '';
                $obj = \Drupal\user\Entity\User::load($u->uid);
                $role = $obj->getRoles();
                $role = implode(',', $role);

                $form['list'][$form_state->getValue('cid')][$u->uid] = array(
                    '#type' => 'checkbox',
                    '#title' => $u->name . ' (' . $role . ')',
                    '#default_value' => in_array($u->uid, $default) ? 1 : 0,
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

    function user_select_callback($form, FormStateInterface $form_state) {
        return $form['list'];
    }

  /**
   * {@inheritdoc}
   */
    public function validateForm(array &$form, FormStateInterface $form_state) {

        if (!is_numeric($form_state->getValue('cid'))) {
            $form_state->setErrorByName('cid', $this->t('No country selected'));
        }
    }

  /**
   * {@inheritdoc}
   */
    public function submitForm(array &$form, FormStateInterface $form_state) {

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
            drupal_set_message(t('Data updated'));
        }

        //////////////////////////
        //    WATCHDOG          //
        //////////////////////////
        $cuntry = Database::getConnection('external_db', 'external_db')
                ->query("SELECT name from {ek_country} WHERE id=:id", [':id' => $form_state->getValue('cid')])
                ->fetchField();
        $name = \Drupal::currentUser()->getUsername();
        $a = array('@u' => $name, '@c' => $cuntry, '@d' => $access);
        $log = t("User @u has given access to country @c for users id @d", $a);
        \Drupal::logger('ek_admin')->notice($log);
    }


}
