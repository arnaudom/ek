<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SerialFormat.
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

;

/**
 * Provides a form to setup the format of project serial reference
 */
class SerialFormat extends FormBase {

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
        return 'project_serial_format';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $query = "SELECT settings from {ek_project_settings} WHERE coid=:c";
        $settings = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => 0])->fetchField();

        $s = unserialize($settings);
        $sample = ['', 'MYCO', 'TYPE', 'CID', 'MM_YY', 'ABC', '123'];

        $string = isset($s['code'][1]) ? "<span id='e1'>" . $sample[$s['code'][1]] . "-</span>" : "<span id='e1'>" . $sample[1] . "</span>-";
        $string .= isset($s['code'][2]) ? "<span id='e2'>" . $sample[$s['code'][2]] . "-</span>" : "<span id='e2'>" . $sample[2] . "</span>-";
        $string .= isset($s['code'][3]) ? "<span id='e3'>" . $sample[$s['code'][3]] . "-</span>" : "<span id='e3'>" . $sample[3] . "</span>-";
        $string .= isset($s['code'][4]) ? "<span id='e4'>" . $sample[$s['code'][4]] . "-</span>" : "<span id='e4'>" . $sample[4] . "</span>-";
        $string .= isset($s['code'][5]) ? "<span id='e5'>" . $sample[$s['code'][5]] . "-</span>" : "<span id='e5'>" . $sample[5] . "</span>-";
        $string .= isset($s['code'][6]) ? "<span id='e6'>" . $sample[$s['code'][6]] . "</span>" : "<span id='e6'>" . $sample[6] . "</span>";

        $form['sample'] = array(
            '#type' => 'item',
            '#markup' => '<h2>' . $string . '</h2>',
        );
        $elements = [
            0 => $this->t('hidden'),
            1 => $this->t('company'),
            2 => $this->t('project type'),
            3 => $this->t('country code'),
            4 => $this->t('date'),
            5 => $this->t('client code')
        ];

        $form['first'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('First element'),
            '#default_value' => isset($s['code'][1]) ? $s['code'][1] : 1,
        );

        $form['second'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Second element'),
            '#default_value' => isset($s['code'][2]) ? $s['code'][2] : 2,
        );

        $form['third'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Third element'),
            '#default_value' => isset($s['code'][3]) ? $s['code'][3] : 3,
        );

        $form['fourth'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Fourth element'),
            '#default_value' => isset($s['code'][4]) ? $s['code'][4] : 4,
        );

        $form['fifth'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $elements,
            '#required' => true,
            '#title' => $this->t('Fifth element'),
            '#default_value' => isset($s['code'][5]) ? $s['code'][5] : 5,
        );
        $form['last'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => [6 => $this->t('sequence number')],
            '#required' => true,
            '#title' => $this->t('Last element'),
            '#default_value' => 6,
        );

        $form['increment'] = array(
            '#type' => 'textfield',
            '#size' => 20,
            '#required' => true,
            '#title' => $this->t('Increment base'),
            '#default_value' => isset($s['increment']) ? $s['increment'] : 1,
        );

        $form['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array('container-inline')),
        );

        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        );

        $form['#attached']['library'][] = 'ek_projects/ek_projects_settings';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (!is_numeric($form_state->getValue('increment')) || $form_state->getValue('increment') < 1) {
            $form_state->setErrorByName("increment", $this->t('The increment value is should be a positive number.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $elements = [
            'code' => [
                1 => $form_state->getValue('first'),
                2 => $form_state->getValue('second'),
                3 => $form_state->getValue('third'),
                4 => $form_state->getValue('fourth'),
                5 => $form_state->getValue('fifth'),
                6 => 6
            ],
            'increment' => $form_state->getValue('increment')
        ];

        Database::getConnection('external_db', 'external_db')
                ->update('ek_project_settings')
                ->condition('coid', 0)
                ->fields(['settings' => serialize($elements)])
                ->execute();

        \Drupal::messenger()->addStatus($this->t('Settings updated'));
        \Drupal\Core\Cache\Cache::invalidateTags(['ek_admin.settings']);
        if ($_SESSION['install'] == 1) {
            unset($_SESSION['install']);
            $form_state->setRedirect('ek_admin.main');
        }
    }

}
