<?php

/**
 * @file
 * Contains \Drupal\ek_sales\Form\SettingsFormCustomize.
 */

namespace Drupal\ek_sales\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Color as ColorUtility;
use Drupal\ek_sales\SalesSettings;

/**
 * Provides a form to customize pdf form
 */
class SettingsFormCustomize extends FormBase {

    /**
     *
     */
    public function __construct() {
        $this->settings = new SalesSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_sales_form_customize';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $format = 'pdf';
        unset($_SESSION['prev']);
        $s = $this->settings->get('custom_form');

        $form['format'] = [
            '#type' => 'hidden',
            '#value' => $format,
        ];

        // provide selector for templates

        if ($form_state->get('step') == '') {
            $form_state->set('step', 1);
        }

        $list['Invoice'] = ['0|default_invoice_pdf' => $this->t('default invoice')];
        $list['Invoice'] += ['0|default_receipt_invoice_pdf' => $this->t('default receipt')];

        $templates = 'private://sales/templates/invoice/';
        if (file_exists($templates)) {
            $handle = opendir('private://sales/templates/invoice/');
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    if (strpos($file, $format)) {
                        $filename = explode('.', $file);
                        $list['Invoice']['invoice|'.$file] = $filename[0];
                    }
                }
            }
        }

        $list['Purchase'] =['0|default_purchase_pdf' => $this->t('default purchase')];
        $templates = 'private://sales/templates/purchase/';
        if (file_exists($templates)) {
            $handle = opendir('private://sales/templates/purchase/');
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    if (strpos($file, $format)) {
                        $filename = explode('.', $file);
                        $list['Purchase']['purchase|'.$file] = $filename[0];
                    }
                }
            }
        }

        $list['Quotation'] = ['0|default_quotation_pdf' => $this->t('default quotation')];
        $templates = 'private://sales/templates/quotation/';
        if (file_exists($templates)) {
            $handle = opendir('private://sales/templates/quotation/');
            while ($file = readdir($handle)) {
                if ($file != '.' and $file != '..') {
                    if (strpos($file, $format)) {
                        $filename = explode('.', $file);
                        $list['Quotation']['quotation|'.$file] = $filename[0];
                    }
                }
            }
        }

        $form['template'] = [
            '#type' => 'select',
            '#required' => True,
            '#options' => $list,
            '#default_value' => $form_state->getValue('template') ? $form_state->getValue('template') : null,
            '#disabled' => $form_state->getValue('template') ? true : false,
            '#title' => $this->t('templates'),
            '#prefix' => "<div class='container-inline'>",
        ];

        
        $form['next'] = [
                '#type' => 'submit',
                '#value' => ($form_state->getValue('template')) ? $this->t('Reset') : $this->t('Select'),
                '#suffix' => "</div>",
                '#states' => [
                    // Hide data fieldset when class is empty.
                    'invisible' => array(
                        "select[name='template']" => array('value' => ''),
                    ),
                ],
            ];
        

        $form['preview'] = [
            '#type' => 'item',
            '#prefix' => '<div id="preview">',
            '#suffix' => '</div>',
        ];

        if ($form_state->get('step') == 2) {

            $tpl = explode('|',$form_state->getValue('template'));
            
            
            
            $form['actions'] = [
                '#type' => 'actions',
                '#weight' => 1,
            ];

            $form['actions']['preview'] = [
                '#type' => 'button',
                '#value' => $this->t('Preview'),
                '#ajax' => [
                    'callback' => array($this, 'preview'),
                    'wrapper' => 'preview',
                ],
            ];

            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
            ];
            
            if(isset($s[$tpl[1]]['doc'])) {
                $form['delete'] = [
                    '#type' => 'checkbox',
                    '#title' => $this->t('Delete custom data'),
                ];
            }

            // Doc style

            $form['doc'] = [
                '#type' => 'details',
                '#title' => $this->t('Document style'),
                '#open' => false,
                '#weight' => 2,
            ];

            $form['doc']['orientation'] = [
                '#type' => 'select',
                '#options' => ['P' => $this->t('Portrait'), 'L' => $this->t('Landscape')],
                '#default_value' => isset($s[$tpl[1]]['doc']['orientation']) ? 
                $s[$tpl[1]]['doc']['orientation'] : 'P',
                '#title' => $this->t('Orientation'),
                '#prefix' => "<div class='container-inline'>",
            ];
            $form['doc']['format'] = [
                '#type' => 'select',
                '#options' => ['A1' => 'A1', 'A2' => 'A2', 'A3' => 'A3', 'A4' => 'A4', 'A5' => 'A5'],
                '#default_value' => isset($s[$tpl[1]]['doc']['format']) ?
                $s[$tpl[1]]['doc']['format'] : 'A4',
                '#title' => $this->t('Format'),
                '#suffix' => '</div>'
            ];
            $form['doc']['margin_left'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 90,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_left']) ?
                $s[$tpl[1]]['doc']['margin_left'] : 10,
                '#title' => $this->t('Left margin'),
                '#prefix' => "<div class='container-inline'>",
            ];
            $form['doc']['margin_top'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_top']) ?
                $s[$tpl[1]]['doc']['margin_top'] : 60,
                '#title' => $this->t('Top margin'),
                '#suffix' => '</div>'
            ];
            $form['doc']['margin_right'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 50,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_right']) ?
                $s[$tpl[1]]['doc']['margin_right'] : 15,
                '#title' => $this->t('Right margin'),
                '#prefix' => "<div class='container-inline'>",
            ];
            $form['doc']['margin_bottom'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_bottom']) ?
                $s[$tpl[1]]['doc']['margin_bottom'] : 25,
                '#title' => $this->t('Bottom margin'),
                '#suffix' => '</div>'
            ];
            
            $form['doc']['margin_header'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 50,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_header']) ?
                $s[$tpl[1]]['doc']['margin_header'] : 5,
                '#title' => $this->t('Bottom header'),
                '#prefix' => "<div class='container-inline'>",
            ];
            
            $form['doc']['margin_footer'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 50,
                '#default_value' => isset($s[$tpl[1]]['doc']['margin_footer']) ?
                $s[$tpl[1]]['doc']['margin_footer'] : 10,
                '#title' => $this->t('Bottom footer'),
                '#suffix' => '</div>'
            ];

            // Header

            $form['header'] = [
                '#type' => 'details',
                '#title' => $this->t('Document header style'),
                '#open' => false,
                '#weight' => 3,
            ];

            $form['header']['border'] = [
                '#type' => 'select',
                '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes')],
                '#default_value' => isset($s[$tpl[1]]['header']['border']) ?
                $s[$tpl[1]]['header']['border'] : 0,
                '#title' => $this->t('Border visible'),
            ];

            $form['header']['left_margin'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 15,
                '#default_value' => isset($s[$tpl[1]]['header']['left_margin']) ?
                $s[$tpl[1]]['header']['left_margin'] : 2,
                '#title' => $this->t('Left margin'),
            ];

            $form['header']['col_1'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['col_1']) ?
                $s[$tpl[1]]['header']['col_1'] : 50,
                '#title' => $this->t('Column') . " 1",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['header']['col_2'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['col_2']) ?
                $s[$tpl[1]]['header']['col_2'] : 50,
                '#title' => $this->t('Column') . " 2",
                '#suffix' => "</div>"
            ];

            $form['header']['col_3'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['col_3']) ?
                $s[$tpl[1]]['header']['col_3'] : 50,
                '#title' => $this->t('Column') . " 3",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['header']['col_4'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['col_4']) ?
                $s[$tpl[1]]['header']['col_4'] : 50,
                '#title' => $this->t('Column') . " 4",
                '#suffix' => "</div>"
            ];

            $form['header']['logo_x'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['logo_x']) ?
                $s[$tpl[1]]['header']['logo_x'] : 20,
                '#title' => $this->t('Logo horizontal offset'),
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['header']['logo_y'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['logo_y']) ?
                $s[$tpl[1]]['header']['logo_y'] : 10,
                '#title' => $this->t('Logo vertical offset'),
            ];

            $form['header']['logo_z'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['header']['logo_z']) ?
                $s[$tpl[1]]['header']['logo_z'] : 50,
                '#title' => $this->t('Logo zoom'),
                '#suffix' => "</div>"
            ];

            $form['header']['font'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 20,
                '#default_value' => isset($s[$tpl[1]]['header']['font']) ?
                $s[$tpl[1]]['header']['font'] : 12,
                '#title' => $this->t('Max. font size'),
            ];

            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['header']['color'])){
                $c = ColorUtility::rgbToHex($s[$tpl[1]]['header']['color']);
            } else {
                $c = ColorUtility::rgbToHex('128,128,128');
            }
            
            $form['header']['color'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Color'),
            ];

            // Footer

            $form['footer'] = [
                '#type' => 'details',
                '#title' => $this->t('Document footer style'),
                '#open' => false,
                '#weight' => 4,
            ];

            $form['footer']['border'] = [
                '#type' => 'select',
                '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes')],
                '#default_value' => isset($s[$tpl[1]]['footer']['border']) ?
                $s[$tpl[1]]['footer']['border'] : 0,
                '#title' => $this->t('Border visible'),
            ];

            $form['footer']['left_margin'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 15,
                '#default_value' => isset($s[$tpl[1]]['footer']['left_margin']) ?
                $s[$tpl[1]]['footer']['left_margin'] : 2,
                '#title' => $this->t('Left margin'),
            ];

            $form['footer']['col_1'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['footer']['col_1']) ?
                $s[$tpl[1]]['footer']['col_1'] : 15,
                '#title' => $this->t('Column') . " 1",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['footer']['col_2'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['footer']['col_2']) ?
                $s[$tpl[1]]['footer']['col_2'] : 30,
                '#title' => $this->t('Column') . " 2",
                '#suffix' => "</div>",
            ];

            $form['footer']['col_3'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['footer']['col_3']) ?
                $s[$tpl[1]]['footer']['col_3'] : 60,
                '#title' => $this->t('Column') . " 3",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['footer']['col_4'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['footer']['col_4']) ?
                $s[$tpl[1]]['footer']['col_4'] : 80,
                '#title' => $this->t('Column') . " 4",
                '#suffix' => "</div>",
            ];

            $form['footer']['w_data'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 100,
                '#default_value' => isset($s[$tpl[1]]['footer']['w_data']) ?
                $s[$tpl[1]]['footer']['w_data'] : -45,
                '#title' => $this->t('Text offset'),
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['footer']['w_page'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['footer']['w_page']) ?
                $s[$tpl[1]]['footer']['w_page'] : -25,
                '#title' => $this->t('Page No. offset'),
                '#suffix' => "</div>",
            ];

            $form['footer']['font'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 20,
                '#default_value' => isset($s[$tpl[1]]['footer']['font']) ?
                $s[$tpl[1]]['footer']['font'] : 8,
                '#title' => $this->t('Max. font size'),
            ];
            
            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['header']['color'])){
                $c = ColorUtility::rgbToHex(isset($s[$tpl[1]]['footer']['color']));
            } else {
                $c = ColorUtility::rgbToHex('128,128,128');
            }
            
            $form['footer']['color'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Color'),
            ];

            // Feature

            $form['feature'] = [
                '#type' => 'details',
                '#title' => $this->t('Document feature style'),
                '#open' => false,
                '#weight' => 5,
            ];

            $form['feature']['border'] = [
                '#type' => 'select',
                '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes')],
                '#default_value' => isset($s[$tpl[1]]['feature']['border']) ?
                $s[$tpl[1]]['feature']['border'] : 0,
                '#title' => $this->t('Border visible'),
            ];

            $form['feature']['left_margin'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 15,
                '#default_value' => isset($s[$tpl[1]]['feature']['left_margin']) ?
                $s[$tpl[1]]['feature']['left_margin'] : 1,
                '#title' => $this->t('Left margin'),
            ];

            $form['feature']['col_1'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['col_1']) ?
                $s[$tpl[1]]['feature']['col_1'] : 45,
                '#title' => $this->t('Column') . " 1",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['feature']['col_2'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['col_2']) ?
                $s[$tpl[1]]['feature']['col_2'] : 70,
                '#title' => $this->t('Column') . " 2",
                '#suffix' => "</div>",
            ];

            $form['feature']['col_3'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['col_3']) ?
                $s[$tpl[1]]['feature']['col_3'] : 30,
                '#title' => $this->t('Column') . " 3",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['feature']['col_4'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['col_4']) ?
                $s[$tpl[1]]['feature']['col_4'] : 35,
                '#title' => $this->t('Column') . " 4",
                '#suffix' => "</div>",
            ];

            $form['feature']['font'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 20,
                '#default_value' => isset($s[$tpl[1]]['feature']['font']) ?
                $s[$tpl[1]]['feature']['font'] : 14,
                '#title' => $this->t('Max. font size'),
            ];
            
            $form['feature']['stamp_x'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['stamp_x']) ?
                $s[$tpl[1]]['feature']['stamp_x'] : 110,
                '#title' => $this->t('Stamp horizontal offset'),
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['feature']['stamp_y'] = [
                '#type' => 'number',
                '#min' => -100,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['stamp_y']) ?
                $s[$tpl[1]]['feature']['stamp_y'] : 90,
                '#title' => $this->t('Stamp vertical offset'),
            ];

            $form['feature']['stamp_z'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['feature']['stamp_z']) ?
                $s[$tpl[1]]['feature']['stamp_z'] : 35,
                '#title' => $this->t('Stamp zoom'),
                '#suffix' => "</div>",
            ];
            
            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['feature']['colortitle'])){
                $c = ColorUtility::rgbToHex(isset($s[$tpl[1]]['feature']['colortitle']));
            } else {
                $c = ColorUtility::rgbToHex('120,150,190');
            }
            
            $form['feature']['colortitle'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Title color'),
                '#prefix' => "<div class='container-inline'>",
            ];
            
            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['feature']['color'])){
                $c = ColorUtility::rgbToHex(isset($s[$tpl[1]]['feature']['color']));
            } else {
                $c = ColorUtility::rgbToHex('128,128,128');
            }
            
            $form['feature']['color'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Color'),
                '#suffix' => "</div>",
            ];

            // Body

            $form['body'] = [
                '#type' => 'details',
                '#title' => $this->t('Document items style'),
                '#open' => false,
                '#weight' => 6,
            ];

            $form['body']['border'] = [
                '#type' => 'select',
                '#options' => ['0' => $this->t('No'), '1' => $this->t('Yes')],
                '#default_value' => isset($s[$tpl[1]]['body']['border']) ?
                $s[$tpl[1]]['body']['border'] : 0,
                '#title' => $this->t('Border visible'),
            ];

            $form['body']['left_margin'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 15,
                '#default_value' => isset($s[$tpl[1]]['body']['left_margin']) ?
                $s[$tpl[1]]['body']['left_margin'] : 1,
                '#title' => $this->t('Left margin'),
            ];

            $form['body']['col_1'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_1']) ?
                $s[$tpl[1]]['body']['col_1'] : 7,
                '#title' => $this->t('Column') . " 1",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['body']['col_2'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_2']) ?
                $s[$tpl[1]]['body']['col_2'] : 110,
                '#title' => $this->t('Column') . " 2",
                '#suffix' => "</div>",
            ];

            $form['body']['col_3'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_3']) ?
                $s[$tpl[1]]['body']['col_3'] : 20,
                '#title' => $this->t('Column') . " 3",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['body']['col_4'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_4']) ?
                $s[$tpl[1]]['body']['col_4'] : 20,
                '#title' => $this->t('Column') . " 4",
                
            ];

            $form['body']['col_5'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_5']) ?
                $s[$tpl[1]]['body']['col_5'] : 25,
                '#title' => $this->t('Column') . " 5",
                '#suffix' => "</div>",
            ];
            
            
            $form['body']['col_6'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_6']) ?
                $s[$tpl[1]]['body']['col_6'] : 30,
                '#title' => $this->t('Column') . " 6",
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['body']['col_7'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 200,
                '#default_value' => isset($s[$tpl[1]]['body']['col_7']) ?
                $s[$tpl[1]]['body']['col_7'] : 35,
                '#title' => $this->t('Column') . " 7",
                '#suffix' => "</div>",
            ];

            $form['body']['font'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 20,
                '#default_value' => isset($s[$tpl[1]]['body']['font']) ?
                $s[$tpl[1]]['body']['font'] : 10,
                '#title' => $this->t('Max. font size'),
            ];
            
            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['body']['color'])){
                $c = ColorUtility::rgbToHex(isset($s[$tpl[1]]['body']['color']));
            } else {
                $c = ColorUtility::rgbToHex('128,128,128');
            }
            
            $form['body']['color'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Color'),
                '#prefix' => "<div class='container-inline'>",
            ];
            
            // convert rgb used color to valid format for form
            if(isset($s[$tpl[1]]['body']['fillcolor'])){
                $c = ColorUtility::rgbToHex($s[$tpl[1]]['body']['fillcolor']);
            } else {
                $c = ColorUtility::rgbToHex('238,238,238');
            }
            
            $form['body']['fillcolor'] = [
                '#type' => 'color',
                '#default_value' => $c,
                '#title' => $this->t('Fill color'),
                '#suffix' => "</div>",
            ];
            
            $form['body']['cut'] = [
                '#type' => 'number',
                '#min' => 0,
                '#max' => 350,
                '#default_value' => isset($s[$tpl[1]]['body']['cut']) ?
                $s[$tpl[1]]['body']['cut'] : 230,
                '#title' => $this->t('Max. height'),
            ];

            $form['doc']['#tree'] = true;
            $form['header']['#tree'] = true;
            $form['footer']['#tree'] = true;
            $form['feature']['#tree'] = true;
            $form['body']['#tree'] = true;
        }



        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->setRebuild();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $triggering_element = $form_state->getTriggeringElement();

        if ($triggering_element['#id'] != 'edit-next') {
            
                $file = explode('|',$form_state->getValue('template'));
                $s = $this->settings->get('custom_form');
                                
            if($form_state->getValue('delete') == 1) {
                
                unset($s[$file[1]]);
                $this->settings->set('custom_form', $s);
                $save = $this->settings->save();
                \Drupal::messenger()->addWarning(t('The settings are deleted'));
                
            } else {
            
                $doc = $form_state->getValue('doc');
                $header = $form_state->getValue('header');
                $footer = $form_state->getValue('footer');
                $feature = $form_state->getValue('feature');
                $body = $form_state->getValue('body');

                $s[$file[1]] = [
                    'source' => $file[0],
                    'doc' => [
                        'orientation' => $doc['orientation'],
                        'format' => $doc['format'],
                        'margin_left' => $doc['margin_left'],
                        'margin_top' => $doc['margin_top'],
                        'margin_right' => $doc['margin_right'],
                        'margin_bottom' => $doc['margin_bottom'],
                        'margin_header' => $doc['margin_header'],
                        'margin_footer' => $doc['margin_footer'],
                    ],
                    'header' => [
                        'border' => $header['border'],
                        'left_margin' => $header['left_margin'],
                        'col_1' => $header['col_1'],
                        'col_2' => $header['col_2'],
                        'col_3' => $header['col_3'],
                        'col_4' => $header['col_4'],
                        'logo_x' => $header['logo_x'],
                        'logo_y' => $header['logo_y'],
                        'logo_z' => $header['logo_z'],
                        'color' => ColorUtility::hexToRgb($header['color']),
                        'font' => $header['font'],
                    ],
                    'footer' => [
                        'border' => $footer['border'],
                        'left_margin' => $footer['left_margin'],
                        'col_1' => $footer['col_1'],
                        'col_2' => $footer['col_2'],
                        'col_3' => $footer['col_3'],
                        'col_4' => $footer['col_4'],
                        'w_data' => $footer['w_data'],
                        'w_page' => $footer['w_page'],
                        'color' => ColorUtility::hexToRgb($footer['color']),
                        'font' => $footer['font'],
                    ],
                    'feature' => [
                        'border' => $feature['border'],
                        'left_margin' => $feature['left_margin'],
                        'col_1' => $feature['col_1'],
                        'col_2' => $feature['col_2'],
                        'col_3' => $feature['col_3'],
                        'col_4' => $feature['col_4'],
                        'stamp_x' => $feature['stamp_x'],
                        'stamp_y' => $feature['stamp_y'],
                        'stamp_z' => $feature['stamp_z'],
                        'colortitle' => ColorUtility::hexToRgb($feature['colortitle']),
                        'color' => ColorUtility::hexToRgb($feature['color']),
                        'font' => $feature['font'],
                    ],
                    'body' => [
                        'border' => $body['border'],
                        'left_margin' => $body['left_margin'],
                        'col_1' => $body['col_1'],
                        'col_2' => $body['col_2'],
                        'col_3' => $body['col_3'],
                        'col_4' => $body['col_4'],
                        'col_5' => $body['col_5'],
                        'col_6' => $body['col_6'],
                        'col_7' => $body['col_7'],
                        'cut' => $body['cut'],
                        'font' => $body['font'],
                        'color' => ColorUtility::hexToRgb($body['color']),
                        'fillcolor' => ColorUtility::hexToRgb($body['fillcolor']),
                    ]
                ];
                $this->settings->set('custom_form', $s);
                $save = $this->settings->save();
                \Drupal::messenger()->addStatus(t('The settings are recorded'));
            }
            
            unset($_SESSION['prev']);
        }
        
    }

    /**
     * reset
     */
    public function reset(array &$form, FormStateInterface $form_state) {
        $form['preview'] = ['#type' => 'item','#markup' => '','#prefix' => '<div id="preview">','#suffix' => '</div>',];
        return $form['preview'];
    }
    /**
     * preview
     */
    public function preview(array &$form, FormStateInterface $form_state) {
        $doc = $form_state->getValue('doc');
        $header = $form_state->getValue('header');
        $footer = $form_state->getValue('footer');
        $feature = $form_state->getValue('feature');
        $body = $form_state->getValue('body');
        $file = explode('|',$form_state->getValue('template'));
        $_SESSION['prev'][$file[1]] = [
            'source' => $file[0],
            'doc' => [
                'orientation' => $doc['orientation'],
                'format' => $doc['format'],
                'margin_left' => $doc['margin_left'],
                'margin_top' => $doc['margin_top'],
                'margin_right' => $doc['margin_right'],
                'margin_bottom' => $doc['margin_bottom'],
                'margin_header' => $doc['margin_header'],
                'margin_footer' => $doc['margin_footer'],
            ],
            'header' => [
                'border' => $header['border'],
                'left_margin' => $header['left_margin'],
                'col_1' => $header['col_1'],
                'col_2' => $header['col_2'],
                'col_3' => $header['col_3'],
                'col_4' => $header['col_4'],
                'logo_x' => $header['logo_x'],
                'logo_y' => $header['logo_y'],
                'logo_z' => $header['logo_z'],
                'color' => ColorUtility::hexToRgb($header['color']),
                'font' => $header['font'],
            ],
            'footer' => [
                'border' => $footer['border'],
                'left_margin' => $footer['left_margin'],
                'col_1' => $footer['col_1'],
                'col_2' => $footer['col_2'],
                'col_3' => $footer['col_3'],
                'col_4' => $footer['col_4'],
                'w_data' => $footer['w_data'],
                'w_page' => $footer['w_page'],
                'color' => ColorUtility::hexToRgb($footer['color']),
                'font' => $footer['font'],
            ],
            'feature' => [
                'border' => $feature['border'],
                'left_margin' => $feature['left_margin'],
                'col_1' => $feature['col_1'],
                'col_2' => $feature['col_2'],
                'col_3' => $feature['col_3'],
                'col_4' => $feature['col_4'],
                'stamp_x' => $feature['stamp_x'],
                'stamp_y' => $feature['stamp_y'],
                'stamp_z' => $feature['stamp_z'],
                'color' => ColorUtility::hexToRgb($feature['color']),
                'colortitle' => ColorUtility::hexToRgb($feature['colortitle']),
                'font' => $feature['font'],
            ],
            'body' => [
                'border' => $body['border'],
                'left_margin' => $body['left_margin'],
                'col_1' => $body['col_1'],
                'col_2' => $body['col_2'],
                'col_3' => $body['col_3'],
                'col_4' => $body['col_4'],
                'col_5' => $body['col_5'],
                'col_6' => $body['col_6'],
                'col_7' => $body['col_7'],
                'cut' => $body['cut'],
                'font' => $body['font'],
                'color' => ColorUtility::hexToRgb($body['color']),
                'fillcolor' => ColorUtility::hexToRgb($body['fillcolor']),
            ]
        ];

        $form['preview'] = [
            '#type' => 'inline_template',
            '#template' => '<iframe src="{{ url }}"  width="100%" height="500px" id="view" name="view"></iframe>',
            '#context' => [
                'url' => '/ek_sales/admin/settings-form-preview',
            ],
            '#prefix' => '<div id="preview">',
            '#suffix' => '</div>',
        ];
        
        return $form['preview'];
    }

}
