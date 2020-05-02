<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\YearNewController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for ek module routes.
 */
class YearNewController extends ControllerBase
{

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a YearNewController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler)
    {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     *  Post accounting data to a new year
     *  @return array
     *      form
     */
    public function newyear(Request $request)
    {
        $build['form'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\PostNewYear');

        return $build;
    }
}
