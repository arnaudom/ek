<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Controller routines for ek module routes.
 */
class JournalEntryController extends ControllerBase {

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('form_builder')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     */
    public function __construct(FormBuilderInterface $form_builder) {
        $this->formBuilder = $form_builder;
    }

    /**
     *  display form to record journal entry
     *
     */
    public function entryjournal(Request $request) {

        $build['journal_entry'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\JournalEntry');
        Return $build;
    }

    /**
     *  display form to edit journal entry
     *  @id = id of journal entry
     *
     */
    public function editjournal($id) {

        $company = AccessCheck::GetCompanyByUser();
        $query = "SELECT * FROM {ek_journal} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':id' => $id))->fetchObject();
        $edit = TRUE;

        if (!in_array($data->coid, $company)) {
            $edit = FALSE;
        }
        if ($data->reconcile == 1) {
            $edit = FALSE; 
        }
        if ($data->source != 'general' && $data->source != 'payment') {
            $edit = FALSE;
        }

        if ($edit == TRUE) {
            $param = ['id' => $id, 
                'coid' => $data->coid, 
                'source' => $data->source, 
                'reference' => $data-> reference,
                'currency' => $data->currency,
                'date' => $data->date,
                    ];
            $build['journal_edit'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\JournalEdit', $param);
        } else {
            $url = Url::fromRoute('ek_finance.extract.general_journal', array(), array())->toString();
            $build = ['#markup' => t('This journal entry is not editable or was deleted. <a href="@url" >Go to journal.</a>.', ['@url' => $url])];
        }
        Return $build;
    }

}

//class