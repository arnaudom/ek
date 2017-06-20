<?php
/**
* @file
* Contains \Drupal\ek\Controller\EkController.
*/
namespace Drupal\ek_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
* Controller routines for ek module routes.
*/
class SettingsController extends ControllerBase {



/**
   *  
   *
*/
  public function settings(Request $request) {
  
      $form_builder = $this->formBuilder();
      $response = $form_builder->getForm('Drupal\ek_finance\Form\FinanceSettingsForm' );

      return array(
      '#theme' => 'ek_finance_settings_form',
      '#items' => $response,
      '#title' => t('Edit finance settings' ),
      '#attached' => array(
         'library' => array('ek_finance/ek_finance'),
      ),
    ); 
  
  }









} //class