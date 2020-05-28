<?php

/**
 * @file
 * Contains \Drupal\ek_intelligence\Form\DeleteForm.
 */

namespace Drupal\ek_intelligence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Provides a form to delete business report.
 */
class DeleteForm extends FormBase {

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
        return 'ek_report_delete_item';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $url = Url::fromRoute('ek_intelligence.report', array(), array())->toString();
        $form['back'] = array(
            '#type' => 'item',
            '#markup' => $this->t('<a href="@url" >Reports list</a>', array('@url' => $url)),
        );


        $query = "SELECT serial,owner,description FROM {ek_ireports} "
                . " WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        $access = AccessCheck::GetCompanyByUser();
        $coid = implode(',', $access);

        $form['edit_item'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Report : @p', array('@p' => $data->serial)),
        );
        $form['edit_item2'] = array(
            '#type' => 'item2',
            '#markup' => $this->t('Description : @p', array('@p' => $data->description)),
        );

        $del = 1;
        if (!in_array(\Drupal::currentUser()->id(), $access)) {
            $del = 0;
            $message = $this->t('You are not authorized to delete the report from this company.');
        } elseif ($data->owner != \Drupal::currentUser()->id()) {
            $del = 0;
            $message = $this->t('You are not editor. the report cannot be deleted.');
        }


        if ($del != 0) {
            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Are you sure you want to delete this report?'),
            );

            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
            );
        } else {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $message,
            );
        }
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
        $delete = Database::getConnection('external_db', 'external_db')
                ->delete('ek_ireports')
                ->condition('id', $form_state->getValue('for_id'))
                ->execute();

        if ($delete) {
            \Drupal::messenger()->addStatus(t('The report data have been deleted'));
            $form_state->setRedirect("ek_intelligence.report");
        }
    }

}

//class
