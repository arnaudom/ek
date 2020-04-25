<?php

/**
 * @file
 * Contains \Drupal\ek_hr\Form\EditCategory.
 */

namespace Drupal\ek_hr\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_hr\HrSettings;

;
/**
 * Provides a form to create or edit HR categories
 */
class EditCategory extends FormBase
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
        return 'category_edit';
    }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null)
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
    
    );

        if ($form_state->getValue('coid') == '') {
            $form['next'] = array(
    '#type' => 'submit',
    '#value' => t('Next'). ' >>',
    '#states' => array(
        // Hide data fieldset when class is empty.
        'invisible' => array(
           "select[name='coid']" => array('value' => ''),
        ),
      ),
  );
        }
 
        if ($form_state->get('step') == 2) {
            $form_state->set('step', 3);
  
            //verify if the settings table has the company
            $query = "SELECT count(coid) from {ek_hr_workforce_settings} where coid=:c";
            $row = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $form_state->getValue('coid') ))->fetchField();
  
            if ($row != 1) {
                Database::getConnection('external_db', 'external_db')
          ->insert('ek_hr_workforce_settings')
          ->fields(array('coid' => $form_state->getValue('coid') ))
          ->execute();
            }
  
  
            $category = new HrSettings($form_state->getValue('coid'));
            $list = $category->HrCat[$form_state->getValue('coid')];
     
 
  
            if (empty($list)) {
                $list = array(
    $form_state->getValue('coid') => array(
    'a' => 'category a',
    'b' => 'category b',
    'c' => 'category c',
    'd' => 'category d',
    'e' => 'category e',
    )
    );
  
                Database::getConnection('external_db', 'external_db')
          ->update('ek_hr_workforce_settings')
          ->fields(array('cat' => serialize($list) ))
          ->condition('coid', $form_state->getValue('coid'))
          ->execute();
          
                $category = new HrSettings($form_state->getValue('coid'));
                $list = $category->HrCat[$form_state->getValue('coid')];
            }
            $link = Url::fromRoute('ek_hr.parameters-ad', array(), array())->toString();
            $form['info'] = array(
      '#type' => 'item',
      '#markup' => t('Input the description name for each category used. For each category you can define specific parameters in <a href="@l">Allowances</a>', array('@l' => $link)),
    );
            foreach ($list as $key => $value) {
                $form[$key] = array(
      '#type' => 'textfield',
      '#size' => 50,
      '#maxlength' => 100,
      '#default_value' => $value,
      '#attributes' => array('placeholder'=>t('name of category')),
      '#title' => t('Category @c', array('@c' => $key)),
    );
            }//for

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
        } //if
        return $form;
    }


 
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
    }
  
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        if ($form_state->get('step') == 3) {
            $category = new HrSettings($form_state->getValue('coid'));
            $list = $category->HrCat[ $form_state->getValue('coid') ];
  
            foreach ($list as $key => $value) {
                $input = $form_state->getValue($key) ;
                $category->set(
                    'cat',
                    $key,
                    $input
                );
            }

            $category->save();
        }//step 3
    }
}
