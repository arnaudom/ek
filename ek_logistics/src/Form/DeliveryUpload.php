<?php

/**
 * @file
 * Contains \Drupal\ek_logistics\Form\DeliveryUpload.
 */

namespace Drupal\ek_logistics\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;

/**
 * Provides a form to upload data into delivery from external sources.
 */
class DeliveryUpload extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_logistics_delivery_upload_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        if (null == $form_state->get('step')) {
            $form_state->set('step', 1);
        }
        $form['csv'] = array(
            '#type' => 'details',
            '#title' => $this->t('Upload'),
            '#open' => ($form_state->get('step') == 1) ? true : false,
        );
        if ($form_state->get('step') == 1) {
            $form['csv']['file'] = array(
                '#type' => 'file',
                '#title' => t('Upload'),
                '#description' => $this->t('Select file to upload'),
            );


            $form['csv']['info'] = array(
                '#type' => 'item',
                '#markup' => $this->t('The import format should be a text csv file.'),
            );
        } else {
            $file = $form_state->get('data');

            $form['csv']['info'] = array(
                '#type' => 'item',
                '#markup' => $file->getFileName(),
            );
        }

        $form['csv']['source'] = array(
            '#type' => 'select',
            '#options' => ['1' => 'Lazada'],
            '#default_value' => $form_state->getValue('source'),
            '#required' => true,
            '#title' => $this->t('Data source'),
        );

        if ($form_state->get('step') == 1) {
            $form['csv']['actions']['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next') . ' >>',
                '#submit' => array(array($this, 'step_2')),
            );
        }


        if ($form_state->get('step') == 2) {
            switch ($form_state->getValue('source')) {
                case '1': //Lazada
                    $delimiter = ';';
                    //$enclose = '';
                    $ignore = 'IGNORE 1 LINES';
                    $header = ['Item Id', 'Lazada Id', 'Seller SKU', 'Lazada SKU', 'Created at',
                        'Updated at', 'Order Number', 'Invoice Required', 'Customer Name',
                        'Customer Email', 'National Registration Number', 'Shipping Name',
                        'Shipping Address', 'Shipping Address2', 'Shipping Address3',
                        'Shipping Address4', 'Shipping Address5', 'Shipping Phone Number',
                        'Shipping Phone Number2', 'Shipping City', 'Shipping Postcode',
                        'Shipping Country', 'Shipping Region', 'Billing Name', 'Billing Address',
                        'Billing Address2', 'Billing Address3', 'Billing Address4',
                        'Billing Address5', 'Billing Phone Number', 'Billing Phone Number2',
                        'Billing City', 'Billing Postcode', 'Billing Country',
                        'Payment Method', 'Paid Price', 'Unit Price', 'Shipping Fee',
                        'Wallet Credits', 'Item Name', 'Variation', 'CD Shipping Provider',
                        'Shipping Provider', 'Shipment Type Name', 'Shipping Provider Type',
                        'CD Tracking Code', 'Tracking Code', 'Tracking URL',
                        'Shipping Provider (first mile)', 'Tracking Code (first mile)',
                        'Tracking URL (first mile)', 'Promised shipping time',
                        'Premium', 'Status', 'Reason'];
                    break;
            }

            $file = $form_state->get('data');
            $path = "temporary://" . $file->getFileName();
            //$handle = fopen($path, "r");
            $item = 0;
            //$raw = file_get_contents($path);
            //$raw = preg_replace('/\r\n|\n\r|\n|\r/', '\n', $raw);
            //$raw = preg_replace('/"|,/', '', $raw);
            //$fp = fopen($path, 'w');
            //$written = fwrite($fp, $raw);
            //fclose($fp);
            //$text = fgetcsv($handle, 1000, ';');
            //dpm($text);
            //preg_replace('/\r\n|\n\r|\n|\r/', '\n', $subject);



            if (($handle = fopen($path, "r")) !== false) {
                # Set the parent multidimensional array key to 0.
                $nn = 0;
                while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {

                    # skip
                    if ($nn == 0) {
                        //$nn++; continue;
                    }
                    # Count the total keys in the row.
                    # Populate the multidimensional array.
                    for ($x = 0; $x < 55; $x++) {
                        $csvarray[$nn][$x] = $data[$x];
                    }
                    $nn++;
                }
                # Close the File.
                fclose($handle);
            }


            /*

              do {

              if ($data[0]) {

              //if (is_numeric($data[0])) { dpm($data[0]);
              //first field is an item ID
              $list = '<ul>items';
              for ($i = 0; $i < count($data); $i++) {

              $list .= '<li>' . $header[$i] . ':         ' . $data[$i] . '</li>';
              }
              $list .= '</ul>';

              $form[$item] = array(
              '#type' => 'item',
              '#markup' => $list,
              );
              // }
              }

              $item++;
              } while ($data = fgetcsv($handle, 1000, $delimiter));
             */
            $form['actions'] = array('#type' => 'actions');
            $form['actions']['submit'] = array('#type' => 'submit', '#value' => $this->t('Import'));
        }




        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            /**/
            $extensions = 'csv';
            $validators = array('file_validate_extensions' => array($extensions));
            $file = file_save_upload("file", $validators, false, 0);

            if ($file) {
                $form_state->set('data', $file);
            } else {
                $form_state->setErrorByName('file', $this->t('Wrong format of file'));
            }
        }
    }

    /*
     * Callback
     */

    public function step_2(array &$form, FormStateInterface $form_state) {
        $form_state->set('step', 2);

        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
    }

}
