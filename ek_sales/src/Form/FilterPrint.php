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
use Drupal\ek_sales\SalesSettings;

/**
 * Provides a form to filter print docs.
 */
class FilterPrint extends FormBase {

    /**
     * 
     */
    public function __construct() {
        $this->settings = new SalesSettings();
        $this->tpls = $this->settings->get('templates');
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_print_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $source = null, $format = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_' . $source, 's');
        $query->fields('s', ['serial', 'head', 'client', 'status']);
        $query->condition('s.id', $id);
        $query->leftJoin('ek_company', 'c', 'c.id = s.head');
        $query->fields('c', ['sign']);
        $doc = $query->execute()->fetchObject();


        $form['serial'] = [
            '#type' => 'item',
            '#markup' => $doc->serial,
        ];
        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id . '_' . $source,
        ];
        $form['format'] = [
            '#type' => 'hidden',
            '#value' => $format,
        ];

        $back = Url::fromRoute('ek_sales.' . $source . 's.list', [], [])->toString();

        $form["back"] = [
            '#markup' => "<a href='" . $back . "' >" . $this->t('list') . "</a>",
        ];

        $form['filters'] = [
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => (isset($_SESSION['printfilter']['filter']) && $_SESSION['printfilter']['filter'] == $id && $format != 'excel') ? false : true,
            // '#attributes' => ['class' => ['container-inline']],
        ];

        if ($format == 'excel') {
            //add option to choose download as excel or csv
            $form['filters']['output_format'] = [
                '#type' => 'radios',
                '#options' => ['1' => $this->t('excel'), '2' => $this->t('csv')],
                '#default_value' => 1,
                '#attributes' => ['title' => $this->t('output format')],
                '#title' => $this->t('Format'),
            ];
        }

        
        $sign_type = ['0' => $this->t('no signature'), '2' => $this->t('computer signature')];
        if ($doc->sign != null && file_exists($doc->sign)) {
            $sign_type[1] = $this->t('hand signature');
        } else {            
            $form['filters']['signature_alert'] = [
                '#markup' => \Drupal\Core\Link::createFromRoute(t('Upload signature'), 'ek_admin.company.edit', ['id' => $doc->head], ['fragment' => 'edit-i'])->toString(),
            ];           
        }
        
        $form['filters']['signature'] = [
            '#type' => 'radios',
            '#options' => $sign_type,
            '#default_value' => isset($_SESSION['printfilter']['signature'][0]) ? $_SESSION['printfilter']['signature'][0] : 0,
            '#attributes' => ['title' => $this->t('signature type')],
            '#title' => $this->t('signature type'),
            '#description_display' => 'before',
            '#states' => [
                'invisible' => [':input[name="output_format"]' => ['value' => 2],],
            ]
        ];
        
        $form['filters']['s_pos'] = [
                '#type' => 'number',
                '#default_value' => isset($_SESSION['printfilter']['s_pos'][1]) ? $_SESSION['printfilter']['s_pos'][1] : 40,
                '#description' => $this->t('Adjust vertical position'),
                '#min' => 10,
                '#max' => 100,
                '#step' => 10,
                '#states' => [
                    'invisible' => [":input[name='signature']" => ['value' => 0]],
                ],
        ];           
        

        $stamps = ['0' => $this->t('no'), '1' => $this->t('original'), '2' => $this->t('copy')];

        $form['filters']['stamp'] = [
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => ['class' => ['container-inline']],
            '#title' => $this->t('stamp'),
            '#states' => [
                'invisible' => [':input[name="output_format"]' => ['value' => 2],],
            ]
        ];

        // provide selector for templates
        $list = [0 => $this->t('default')];
        if ($doc->status == '1') {
            $list['default_receipt_invoice_' . $format] = $this->t('receipt');
        }
        $templates = 'private://sales/templates/' . $source . '/';
        if (!empty($this->tpls[$source])) {
            foreach ($this->tpls[$source] as $key => $file) {
                if ($file != '.' and $file != '..') {
                    if (strpos($file, $format)) {
                        $filename = explode('.', $file);
                        $list[$file] = $filename[0];
                    }
                }
            }
        }

        $form['filters']['template'] = [
            '#type' => 'select',
            '#options' => $list,
            '#default_value' => isset($_SESSION['printfilter']['template']) ? $_SESSION['printfilter']['template'] : null,
            '#title' => $this->t('template'),
            '#prefix' => "<div class='container-inline'>",
            '#states' => [
                'invisible' => [':input[name="output_format"]' => ['value' => 2],]
            ]
        ];

        //if client has multiple contact, provide a filter for choice
        $query = 'SELECT id,contact_name FROM {ek_address_book_contacts} WHERE abid=:id';
        $contacts = Database::getConnection('external_db', 'external_db')->query($query, [':id' => $doc->client])->fetchAllKeyed();

        if (count($contacts) > 1) {
            $form['filters']['contact'] = [
                '#type' => 'select',
                '#options' => $contacts,
                '#default_value' => isset($_SESSION['printfilter']['contact']) ? $_SESSION['printfilter']['contact'] : null,
                '#title' => $this->t('addressed to'),
                '#suffix' => "</div>",
                '#states' => [
                    'invisible' => [':input[name="output_format"]' => ['value' => 2],]
                ]
            ];
        }


        $form['filters']['actions'] = [
            '#type' => 'actions',
        ];

        if ($format == 'html') {
            $form['filters']['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Display'),
            ];
        } else {
            $form['filters']['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => ($format == 'pdf') ? $this->t('Print in Pdf') : $this->t('Download'),
            ];
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
        $_SESSION['printfilter']['signature'] = [$form_state->getValue('signature'), $form_state->getValue('s_pos')];
        $_SESSION['printfilter']['stamp'] = $form_state->getValue('stamp');
        $_SESSION['printfilter']['template'] = $form_state->getValue('template');
        $_SESSION['printfilter']['contact'] = $form_state->getValue('contact');
        $filter = explode('_', $form_state->getValue('for_id'));
        $_SESSION['printfilter']['filter'] = $filter[0];
        $_SESSION['printfilter']['format'] = $form_state->getValue('format');
        if ($form_state->getValue('format') == 'excel') {
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
