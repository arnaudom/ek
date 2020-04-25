<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditAd.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

;

/**
 * Provides a form to edit allowances and deductions
 */
class EditAd extends FormBase
{

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
    public function __construct(ModuleHandler $module_handler)
    {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ad_edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }


        $company = AccessCheck::CompanyListByUid();
        $form['coid'] = array(
            '#type' => 'select',
            '#size' => 1,
            '#options' => $company,
            '#default_value' => ($form_state->getValue('coid')) ? $form_state->getValue('coid') : null,
            '#title' => t('company'),
            '#disabled' => ($form_state->getValue('coid')) ? true : false,
            '#required' => true,
            '#disabled' => ($form_state->get('step') > 1) ? true : false,
            '#ajax' => array(
                'callback' => array($this, 'categories'),
                'wrapper' => 'category',
            ),
        );

        if ($form_state->get('step') <> 2) {
            $form['category'] = array(
                '#type' => 'select',
                '#options' => ($form_state->get('opt')) ? $form_state->get('opt') : array(),
                '#default_value' => ($form_state->get('opt')) ? $form_state->getValue('category') : null,
                '#title' => t('category'),
                '#required' => true,
                '#prefix' => "<div id='category'>",
                '#suffix' => '</div>',
                '#validated' => true,
            );
        } else {
            $param = new HrSettings($form_state->getValue('coid'));
            $categories = $param->HrCat[$form_state->getValue('coid')];

            $form['selected'] = array(
                '#markup' => t('category : @s', array('@s' => $categories[$form_state->getValue('category')])),
            );
        }

        if ($form_state->get('step') == 1) {
            $form['next'] = array(
                '#type' => 'submit',
                '#value' => t('Next') . ' >>',
                //'#limit_validation_errors' => array(array('coid', 'category')),
                '#submit' => array(array($this, 'step_2')),
            );
        }

        if ($form_state->get('step') == 2) {
            $param = new HrSettings($form_state->getValue('coid'));
            $list = $param->HrAd[$form_state->getValue('coid')];
            $cat = $form_state->getValue('category');

            if (empty($list)) {
                $list = array(
                    $form_state->getValue('coid') => array(
                        'LAF1-a' => array('value' => '0', 'type' => 'normal OT', 'description' => 'Normal OT', 'formula' => '', 'tax' => '1',),
                        'LAF2-a' => array('value' => '0', 'type' => 'rest day OT', 'description' => 'Rest day OT', 'formula' => '', 'tax' => '1',),
                        'LAF3-a' => array('value' => '0', 'type' => 'PH', 'description' => 'PH OT', 'formula' => '', 'tax' => '1',),
                        'LAF4-a' => array('value' => '0', 'type' => 'ML', 'description' => 'medical leave', 'formula' => '', 'tax' => '1',),
                        'LAF5-a' => array('value' => '0', 'type' => 'Extra hours', 'description' => 'extra hours', 'formula' => '', 'tax' => '1',),
                        'LAF6-a' => array('value' => '0', 'type' => 'Sales', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC1-a' => array('value' => '0', 'type' => 'custom allowance 1', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC2-a' => array('value' => '0', 'type' => 'custom allowance 2', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC3-a' => array('value' => '0', 'type' => 'custom allowance 3', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC4-a' => array('value' => '0', 'type' => 'custom allowance 4', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC5-a' => array('value' => '0', 'type' => 'custom allowance 5', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC6-a' => array('value' => '0', 'type' => 'custom allowance 6', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC7-a' => array('value' => '0', 'type' => 'custom allowance 7', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC8-a' => array('value' => '0', 'type' => 'custom allowance 8', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC9-a' => array('value' => '0', 'type' => 'custom allowance 9', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC10-a' => array('value' => '0', 'type' => 'custom allowance 10', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC11-a' => array('value' => '0', 'type' => 'custom allowance 11', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC12-a' => array('value' => '0', 'type' => 'custom allowance 12', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC13-a' => array('value' => '0', 'type' => 'custom allowance 13', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LDF1-a' => array('value' => '0', 'type' => 'advance', 'description' => 'Advance', 'formula' => '', 'tax' => '1',),
                        'LDF2-a' => array('value' => '0', 'type' => 'Less hours', 'description' => 'Less hours', 'formula' => '', 'tax' => '1',),
                        'LDC1-a' => array('value' => '0', 'type' => 'deduction custom 1', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC2-a' => array('value' => '0', 'type' => 'deduction custom 2', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC3-a' => array('value' => '0', 'type' => 'deduction custom 3', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC4-a' => array('value' => '0', 'type' => 'deduction custom 4', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC5-a' => array('value' => '0', 'type' => 'deduction custom 5', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC6-a' => array('value' => '0', 'type' => 'deduction custom 6', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC7-a' => array('value' => '0', 'type' => 'deduction custom 7', 'description' => 'deduction', 'formula' => '0', 'tax' => '1',),
                        'LAF1-b' => array('value' => '0', 'type' => 'normal OT', 'description' => 'normal OT', 'formula' => '', 'tax' => '1',),
                        'LAF2-b' => array('value' => '0', 'type' => 'rest day OT', 'description' => 'Rest Day OT', 'formula' => '', 'tax' => '1',),
                        'LAF3-b' => array('value' => '0', 'type' => 'PH', 'description' => 'Public Holidays OT', 'formula' => '', 'tax' => '1',),
                        'LAF4-b' => array('value' => '0', 'type' => 'ML', 'description' => 'medical leave AW', 'formula' => '', 'tax' => '1',),
                        'LAF5-b' => array('value' => '0', 'type' => 'Extra hours', 'description' => 'Extra hours', 'formula' => '', 'tax' => '1',),
                        'LAF6-b' => array('value' => '0', 'type' => 'Sales', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC1-b' => array('value' => '0', 'type' => 'custom allowance 1', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC2-b' => array('value' => '0', 'type' => 'custom allowance 2', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC3-b' => array('value' => '0', 'type' => 'custom allowance 3', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC4-b' => array('value' => '0', 'type' => 'custom allowance 4', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC5-b' => array('value' => '0', 'type' => 'custom allowance 5', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC6-b' => array('value' => '0', 'type' => 'custom allowance 6', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC7-b' => array('value' => '0', 'type' => 'custom allowance 7', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC8-b' => array('value' => '0', 'type' => 'custom allowance 8', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC9-b' => array('value' => '0', 'type' => 'custom allowance 9', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC10-b' => array('value' => '0', 'type' => 'custom allowance 10', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC11-b' => array('value' => '0', 'type' => 'custom allowance 11', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC12-b' => array('value' => '0', 'type' => 'custom allowance 12', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC13-b' => array('value' => '0', 'type' => 'custom allowance 13', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LDF1-b' => array('value' => '0', 'type' => 'advance', 'description' => 'Advance', 'formula' => '', 'tax' => '1',),
                        'LDF2-b' => array('value' => '0', 'type' => 'Less hours', 'description' => 'Less hours', 'formula' => '', 'tax' => '1',),
                        'LDC1-b' => array('value' => '0', 'type' => 'deduction custom 1', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC2-b' => array('value' => '0', 'type' => 'deduction custom 2', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC3-b' => array('value' => '0', 'type' => 'deduction custom 3', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC4-b' => array('value' => '0', 'type' => 'deduction custom 4', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC5-b' => array('value' => '0', 'type' => 'deduction custom 5', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC6-b' => array('value' => '0', 'type' => 'deduction custom 6', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC7-b' => array('value' => '0', 'type' => 'deduction custom 7', 'description' => 'deduction', 'formula' => '0', 'tax' => '1',),
                        'LAF1-c' => array('value' => '0', 'type' => 'normal OT', 'description' => 'Normal OT', 'formula' => '', 'tax' => '1',),
                        'LAF2-c' => array('value' => '0', 'type' => 'rest day OT', 'description' => 'Rest day OT', 'formula' => '', 'tax' => '1',),
                        'LAF3-c' => array('value' => '0', 'type' => 'PH', 'description' => 'PH OT', 'formula' => '', 'tax' => '1',),
                        'LAF4-c' => array('value' => '0', 'type' => 'ML', 'description' => 'medical leave', 'formula' => '', 'tax' => '1',),
                        'LAF5-c' => array('value' => '0', 'type' => 'Extra hours', 'description' => 'extra hours', 'formula' => '', 'tax' => '1',),
                        'LAF6-c' => array('value' => '0', 'type' => 'Sales', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC1-c' => array('value' => '0', 'type' => 'custom allowance 1', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC2-c' => array('value' => '0', 'type' => 'custom allowance 2', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC3-c' => array('value' => '0', 'type' => 'custom allowance 3', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC4-c' => array('value' => '0', 'type' => 'custom allowance 4', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC5-c' => array('value' => '0', 'type' => 'custom allowance 5', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC6-c' => array('value' => '0', 'type' => 'custom allowance 6', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC7-c' => array('value' => '0', 'type' => 'custom allowance 7', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC8-c' => array('value' => '0', 'type' => 'custom allowance 8', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC9-c' => array('value' => '0', 'type' => 'custom allowance 9', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC10-c' => array('value' => '0', 'type' => 'custom allowance 10', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC11-c' => array('value' => '0', 'type' => 'custom allowance 11', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC12-c' => array('value' => '0', 'type' => 'custom allowance 12', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC13-c' => array('value' => '0', 'type' => 'custom allowance 13', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LDF1-c' => array('value' => '0', 'type' => 'advance', 'description' => 'Advance', 'formula' => '', 'tax' => '1',),
                        'LDF2-c' => array('value' => '0', 'type' => 'Less hours', 'description' => 'Less hours', 'formula' => '', 'tax' => '1',),
                        'LDC1-c' => array('value' => '0', 'type' => 'deduction custom 1', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC2-c' => array('value' => '0', 'type' => 'deduction custom 2', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC3-c' => array('value' => '0', 'type' => 'deduction custom 3', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC4-c' => array('value' => '0', 'type' => 'deduction custom 4', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC5-c' => array('value' => '0', 'type' => 'deduction custom 5', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC6-c' => array('value' => '0', 'type' => 'deduction custom 6', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC7-c' => array('value' => '0', 'type' => 'deduction custom 7', 'description' => 'deduction', 'formula' => '0', 'tax' => '1',),
                        'LAF1-d' => array('value' => '0', 'type' => 'normal OT', 'description' => 'Normal OT', 'formula' => '', 'tax' => '1',),
                        'LAF2-d' => array('value' => '0', 'type' => 'rest day OT', 'description' => 'Rest day OT', 'formula' => '', 'tax' => '1',),
                        'LAF3-d' => array('value' => '0', 'type' => 'PH', 'description' => 'PH OT', 'formula' => '', 'tax' => '1',),
                        'LAF4-d' => array('value' => '0', 'type' => 'ML', 'description' => 'medical leave', 'formula' => '', 'tax' => '1',),
                        'LAF5-d' => array('value' => '0', 'type' => 'Extra hours', 'description' => 'extra hours', 'formula' => '', 'tax' => '1',),
                        'LAF6-d' => array('value' => '0', 'type' => 'Sales', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC1-d' => array('value' => '0', 'type' => 'custom allowance 1', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC2-d' => array('value' => '0', 'type' => 'custom allowance 2', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC3-d' => array('value' => '0', 'type' => 'custom allowance 3', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC4-d' => array('value' => '0', 'type' => 'custom allowance 4', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC5-d' => array('value' => '0', 'type' => 'custom allowance 5', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC6-d' => array('value' => '0', 'type' => 'custom allowance 6', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC7-d' => array('value' => '0', 'type' => 'custom allowance 7', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC8-d' => array('value' => '0', 'type' => 'custom allowance 8', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC9-d' => array('value' => '0', 'type' => 'custom allowance 9', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC10-d' => array('value' => '0', 'type' => 'custom allowance 10', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC11-d' => array('value' => '0', 'type' => 'custom allowance 11', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC12-d' => array('value' => '0', 'type' => 'custom allowance 12', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC13-d' => array('value' => '0', 'type' => 'custom allowance 13', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LDF1-d' => array('value' => '0', 'type' => 'advance', 'description' => 'Advance', 'formula' => '', 'tax' => '1',),
                        'LDF2-d' => array('value' => '0', 'type' => 'Less hours', 'description' => 'Less hours', 'formula' => '', 'tax' => '1',),
                        'LDC1-d' => array('value' => '0', 'type' => 'deduction custom 1', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC2-d' => array('value' => '0', 'type' => 'deduction custom 2', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC3-d' => array('value' => '0', 'type' => 'deduction custom 3', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC4-d' => array('value' => '0', 'type' => 'deduction custom 4', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC5-d' => array('value' => '0', 'type' => 'deduction custom 5', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC6-d' => array('value' => '0', 'type' => 'deduction custom 6', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC7-d' => array('value' => '0', 'type' => 'deduction custom 7', 'description' => 'deduction', 'formula' => '0', 'tax' => '1',),
                        'LAF1-e' => array('value' => '0', 'type' => 'normal OT', 'description' => 'Normal OT', 'formula' => '', 'tax' => '1',),
                        'LAF2-e' => array('value' => '0', 'type' => 'rest day OT', 'description' => 'Rest day OT', 'formula' => '', 'tax' => '1',),
                        'LAF3-e' => array('value' => '0', 'type' => 'PH', 'description' => 'PH OT', 'formula' => '', 'tax' => '1',),
                        'LAF4-e' => array('value' => '0', 'type' => 'ML', 'description' => 'medical leave', 'formula' => '', 'tax' => '1',),
                        'LAF5-e' => array('value' => '0', 'type' => 'Extra hours', 'description' => 'extra hours', 'formula' => '', 'tax' => '1',),
                        'LAF6-e' => array('value' => '0', 'type' => 'Sales', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC1-e' => array('value' => '0', 'type' => 'custom allowance 1', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC2-e' => array('value' => '0', 'type' => 'custom allowance 2', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC3-e' => array('value' => '0', 'type' => 'custom allowance 3', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC4-e' => array('value' => '0', 'type' => 'custom allowance 4', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC5-e' => array('value' => '0', 'type' => 'custom allowance 5', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC6-e' => array('value' => '0', 'type' => 'custom allowance 6', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC7-e' => array('value' => '0', 'type' => 'custom allowance 7', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC8-e' => array('value' => '0', 'type' => 'custom allowance 8', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC9-e' => array('value' => '0', 'type' => 'custom allowance 9', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC10-e' => array('value' => '0', 'type' => 'custom allowance 10', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC11-e' => array('value' => '0', 'type' => 'custom allowance 11', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC12-e' => array('value' => '0', 'type' => 'custom allowance 12', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LAC13-e' => array('value' => '0', 'type' => 'custom allowance 13', 'description' => 'allowance', 'formula' => '', 'tax' => '1',),
                        'LDF2-e' => array('value' => '0', 'type' => 'Less hours', 'description' => 'Less hours', 'formula' => '', 'tax' => '1',),
                        'LDF1-e' => array('value' => '0', 'type' => 'advance', 'description' => 'Advance', 'formula' => '', 'tax' => '1',),
                        'LDC1-e' => array('value' => '0', 'type' => 'deduction custom 1', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC2-e' => array('value' => '0', 'type' => 'deduction custom 2', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC3-e' => array('value' => '0', 'type' => 'deduction custom 3', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC4-e' => array('value' => '0', 'type' => 'deduction custom 4', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC5-e' => array('value' => '0', 'type' => 'deduction custom 5', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC6-e' => array('value' => '0', 'type' => 'deduction custom 6', 'description' => 'deduction', 'formula' => '', 'tax' => '1',),
                        'LDC7-e' => array('value' => '0', 'type' => 'deduction custom 7', 'description' => 'deduction', 'formula' => '0', 'tax' => '1',),
                    )
                );

                Database::getConnection('external_db', 'external_db')
                        ->update('ek_hr_workforce_settings')
                        ->fields(array('ad' => serialize($list)))
                        ->condition('coid', $form_state->getValue('coid'))
                        ->execute();

                $category = new HrSettings($form_state->getValue('coid'));
                $list = $category->HrAd[$form_state->getValue('coid')];
            }


            $form['selected_category'] = array(
                '#type' => 'hidden',
                '#value' => $cat,
            );


            $form_state->set('step', 3);

            $headerline = "<table><tr><td>" . t("Description") . "</td><td>" . t("Value")
                    . "</td><td>" . t("Formula") . "</td><td>" . t("Include tax") . "</td></tr>";


            $form['AF'] = array(
                '#type' => 'details',
                '#title' => t('Fixed allowances'),
                '#collapsible' => true,
                '#open' => true,
            );

            $form['AF']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $form['AC'] = array(
                '#type' => 'details',
                '#title' => t('Custom allowances'),
                '#collapsible' => true,
                '#open' => true,
            );

            $form['AC']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $form['DF'] = array(
                '#type' => 'details',
                '#title' => t('Fixed deductions'),
                '#collapsible' => true,
                '#open' => true,
            );

            $form['DF']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            $form['DC'] = array(
                '#type' => 'details',
                '#title' => t('Custom deductions'),
                '#collapsible' => true,
                '#open' => true,
            );

            $form['DC']["headerline"] = array(
                '#type' => 'item',
                '#markup' => $headerline,
            );

            foreach ($list as $key => $value) {
                if (strpos($key, '-' . $cat)) {
                    $group = substr($key, 1, 2);

                    if (substr($group, 1, 1) == 'F') {
                        $read = false;
                    } else {
                        $read = true;
                    }


                    $form[$group][$key][$key . '-description'] = array(
                        '#type' => 'textfield',
                        '#size' => 25,
                        '#maxlength' => 100,
                        '#default_value' => $value['description'],
                        '#attributes' => ($read) ? array('placeholder' => t('description')) : array('readonly' => 'readonly'),
                        '#description' => $value['type'],
                        '#prefix' => "<tr><td>",
                        '#suffix' => '</td>',
                    );

                    $form[$group][$key][$key . '-value'] = array(
                        '#type' => 'textfield',
                        '#size' => 15,
                        '#maxlength' => 15,
                        '#default_value' => $value['value'],
                        '#attributes' => array('placeholder' => t('value')),
                        '#prefix' => "<td>",
                        '#suffix' => '</td>',
                    );

                    $form[$group][$key][$key . '-formula'] = array(
                        '#type' => 'textfield',
                        '#size' => 25,
                        '#maxlength' => 255,
                        '#default_value' => $value['formula'],
                        '#attributes' => array('placeholder' => t('formula')),
                        '#prefix' => "<td>",
                        '#suffix' => '</td>',
                    );

                    $form[$group][$key][$key . '-tax'] = array(
                        '#type' => 'select',
                        '#size' => 1,
                        '#options' => array(0 => t('no'), 1 => t('yes')),
                        '#default_value' => $value['tax'],
                        '#prefix' => "<td>",
                        '#suffix' => '</td></tr>',
                    );
                }
            }

            $form['AF']["close"] = array(
                '#type' => 'item',
                '#markup' => "</table>",
            );
            $form['AC']["close"] = array(
                '#type' => 'item',
                '#markup' => "</table>",
            );
            $form['DF']["close"] = array(
                '#type' => 'item',
                '#markup' => "</table>",
            );
            $form['DC']["close"] = array(
                '#type' => 'item',
                '#markup' => "</table>",
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

            $form['#attached']['library'][] = 'ek_hr/ek_hr.hr';
        }//if
        return $form;
    }

    /**
     * Callback
     */
    public function step_2(array &$form, FormStateInterface $form_state)
    {
        $form_state->set('step', 2);
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 3) {
            //TODO insert numeric validation for value
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 3) {
            $category = new HrSettings($form_state->getValue('coid'));
            $list = $category->HrAd[$form_state->getValue('coid')];

            foreach ($list as $key => $value) {
                if (strpos($key, '-' . $form_state->getValue('selected_category'))) {
                    $v = array(
                        'value' => Xss::filter($form_state->getValue($key . '-value')),
                        'type' => $value['type'],
                        'description' => Xss::filter($form_state->getValue($key . '-description')),
                        'formula' => Xss::filter($form_state->getValue($key . '-formula')),
                        'tax' => $form_state->getValue($key . '-tax'),
                    );

                    $category->set(
                        'ad',
                        $key,
                        $v
                    );
                }
            }

            if ($category->save()) {
                \Drupal::messenger()->addStatus(t('Settings saved'));
            }
        }//step 2
    }

    /**
     * Callback
     */
    public function categories(array &$form, FormStateInterface $form_state)
    {
        $param = new HrSettings($form_state->getValue('coid'));
        $cat = $param->HrCat[$form_state->getValue('coid')];
        $form['category']['#options'] = $cat;
        $form_state->set('opt', $cat);
        //$form_state->setRebuild();
        return $form['category'];
    }
}
