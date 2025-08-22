<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\FilterPrint.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ek_logistics\LogisticsSettings;

/**
 * Provides a form to filter print docs.
 */
class FilterPrint extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_logistics_print_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $source = null, $format = null) {
        
        if ($source == 'delivery') {
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_delivery', 's');
            $route = 'delivery';
        } else {
            $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_logi_receiving', 's');
            $route = '';
        }
        
        $query->fields('s');
        $query->condition('s.id', $id);
        $query->leftJoin('ek_company', 'c', 'c.id = s.head');
        $query->fields('c', ['sign']);
        $doc = $query->execute()->fetchObject();


        if ($route != 'delivery') {
            if ($doc->type == 'RR') {
                $route = 'receiving';
            } else {
                $route = 'returning';
            }
        }
        $back = Url::fromRoute('ek_logistics_list_' . $route, [], [])->toString();
        $form["back"] = [
            '#markup' => "<a href='" . $back . "' >" . $this->t('list') . "</a>",
        ];

        $form['serial'] = [
            '#type' => 'item',
            '#markup' => "<h2>" . $doc->serial . "</h2>",
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id . '_' . $source,
        ];

        $form['for_id'] = [
            '#type' => 'hidden',
            '#value' => $id . '_' . $source,
        ];

        $form['format'] = [
            '#type' => 'hidden',
            '#value' => $format,
        ];

        $form['filters'] = [
            '#type' => 'details',
            '#title' => $this->t('Options'),
            '#open' => true,
            '#attributes' => ['class' => ['container-inline']],
        ];

        if ($doc->sign != null && file_exists($doc->sign)) {
            $form['filters']['signature'] = [
                '#type' => 'checkbox',
                '#default_value' => isset($_SESSION['logisticprintfilter']['signature'][0]) ? $_SESSION['logisticprintfilter']['signature'][0] : 0,
                '#attributes' => ['title' => $this->t('signature')],
                '#title' => $this->t('signature'),
                '#states' => [
                    'invisible' => [':input[name="output_format"]' => ['value' => 2],],
                    ]
                ];

            $form['filters']['s_pos'] = [
                '#type' => 'number',
                '#default_value' => isset($_SESSION['logisticprintfilter']['signature'][1]) ? $_SESSION['logisticprintfilter']['signature'][1] : 40,
                '#description' => $this->t('Adjust vertical position'),
                '#min' => 10,
                '#max' => 100,
                '#step' => 10,
                '#states' => [
                    'visible' => [":input[name='signature']" => ['checked' => true]],
                ],
            ];
        } else {
            $form['filters']['signature'] = [
                '#type' => 'hidden',
                '#value' => 0,
            ];

            $form['filters']['signature_alert'] = [
                '#markup' => \Drupal\Core\Link::createFromRoute(t('Upload signature'), 'ek_admin.company.edit', ['id' => $doc->head], ['fragment' => 'edit-i'])->toString(),
            ];
        }
        

        $stamps = ['0' => $this->t('no'), '1' => $this->t('original'), '2' => $this->t('copy')];

        $form['filters']['stamp'] = [
            '#type' => 'radios',
            '#options' => $stamps,
            '#default_value' => 0,
            '#attributes' => ['title' => $this->t('stamp')],
            '#title' => $this->t('stamp'),
        ];
        
        //
        // provide selector for templates
        //
        $settings = new LogisticsSettings($doc->head);
        $list = [0 => 'default'];
        $tpls = $settings->get('templates');
        /*$handle = opendir('private://logistics/templates/' . $doc->head . '/' . $format . '/');
        while ($file = readdir($handle)) {
            if ($file != '.' and $file != '..') {
                $list[$file] = $file;
            }
        }*/
        if (!empty($tpls[$format])) {
            foreach ($tpls[$format] as $key => $file) {
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
            '#default_value' => $_SESSION['logisticprintfilter']['template'],
            '#title' => $this->t('template'),
        ];

       
        $query =Database::getConnection('external_db', 'external_db')->select('ek_address_book_contacts', 't');
        $query->fields('t',['id', 'contact_name']);
        if($route == 'delivery') {
            $query->condition('abid', $doc->client);
        } else {
            $query->condition('abid', $doc->supplier);
        }
        $contacts = $query->execute()->fetchAllKeyed();
        if (count($contacts) > 1) {
            $form['filters']['contact'] = [
                '#type' => 'select',
                '#options' => $contacts,
                '#default_value' => $_SESSION['logisticprintfilter']['contact'],
                '#title' => $this->t('addressed to'),
            ];
        }

        $form['filters']['actions'] = [
            '#type' => 'actions',
            '#attributes' => ['class' => ['container-inline']],
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
        $_SESSION['logisticprintfilter']['for_id'] = $form_state->getValue('for_id');
        $_SESSION['logisticprintfilter']['signature'] = [$form_state->getValue('signature'), $form_state->getValue('s_pos')];
        $_SESSION['logisticprintfilter']['stamp'] = $form_state->getValue('stamp');
        $_SESSION['logisticprintfilter']['template'] = $form_state->getValue('template');
        $filter = explode('_', $form_state->getValue('for_id'));
        $_SESSION['logisticprintfilter']['filter'] = $filter[0];
        $_SESSION['logisticprintfilter']['contact'] = $form_state->getValue('contact');
        $_SESSION['logisticprintfilter']['format'] = $form_state->getValue('format');
    }

    /**
     * Resets the filter form.
     */
    public function resetForm(array &$form, FormStateInterface $form_state) {
        $_SESSION['logisticprintfilter'] = array();
    }

}
