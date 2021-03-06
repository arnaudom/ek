<?php

/**
 * @file
 * Contains Drupal\ek_admin\Form\SiteSettingsForm.
 * modified core settings install file to create external DB
 */

namespace Drupal\ek_admin\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to configure and rewrite settings.php.
 */
class SiteSettingsForm extends FormBase {

    /**
     * The site path.
     *
     * @var string
     */
    protected $sitePath;

    /**
     * Constructs a new SiteSettingsForm.
     *
     * @param string $site_path
     *   The site path.
     */
    public function __construct($site_path) {
        $this->sitePath = $site_path;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('site.path')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'install_ext_db_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        require_once './core/includes/install.inc';
        $settings_file = './' . $this->sitePath . '/settings.php';
        $form['#title'] = $this->t('External database configuration');

        $drivers = drupal_get_database_types();
        $drivers_keys = array_keys($drivers);

        // Unless there is input for this form (for a non-interactive installation,
        // input originates from the $settings array passed into install_drupal()),
        // check whether database connection settings have been prepared in
        // settings.php already.
        // Note: The installer even executes this form if there is a valid database
        // connection already, since the submit handler of this form is responsible
        // for writing all $settings to settings.php (not limited to $databases).
        $input = &$form_state->getUserInput();
        if (!isset($input['driver']) && $database = Database::getConnectionInfo()) {
            $input['driver'] = $database['default']['driver'];
            $input[$database['default']['driver']] = $database['default'];
        }

        if (isset($input['driver'])) {
            $default_driver = $input['driver'];
            // In case of database connection info from settings.php, as well as for a
            // programmed form submission (non-interactive installer), the table prefix
            // information is usually normalized into an array already, but the form
            // element only allows to configure one default prefix for all tables.
            $prefix = &$input[$default_driver]['prefix'];
            if (isset($prefix) && is_array($prefix)) {
                $prefix = $prefix['default'];
            }

            //no default
            //$default_options = $input[$default_driver];
            $default_options = array();
        }
        // If there is no database information yet, suggest the first available driver
        // as default value, so that its settings form is made visible via #states
        // when JavaScript is enabled (see below).
        else {
            $default_driver = current($drivers_keys);
            $default_options = array();
        }

        $form['driver'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Database type'),
            '#required' => true,
            '#default_value' => $default_driver,
        );
        if (count($drivers) == 1) {
            $form['driver']['#disabled'] = true;
        }

        // Add driver specific configuration options.
        foreach ($drivers as $key => $driver) {
            $form['driver']['#options'][$key] = $driver->name();

            $form['settings'][$key] = $driver->getFormOptions($default_options);
            $form['settings'][$key]['#prefix'] = '<h2 class="js-hide">' . $this->t('@driver_name settings', array('@driver_name' => $driver->name())) . '</h2>';
            $form['settings'][$key]['#type'] = 'container';
            $form['settings'][$key]['#tree'] = true;
            $form['settings'][$key]['advanced_options']['#parents'] = array($key);
            $form['settings'][$key]['#states'] = array(
                'visible' => array(
                    ':input[name=driver]' => array('value' => $key),
                )
            );
        }

        $form['actions'] = array('#type' => 'actions');
        $form['actions']['save'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save and continue'),
            '#button_type' => 'primary',
            '#limit_validation_errors' => array(
                array('driver'),
                array($default_driver),
            ),
            '#submit' => array('::submitForm'),
        );

        $form['errors'] = array();
        $form['settings_file'] = array('#type' => 'value', '#value' => $settings_file);

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        require_once './core/includes/install.core.inc';
        $driver = $form_state->getValue('driver');
        $database = $form_state->getValue($driver);
        $drivers = drupal_get_database_types();
        $reflection = new \ReflectionClass($drivers[$driver]);
        $install_namespace = $reflection->getNamespaceName();
        // Cut the trailing \Install from namespace.
        $database['namespace'] = substr($install_namespace, 0, strrpos($install_namespace, '\\'));
        $database['driver'] = $driver;

        $form_state->set('database', $database);
        $errors = install_database_errors($database, $form_state->getValue('settings_file'));
        foreach ($errors as $name => $message) {
            $form_state->setErrorByName($name, $message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        global $install_state;

        // Update global settings array and save.
        $settings = array();
        $database = $form_state->get('database');
        $settings['databases']['external_db']['default'] = (object) array(
                    'value' => $database,
                    'required' => true,
        );
        $settings['databases']['external_db']['external_db'] = (object) array(
                    'value' => $database,
                    'required' => true,
        );

        $settings_file = \Drupal::service('site.path') . '/settings.php';
        chmod($settings_file, 0777);
        drupal_rewrite_settings($settings);
        chmod($settings_file, 0655);

        \Drupal::messenger()->addStatus(t('External data connection done. Please verify the default settings file is set back to read only. Starting tables installation.'));
        $form_state->setRedirect('ek_admin_install');
    }

}
