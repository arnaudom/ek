<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\FilterPostDoc.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form to post docs to project pages.
 */
class FilterPostDoc extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ek_admin_doc_post_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null, $pcode = null)
    {
        $param = unserialize($param);

        $form['source'] = array(
            '#type' => 'hidden',
            '#value' => $param[1],
        );
        $form['pcode'] = array(
            '#type' => 'hidden',
            '#value' => $pcode,
        );

        //insert the create file mode into param
        array_push($param, '1');
        $newparam = serialize($param);

        $form['param'] = array(
            '#type' => 'hidden',
            '#value' => $newparam,
        );

        $form['postdoc'] = array(
            '#type' => 'fieldset',
            '#title' => $this->t('Copy document to project') . " " . $pcode,
            '#open' => isset($param['open']) ? $param['open'] : false,
            '#attributes' => array('class' => ''),
        );

        $form['postdoc']['actions'] = array('#type' => 'actions');
        $form['postdoc']['actions']['copy'] = array(
            '#id' => 'copybuttonid',
            '#type' => 'button',
            '#value' => $this->t('Copy'),
            //'#limit_validation_errors' => array(),
            '#ajax' => array(
                'callback' => array($this, 'ProcessPost'),
                'wrapper' => 'PostMessage',
            ),
        );

        $form['postdoc']['alert'] = array(
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => "<div id='PostMessage'>",
            '#suffix' => '</div>',
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Nothing to submit.
    }

    /**
     * process mail
     */
    public function ProcessPost(array &$form, FormStateInterface $form_state)
    {
        $param = $form_state->getValue('param');
        switch ($form_state->getValue('source')) {
            //generate the pdf file and save in tmp dir
            case 'expenses_memo':
                include_once drupal_get_path('module', 'ek_finance') . '/manage_pdf_output.inc';
                $fileName = $head->serial . ".pdf";
                $file = \Drupal::service('file_system')->getTempDirectory() . "/" . $fileName;
                $sec = "fi";
                break;

            case 'invoice':
            case 'purchase':
            case 'quotation':
                include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';
                $fileName = $head->serial . ".pdf";
                $file = \Drupal::service('file_system')->getTempDirectory() . "/" . $fileName;
                $sec = "fi";
                break;

            case 'delivery':
            case 'logi_delivery':
            case 'receiving':
            case 'logi_receiving':
                include_once drupal_get_path('module', 'ek_logistics') . '/manage_pdf_output.inc';
                $fileName = $head->serial . ".pdf";
                $file = \Drupal::service('file_system')->getTempDirectory() . "/" . $fileName;
                $sec = "lo";
                break;
        }


        $pcode_parts = array_reverse(explode('-', $form_state->getValue('pcode')));
        $folder = $pcode_parts[0];
        $dir = "private://projects/documents/" . $folder;
        \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $uri = "private://projects/documents/" . $folder . "/" . $fileName;
        $copy = \Drupal::service('file_system')->copy($file, $uri);
        
        $fields = array(
            'pcode' => $form_state->getValue('pcode'),
            'filename' => \Drupal::service('file_system')->basename($copy),
            'uri' => $copy,
            'folder' => $sec,
            'sub_folder' => 'Print',
            'comment' => $this->t('System print'),
            'date' => time(),
            'size' => filesize($copy),
        );
        $insert = Database::getConnection('external_db', 'external_db')
                ->insert('ek_project_documents')
                ->fields($fields)
                ->execute();

        if ($insert) {
            $lk = ['#markup' => \Drupal\ek_projects\ProjectData::geturl($form_state->getValue('pcode'))];
            $render = \Drupal::service('renderer')->render($lk);
            $message = $this->t('Document posted to @t.', array('@t' => $render));
            $form['postdoc']['alert']['#prefix'] = "<div id='PostMessage' class='messages messages--status'>";
            $form['postdoc']['alert']['#markup'] = $message;
        } else {
            $form['postdoc']['alert']['#prefix'] = "<div id='PostMessage' class='messages messages--error'>";
            $form['postdoc']['alert']['#markup'] = $this->t('Error copy to @t', array('@t' => $form_state->getValue('pcode')));
        }

        unlink($file);
        return $form['postdoc']['alert'];
    }
}
