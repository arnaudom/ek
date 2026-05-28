<?php

/**
 * @file
 * Contains \Drupal\ek_projects\Form\SearchDocumentProject
 */

namespace Drupal\ek_projects\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_projects\Service\ProjectService;

/**
 * Provides a form for project search by keyword.
 */
class SearchDocumentProject extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_projects_search_document';
    }

    protected $projectService;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('project.service')
        );
    }

    /**
     * Constructs an  object.
     *
     */
    public function __construct(ProjectService $projectService) {
        $this->projectService = $projectService;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['search'] = [
            '#type' => 'textfield',
            '#id' => 'document-search-field',
            '#size' => 30,
            '#prefix' => '<div class="container-inline">',
            '#attributes' => array('placeholder' => $this->t('type document name')),
            '#title' => $this->t('Project document'),
        ];

        $form['list_search_result'] = [
            '#type' => 'item',
            '#markup' => $this->t("Document(s)") . ":",
            '#suffix' => "<ul id='list_search_result'></ul>",
        ];

        $form['#attached']['library'][] = 'ek_projects/ek_projects_search_document';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
    }

}