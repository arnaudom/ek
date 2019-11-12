<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Controller\JournalEntryController.
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
     * Constructs a JournalEntryController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     */
    public function __construct(FormBuilderInterface $form_builder) {
        $this->formBuilder = $form_builder;
    }

    /**
     *  display form to record journal entry
     *  @return array
     *  form
     *
     */
    public function entryjournal(Request $request) {

        $build['journal_entry'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\JournalEntry');
        Return $build;
    }

    /**
     *  display form to edit journal entry
     * 
     *  @param int $id
     *      id of journal entry
     *
     */
    public function editjournal($id) {

        $company = AccessCheck::GetCompanyByUser();
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j');
            $query->condition('id', $id, '=');
            
        $data = $query->execute()->fetchObject();
        $edit = TRUE;
        if(!$data) {
            $edit = FALSE;
        }
        elseif (!in_array($data->coid, $company)) {
            //user has no access to this information
            $edit = FALSE;
        }
        elseif ($data->reconcile == '1') {
            $edit = FALSE; 
        } elseif($data->reconcile == '0') {
            //need to check double entry recociliation status
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_journal', 'j');
            $query->fields('j',['reconcile']);
            $query->condition('source', $data->source, '=');
            $query->condition('reference', $data->reference, '=');
            $query->condition('comment', $data->comment, '=');
            $query->condition('id', $data->id, '<>');
            if ($query->execute()->fetchField() == '1'){
               $edit = FALSE; 
            }
               
        } elseif ( $data->source != 'general' || $data->source != 'general cash' ) {
            //TODO check this condition is valid : || $data->source != 'payment'
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
            $items['type'] = 'access';
            $items['message'] = ['#markup' => t('This journal entry is not editable or was deleted.')];
            $items['link'] = ['#markup' => t('Go to <a href="@url" >Journal</a>', ['@url' => $url])];
            return [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
            return $items;
            
           
        }
        Return $build;
    }

}

//class