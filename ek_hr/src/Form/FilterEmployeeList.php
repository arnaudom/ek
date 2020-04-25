<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\FilterEmployeeList.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to filter employee list.
 */
class FilterEmployeeList extends FormBase
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
        return 'employee_list_filter';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $company = AccessCheck::CompanyListByUid();

        $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter'),
      '#open' => true,
      '#attributes' => array('class' => array('container-inline')),
    );
        $form['filters']['filter'] = array(
              '#type' => 'hidden',
              '#value' => 'filter',
              
            );
        $form['filters']['coid'] = array(
              '#type' => 'select',
              '#size' => 1,
              '#options' => $company,
              '#default_value' => isset($_SESSION['hrlfilter']['coid']) ? $_SESSION['hrlfilter']['coid'] : null,
              '#title' => t('company'),
              '#required' => true,
              '#prefix' => "",
              '#suffix' => '',
              );

  
        $form['filters']['status'] = array(
              '#type' => 'select',
              '#options' => array('working' => t('Working'), 'resigned' => t('Resigned'), 'absent' => t('Absent') ),
              '#default_value' => isset($_SESSION['hrlfilter']['status']) ? $_SESSION['hrlfilter']['status'] : 'working' ,
            );
            
            

        $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    
        $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      //'#suffix' => "</div>",
    );

        if (!empty($_SESSION['hrlfilter'])) {
            $form['filters']['actions']['reset'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => array(),
        '#submit' => array(array($this, 'resetForm')),
      );
        }
        return $form;
    }
  
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }
  
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['hrlfilter']['coid'] = $form_state->getValue('coid');
        $_SESSION['hrlfilter']['status'] = $form_state->getValue('status');
        $_SESSION['hrlfilter']['filter'] = 1;
    }
  
    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state)
    {
        $_SESSION['hrlfilter'] = array();
    }
}
