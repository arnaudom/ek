<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\EditCountryForm.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;

/**
 * Provides an item form.
 */
class EditCountryForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('country_manager')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
     *   The country manager.
     */
    public function __construct(CountryManagerInterface $country_manager) {
        $this->countryManager = $country_manager;
    }

    /**
     * {@inheritdoc}
     */

    public function getFormId() {
        return 'ek_edit_country_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_country', 'c')
                        ->fields('c')
                        ->orderBy('name');
        $data = $query->execute();
        
        $header1 = [
            'name' => $this->t('Name'),
            'entity' => $this->t('Entity'),
            'active' => $this->t('Status') . " (" . $this->t('uncheck to desactivate') . ")", 
        ];
        
        $header2 = [
            'name' => $this->t('Name'),
            'entity' => $this->t('Entity'),
            'active' => $this->t('Status') . " (" . $this->t('select to activate') . ")", 
        ];
        
        $form['active'] = array(
            '#type' => 'table',
            '#header' => $header1,
            '#caption' => ['#markup' => '<h2>' . $this->t('active') . '</h2>'],
        );
        
        $form['non_active'] = array(
            '#type' => 'table',
            '#header' => $header2,
            '#caption' => ['#markup' => '<h2>' . $this->t('non active') . '</h2>'],
        );
        
        $options = [];

        while ($r = $data->fetchAssoc()) {

            $id = $r['id'];

            if ($r['status'] == 1) {

                $form['active'][$id]['name'] = array(
                    '#type' => 'item',
                    '#markup' => $r['name'],
                    
                );

                $form['active'][$id]['entity'] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => isset($r['entity']) ? $r['entity'] : NULL,
                    
                );

                $form['active'][$id]['status'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 1,
                    
                );
            } else {

                $form['non_active'][$id]['name'] = array(
                    '#type' => 'item',
                    '#markup' => $r['name'],
                    
                );

                $form['non_active'][$id]['entity'] = array(
                    '#type' => 'textfield',
                    '#size' => 30,
                    '#maxlength' => 255,
                    '#default_value' => isset($r['entity']) ? $r['entity'] : NULL,
                    
                );

                $form['non_active'][$id]['status'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 0,
                    '#description' => '',
                );
            }
        }

        $form['#tree'] = TRUE;
        
        
        $countries = $this->countryManager->getList();
        
        $form['new_country'] = [
        '#type' => 'select',
        '#title' => $this->t('New country'),
        '#empty_value' => '',
        '#options' => $countries,
        '#description' => $this->t('Add a country for the site.'),
      ];
        
        
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Record'));



        return $form;
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        
        foreach($form_state->getValue('active') as $key => $data) {
            
            $fields = [
                'entity' => $data['entity'],
                'status' => $data['status'],
                    ] ;
            
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_country')
                    ->condition('id', $key)
                    ->fields($fields)
                    ->execute();
            
        }
        foreach($form_state->getValue('non_active') as $key => $data) {
            
            $fields = [
                'entity' => $data['entity'],
                'status' => $data['status'],
                    ] ;
            
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_country')
                    ->condition('id', $key)
                    ->fields($fields)
                    ->execute();
            
        }
        
        
        if(!null == $form_state->getValue('new_country')) {
            $newCountry = $form_state->getValue('new_country');
            $countries = $this->countryManager->getList();
            $countryName = (string) $countries[$newCountry];
            
            $query = "SELECT id FROM {ek_country} WHERE code=:code";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':code' => $newCountry])
                    ->fetchField();
                    if($data) {
                        \Drupal::messenger()->addWarning(t('New selected country already exists'));
                    } else {
                        $insert = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_country')
                            ->fields(['access' => '', 'status' => 1, 'entity' => '', 'code' => $newCountry, 'name' => $countryName])
                            ->execute();
                    }
        }

        \Drupal::messenger()->addStatus(t('Country data updated'));
        
        if($_SESSION['install'] == 1){
            unset($_SESSION['install']);
            $form_state->setRedirect('ek_admin.main');
        } else {
            $form_state->setRedirect('ek_admin.country.list');
        }
        
            
    }

}
