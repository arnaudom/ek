<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\FilterPrint.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form to filter print docs.
 */
class FilterPrint extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_print_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $source = NULL, $format = NULL) {
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_'. $source, 's');
            $query->fields('s',['serial','head','client','status']);
            $query->condition('s.id', $id);
            $query->leftJoin('ek_company', 'c', 'c.id = s.head');
            $query->fields('c', ['sign']);
        $doc = $query->execute()->fetchObject();
                

        $form['serial'] = array(
            '#type' => 'item',
            '#markup' => $doc->serial,
        );
        $form['for_id'] = array(
            '#type' => 'hidden',
            '#value' => $id . '_' . $source,
        );
        $form['format'] = array(
            '#type' => 'hidden',
            '#value' => $format,
        );

        $back = Url::fromRoute('ek_sales.' . $source . 's.list', array(), array())->toString();

        $form["back"] = array(
        '#markup' => "<a href='" . $back . "' >" . t('list') . "</a>" ,
        );
;    
        $form['filters'] = array(
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id && $format != 'excel') ? FALSE : TRUE,
            '#attributes' => array('class' => array('container-inline')),
        );

        if($format == 'excel') {
            //add option to choose download as excel or csv

            $form['filters']['output_format'] = array(
                '#type' => 'radios',
                '#options' => array('1' => t('excel'), '2' => t('csv')),
                '#default_value' => 1,
                '#attributes' => array('title' => t('output format')),
                '#title' => t('Format'),
            );
        }
        
        if($doc->sign != NULL && file_exists($doc->sign)){
            $form['filters']['signature'] = array(
                '#type' => 'checkbox',
                '#default_value' => isset($_SESSION['printfilter']['signature']) ? $_SESSION['printfilter']['signature'] :0,
                '#attributes' => array('title' => t('signature')),
                '#title' => t('signature'),
                '#states' => array(
                    'invisible' => array(':input[name="output_format"]' => array('value' => 2),
                    ),
                    )
            );
        } else {
            $form['filters']['signature'] = array(
                '#type' => 'hidden',
                '#value' => 0,
            );
            $form['filters']['signature_alert'] = array(
                '#markup' => \Drupal\Core\Link::createFromRoute(t('Upload signature'), 'ek_admin.company.edit', ['id' => $doc->head], ['fragment' => 'edit-i'])->toString(),
            );
            
        }

        $stamps = array('0' => t('no'), '1' => t('original'), '2' => t('copy'));

        $form['filters']['stamp'] = array(
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => array('title' => t('stamp')),
            '#title' => t('stamp'),
            '#states' => array(
                'invisible' => array(':input[name="output_format"]' => array('value' => 2),
                ),
                )
        );

        //provide selector for templates

        $list = array(0 => t('default'));
        if ($doc->status == '1') {
            $list['default_receipt_invoice_' . $format] = t('receipt');
        }
        $templates = 'private://sales/templates/' . $source . '/';
        if(file_exists($templates)) {
            $handle = opendir('private://sales/templates/' . $source . '/');
            while ($file = readdir($handle)) {
                if ($file != '.' AND $file != '..') {
                    if(strpos($file, $format)){
                        $filename = explode('.', $file);
                        $list[$file] = $filename[0];
                    }
                }
            }
        }

        $form['filters']['template'] = array(
            '#type' => 'select',
            '#options' => $list,
            '#default_value' => isset($_SESSION['printfilter']['template']) ? $_SESSION['printfilter']['template'] : NULL,
            '#title' => t('template'),
            '#states' => array(
                'invisible' => array(':input[name="output_format"]' => array('value' => 2),
                ),
                )            
        );

        //if client has multiple contact, provide a filter for choice
        $query = 'SELECT id,contact_name FROM {ek_address_book_contacts} WHERE abid=:id';
        $contacts = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $doc->client))->fetchAllKeyed();

        if (count($contacts) > 1) {
            $form['filters']['contact'] = array(
                '#type' => 'select',
                '#options' => $contacts,
                '#default_value' => isset($_SESSION['printfilter']['contact']) ? $_SESSION['printfilter']['contact'] : NULL,
                '#title' => t('addressed to'),
                '#states' => array(
                    'invisible' => array(':input[name="output_format"]' => array('value' => 2),
                    ),
                )
            );
        }
        

        $form['filters']['actions'] = array(
            '#type' => 'actions',
            '#attributes' => array('class' => array()),
        );
        
        if($format == 'html') {
            $form['filters']['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Display'),
            );            
        } else {
            $form['filters']['actions']['submit'] = array(
                '#type' => 'submit',
                '#value' => ($format == 'pdf') ? $this->t('Print in Pdf') : $this->t('Download'),
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

        $_SESSION['printfilter']['for_id'] = $form_state->getValue('for_id');
        $_SESSION['printfilter']['signature'] = $form_state->getValue('signature');
        $_SESSION['printfilter']['stamp'] = $form_state->getValue('stamp');
        $_SESSION['printfilter']['template'] = $form_state->getValue('template');
        $_SESSION['printfilter']['contact'] = $form_state->getValue('contact');
        $filter = explode('_', $form_state->getValue('for_id'));
        $_SESSION['printfilter']['filter'] = $filter[0];
        $_SESSION['printfilter']['format'] = $form_state->getValue('format');
        if($form_state->getValue('format') == 'excel') {
            $_SESSION['printfilter']['output_format'] = $form_state->getValue('output_format');
        } else {
            $_SESSION['printfilter']['output_format'] = $form_state->getValue('format');
        }
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['printfilter'] = array();
    }

}
