<?php

/**
 * @file
 * Contains \Drupal\ek_products\Form\LinkItem.
 */

namespace Drupal\ek_products\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to link items
 */
class LinkItem extends FormBase {

    protected $extdb;

    
    public function __construct() {
        $this->extdb = Database::getConnection('external_db', 'external_db');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        $instance = new static();
        // Don't inject anything if you're not using it in the constructor
        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'link_item';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form['info'] = [
            '#type' => 'item',
            '#markup' => $this->t('This form will create a link between 2 items.'),
        ];

        $form['parent'] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => true,
            '#default_value' => null,
            '#attributes' => ['placeholder' => $this->t('Ex. IT123')],
            '#title' => $this->t('Parent item'),
            '#autocomplete_route_name' => 'ek.look_up_item_ajax',
            '#autocomplete_route_parameters' => ['id' => 0, 'status' => '1'],
        ];

        $form['child'] = [
            '#type' => 'textfield',
            '#size' => 50,
            '#maxlength' => 150,
            '#required' => true,
            '#default_value' => null,
            '#attributes' => ['placeholder' => $this->t('Ex. AB876')],
            '#title' => $this->t('Child item'),
            '#autocomplete_route_name' => 'ek.look_up_item_ajax',
            '#autocomplete_route_parameters' => ['id' => 0, 'status' => '1'],
        ];


        $form['next'] = [
            '#type' => 'submit',
            '#value' => $this->t('Link'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
            $p = explode(' ', $form_state->getValue('parent'));
            $parentid = (int) $p[0];
            $parentitemcode = $p[1];

            $query = $this->extdb->select('ek_items', 'i')
                    ->fields('i', ['id'])
                    ->condition('itemcode', $parentitemcode)
                    ->execute();
            $id = (int) $query->fetchField();


            if ($id && $id == $parentid) {
                $form_state->setValue('parent', $parentitemcode);
            } else {
                $form_state->setErrorByName('parent', $this->t('Unknown parent item'));
            }

            $p = explode(' ', $form_state->getValue('child'));
            $childid = (int) $p[0];
            $childitemcode = $p[1];

            $query = $this->extdb->select('ek_items', 'i')
                    ->fields('i', ['id'])
                    ->condition('itemcode', $childitemcode)
                    ->execute();
            $id = (int) $query->fetchField();
            
            if ($id && $id == $childid) {
                $form_state->setValue('child', $childitemcode);
            } else {
                $form_state->setErrorByName('child', $this->t('Unknown child item'));
            }
        
            $query = $this->extdb->select('ek_item_relations', 'i')
                    ->fields('i', ['id'])
                    ->condition('parent_itemcode', $parentitemcode)
                    ->condition('child_itemcode', $childitemcode)
                    ->execute();
            $id = $query->fetchField();
            if($id > 0 ) {
                $form_state->setErrorByName('child', $this->t('Item is already linked'));
            }

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

            $parent = $form_state->getValue('parent');
            $child = $form_state->getValue('child');

            
            $this->extdb
                    ->insert('ek_item_relations')
                    ->fields(['parent_itemcode' => $parent, 'child_itemcode' => $child, 'relation_type' => 'accessory'])
                    ->execute();

            \Drupal::messenger()->addStatus(t('@n linked to @u', ['@n' => $child, '@u' => $parent]));
        
    }

}
