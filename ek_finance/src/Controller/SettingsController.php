<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\SettingsController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller routines for ek module routes.
 */
class SettingsController extends ControllerBase
{

    /**
     *  General parameters settings for finance
     *  @return array
     *      render Html
     */
    public function settings(Request $request)
    {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_finance\Form\FinanceSettingsForm');

        return array(
            '#theme' => 'ek_finance_settings_form',
            '#items' => $response,
            '#title' => $this->t('Edit finance settings'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
        );
    }

    /**
     *  General parameters settings for finance
     *  @return array
     *      render Html
     */
    public function moveData()
    {
        $form_builder = $this->formBuilder();
        
        if (isset($_SESSION['moveLog'])) {
            $url = file_create_url($_SESSION['moveLog']);
            $items['log'] = ['#markup' => "<a target='_blank' href='". $url ."'>" . $this->t('View last log') . "</a>"];
            $_SESSION['moveLog'] = null;
        }
        
        $items['form'] = $form_builder->getForm('Drupal\ek_finance\Form\moveData');
        
        return $items;
    }
}
