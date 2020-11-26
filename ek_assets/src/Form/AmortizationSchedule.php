<?php

/**
 * @file
 * Contains \Drupal\ek_assets\Form\AmortizationSchedule.
 */

namespace Drupal\ek_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_assets\Amortization;

/**
 * Provides a form to manage amortization schedule of assets.
 */
class AmortizationSchedule extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_amortization_schedule_asset';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        $url = Url::fromRoute('ek_assets.list', array(), array())->toString();
        $form['back'] = [
            '#type' => 'item',
            '#markup' => $this->t("<a href='@url'>List</a>", ['@url' => $url]),
        ];
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_assets', 'a');
        $query->fields('a');
        $query->condition('id', $id);
        $query->condition('coid', $access, 'IN');
        $query->leftJoin('ek_assets_amortization', 'b', 'a.id = b.asid');
        $query->fields('b');
        $data = $query->execute()->fetchObject();

        $current_amortization = Amortization::is_amortized($id);

        if (!isset($data->id)) {
            $form['alert'] = [
                '#type' => 'item',
                '#markup' => $this->t('You cannot edit this asset amortization schedule.'),
            ];
        } else {
            if ($data->amort_record && null == $form_state->get('calcul')) {
                $form_state->set('schedule', unserialize($data->amort_record));
                $no_record = false;
            } elseif ($form_state->get('schedule')) {
                $no_record = true;
            } else {
                $form_state->set('schedule', null);
                $no_record = true;
            }

            $form['for_id'] = [
                '#type' => 'hidden',
                '#value' => $id,
            ];
            
            $form['coid'] = [
                '#type' => 'hidden',
                '#value' => $data->coid,
            ];
            
            $form['date_purchase'] = [
                '#type' => 'hidden',
                '#value' => $data->date_purchase,
            ];

            $form["asset_value"] = [
                '#type' => 'hidden',
                '#value' => $data->asset_value,
            ];

            $coids = AccessCheck::CompanyListByUid();
            $form['company'] = [
                '#type' => 'item',
                '#markup' => $coids[$data->coid],
            ];

            $form["asset_name"] = [
                '#type' => 'item',
                '#markup' => $data->asset_name . ' (' . $data->asset_ref . ')',
            ];

            $form["value"] = [
                '#type' => 'item',
                '#markup' => number_format($data->asset_value, 2) . ' ' . $data->currency,
            ];

            $form['date'] = [
                '#type' => 'item',
                '#markup' => $this->t('date of purchase') . ': ' . $data->date_purchase,
            ];

            $st = ['0' => $this->t('Not amortized'), '1' => $this->t('Amortized')];
            $form["amort_status"] = [
                '#type' => 'item',
                '#markup' => $st[$data->amort_status] . ' (' . $data->aid . ')',
            ];

            $form["method"] = [
                '#type' => 'select',
                '#size' => 1,
                '#disabled' => $current_amortization,
                '#options' => ['1' => $this->t('Straight line')],
                '#title' => $this->t('Amortization method'),
                '#default_value' => isset($data->method) ? $data->method : '1',
            ];

            $form["term_unit"] = [
                '#type' => 'select',
                '#size' => 1,
                '#disabled' => $current_amortization,
                '#options' => array('Y' => $this->t('Year'), 'M' => $this->t('Month')),
                '#title' => $this->t('Amortization term unit'),
                '#default_value' => isset($data->term_unit) ? $data->term_unit : 'Y',
            ];

            $form["term"] = [
                '#type' => 'textfield',
                '#size' => 8,
                '#disabled' => $current_amortization,
                '#default_value' => isset($data->term) ? $data->term : '0',
                '#maxlength' => 15,
                '#title' => $this->t('Amortization term (No. of years or months)'),
            ];

            $form["amort_salvage"] = [
                '#type' => 'textfield',
                '#size' => 20,
                '#disabled' => $current_amortization,
                '#default_value' => isset($data->amort_salvage) ? $data->amort_salvage : '0',
                '#maxlength' => 35,
                '#title' => $this->t('Amortization salvage value'),
            ];

            $table_head = "<div class='table'  id='schedule_table'>
                  <div class='row'>
                      <div class='cell cellborder' id='tour-item1'>" . $this->t("Date") . "</div>
                      <div class='cell cellborder' id='tour-item2'>" . $this->t("Value") . "</div>
                      <div class='cell cellborder' id='tour-item3'>" . $this->t("Status") . "</div>
                      <div class='cell cellborder' id='tour-item4'>" . $this->t("Action") . "</div>
                  </div>";


            $form['i'] = [
                '#type' => 'details',
                '#title' => $this->t('Schedule'),
                '#collapsible' => true,
                '#open' => true,
                '#prefix' => "<div id='schedule'>",
                '#suffix' => "</div>",
            ];

            $record = 0;

            if ($form_state->get('schedule')) {
                $i = 0;
                $flag = 0;

                $schedule = $form_state->get('schedule');

                $form['i']['table_head'] = [
                    '#type' => 'item',
                    '#prefix' => $table_head,
                ];

                foreach ($schedule['a'] as $key => $value) {
                    if ($value['journal_reference'] != '') {
                        $status = $this->t('expense') . ': ' . $value['journal_reference']['expense'] . '<br/>';
                        $status .= $this->t('journal') . ': ' . $value['journal_reference']['journal'];
                        $action = '';
                        $record++;
                    } elseif ($value['journal_reference'] == '' && $flag == '0' && !$no_record) {
                        $status = $this->t('No record');
                        $url = Url::fromRoute('ek_assets.record_amortization', ['id' => $id, 'ref' => $i], [])->toString();
                        $action = $this->t("<a href='@url'>Record</a>", ['@url' => $url]);
                        $flag = '1';
                    } else {
                        $status = $this->t('No record');
                        $action = '';
                    }

                    $form['i']['date ' . $i] = [
                        '#type' => 'item',
                        '#markup' => $value['record_date'],
                        '#prefix' => "<div class='row' ><div class='cell'>",
                        '#suffix' => '</div>',
                    ];
                    
                    $form['i']['value ' . $i] = [
                        '#type' => 'item',
                        '#markup' => number_format($value['value'], 2),
                        '#prefix' => "<div class='cell'>",
                        '#suffix' => '</div>',
                    ];

                    $form['i']['status ' . $i] = [
                        '#type' => 'item',
                        '#markup' => $status,
                        '#prefix' => "<div class='cell cellcenter'>",
                        '#suffix' => '</div>',
                    ];

                    $form['i']['action ' . $i] = [
                        '#type' => 'item',
                        '#markup' => $action,
                        '#prefix' => "<div class='cell cellcenter'>",
                        '#suffix' => '</div></div>',
                    ];
                    $i++;
                }

                $form['i']['table_foot1'] = [
                    '#type' => 'item',
                    '#markup' => $this->t('Records') . ': ' . $schedule['years'],
                    '#prefix' => "<div class='row'><div class='cell cellborder'>",
                    '#suffix' => "</div>",
                ];
                
                $form['i']['table_foot2'] = [
                    '#type' => 'item',
                    '#markup' => number_format($schedule['total'], 2),
                    '#prefix' => "<div class='cell cellborder'>",
                    '#suffix' => "</div></div>",
                ];

                $form['schedule'] = [
                    '#type' => 'hidden',
                    '#value' => serialize($form_state->get('schedule')),
                ];
            }


            if ($record == '0') {
                $form['actions'] = [
                    '#type' => 'actions',
                    '#attributes' => ['class' => ['container-inline']],
                ];
                
                $form['actions']['calculate'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Calculate'),
                    '#submit' => [[$this, 'calculate_schedule']],
                ];
                
                $form['actions']['save'] = [
                    '#type' => 'submit',
                    '#value' => $this->t('Save'),
                ];
            } else {
                $form['alert'] = [
                    '#type' => 'item',
                    '#markup' => $this->t("Amortization already recorded in journal. The schedule cannot be changed"),
                ];

                $form['edit'] = [
                    '#type' => 'hidden',
                    '#value' => 0,
                ];
            }
        }

        $form['#attached']['library'][] = 'ek_assets/ek_assets_css';
        return $form;
    }

    /**
     * callback functions
     */
    public function calculate_schedule(array &$form, FormStateInterface $form_state) {
        $form_state->set('schedule', Amortization::schedule(
                        $form_state->getValue('method'),
                        $form_state->getValue('term_unit'),
                        $form_state->getValue('term'),
                        $form_state->getValue('asset_value') - $form_state->getValue('amort_salvage'),
                        $form_state->getValue('date_purchase'),
                        $form_state->getValue('coid')
        ));
        $form_state->set('calcul', 1);
        $form_state->setRebuild();

        return $form['i'];
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (!is_numeric($form_state->getValue('term'))) {
            $form_state->setErrorByName('term', $this->t('Non numeric value inserted: @v', ['@v' => $form_state->getValue('term')]));
        }
        if (!is_numeric($form_state->getValue('amort_salvage'))) {
            $form_state->setErrorByName('amort_salvage', $this->t('Non numeric value inserted: @v', ['@v' => $form_state->getValue('term')]));
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if (null == $form_state->getValue('edit')) {
            $fields = array(
                'term_unit' => $form_state->getValue('term_unit'),
                'term' => $form_state->getValue('term'),
                'method' => $form_state->getValue('method'),
                'amort_salvage' => $form_state->getValue('amort_salvage'),
                'amort_record' => $form_state->getValue('schedule'),
            );

            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_assets_amortization')
                    ->condition('asid', $form_state->getValue('for_id'))
                    ->fields($fields)
                    ->execute();
            
            \Drupal\Core\Cache\Cache::invalidateTags(['ek.assets:' . $form_state->getValue('for_id')]);
            
        }
    }

}
