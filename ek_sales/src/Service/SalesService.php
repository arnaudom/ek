<?php

namespace Drupal\ek_sales\Service;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SalesService.
 */
class SalesService implements SalesServiceInterface {


    protected $extdb;
    protected $logger;
    protected $configFactory;
    protected $Financesettings;
    protected $moduleHandler;
    protected $baseCurrency;

  /**
   * Constructs a PromptService object.
   */
    public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleHandler $module_handler) {
        $this->configFactory = $config_factory;
        $this->extdb = Database::getConnection('external_db', 'external_db');
        $this->logger = $logger_factory->get('ek_sales');
        $this->baseCurrency = "";
        $this->moduleHandler = $module_handler;
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $this->Financesettings = new \Drupal\ek_finance\FinanceSettings();
            $this->baseCurrency = $this->Financesettings->get('baseCurrency');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('config.factory'),
                $container->get('logger.factory'),
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoice(array $options = []) {
        
        // Set default options
        $options += [
            'project_code' => NULL,
            'company_id' => NULL,
            'year' => NULL,
            'type' => NULL,
            'serial' => NULL,
        ];
        $type = [1 => 'invoice', 2 => 'invoice', 3 => 'debit note', 4 => 'credit note'];

        try {
            // Build the query
            $query = $this->extdb->select('ek_sales_invoice', 's')
                ->fields('s', ['serial', 'head', 'status', 'amount', 'currency', 'date', 'type', 'pcode','comment', 'client', 'amountbase']);

            if ($options['project_code']) {
                $query->condition('s.pcode',$options['project_code'], '=');
            }

            if ($options['company_id']) {
                $query->condition('s.head',$options['company_id'], '=');
            }

            if ($options['year']) {
                $query->condition('s.date',$options['year'] . '%', 'LIKE');
            }

            if ($options['type']) {
                // filter type to corresponding int
                $op = ['invoice' => 1, 'commercial' => 2, 'debit' => 3 , 'credit' => 4];
                if($options['type'] == 'invoice' || $options['type'] == 'commercial') {
                    $or = $query->orConditionGroup();
                    $or->condition('type', 1, '=');
                    $or->condition('type', 2, '=');
                    $query->condition($or);
                } else {
                    $query->condition('s.type',$op[$options['type']] , '=');
                }
            }

            if (!empty($options['serial'])) {
                $query->condition('s.serial', $options['serial'], '=');
                $query->range(0, 1);
            } else {
                $query->orderBy('s.date', 'ASC');
            }

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $inv = [];
            $stat = [0 => 'unpaid',1 => 'paid',2 => 'partially paid'];
            foreach ($results as $row) {
                $item = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'status' => $row->status . " - " . $stat[$row->status],
                'value' => $row->amount . " " . $row->currency,
                'date' => $row->date,
                'type' => $type[$row->type],
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount_base_currency' => $row->amountbase . " " . $this->baseCurrency,
                ];

                // If queried by serial, attach line items from details table
                if (!empty($options['serial'])) {
                    $item['items'] = $this->getInvoiceDetails($options['serial']);
                }

                $inv[] = $item;
            }

            return $inv;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving invoices: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve invoices data', 0, $e);
        }
    }



    /**
     * {@inheritdoc}
     */
    public function getPurchase(array $options = []) {
        // Set default options
        $options += [
            'project_code' => NULL,
            'company_id' => NULL,
            'year' => NULL,
            'type' => NULL,
            'serial' => NULL,
        ];
        $type = [1 => 'purchase', 4 => 'debit note'];

        try {
            // Build the query
            $query = $this->extdb->select('ek_sales_purchase', 'p')
                ->fields('p', ['serial', 'head', 'status', 'amount', 'currency', 'date', 'type', 'pcode','comment', 'client', 'amountbase']);

            if ($options['project_code']) {
                $query->condition('p.pcode',$options['project_code'], '=');
            }

            if ($options['company_id']) {
                $query->condition('p.head',$options['company_id'], '=');
            }

            if ($options['year']) {
                $query->condition('p.date',$options['year'] . '%', 'LIKE');
            }

            if ($options['type']) {
                // filter type to corresponding int
                $op = ['purchase' => 1, 'debit' => 4 ];
                $query->condition('p.type',$op[$options['type']] , '=');
                
            }

            if (!empty($options['serial'])) {
                $query->condition('p.serial', $options['serial'], '=');
                $query->range(0, 1);
            } else {
                $query->orderBy('p.date', 'ASC');
            }

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $pur = [];
            $stat = [0 => 'unpaid',1 => 'paid',2 => 'partially paid'];
            foreach ($results as $row) {
                $item = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'status' => $row->status . " - " . $stat[$row->status],
                'value' => $row->amount . " " . $row->currency,
                'date' => $row->date,
                'type' => $type[$row->type],
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount_base_currency' => $row->amountbase . " " . $this->baseCurrency,
                ];

                // If queried by serial, attach line items from details table
                if (!empty($options['serial'])) {
                    $item['items'] = $this->getPurchaseDetails($options['serial']);
                }

                $pur[] = $item;
            }

            return $pur;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving purchases: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve purchases data', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getQuotation(array $options = []) {
        // Set default options
        $options += [
            'project_code' => NULL,
            'company_id' => NULL,
            'year' => NULL,
            'client_id' => NULL,
            'serial' => NULL,
        ];
        

        try {
            // Build the query
            $query = $this->extdb->select('ek_sales_quotation', 'q')
                ->fields('q', ['serial', 'head', 'status', 'amount', 'currency', 'date', 'pcode','comment', 'client', 'amount']);

            if ($options['project_code']) {
                $query->condition('q.pcode',$options['project_code'], '=');
            }

            if ($options['company_id']) {
                $query->condition('q.head',$options['company_id'], '=');
            }

            if ($options['year']) {
                $query->condition('q.date',$options['year'] . '%', 'LIKE');
            }

            if ($options['client_id']) {
                $query->condition('q.client',$options['client_id'] , '=');
                
            }

            if (!empty($options['serial'])) {
                $query->condition('q.serial', $options['serial'], '=');
                $query->range(0, 1);
            } else {
                $query->orderBy('q.date', 'ASC');
            }

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $pur = [];
            foreach ($results as $row) {
                $item = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'date' => $row->date,
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount' => $row->amount . " " . $row->currency,
                ];

                // If queried by serial, attach line items from details table
                if (!empty($options['serial'])) {
                    $item['items'] = $this->getQuotationDetails($options['serial']);
                }

                $pur[] = $item;
            }

            return $pur;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving quotation: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve quotations data', 0, $e);
        }
    }

  /**
   * Get invoice line items by serial number.
   *
   * @param string $serial
   *   The invoice serial number.
   *
   * @return array
   *   Array of detail items with fields: id, serial, item, itemdetail, value,
   *   quantity, total, totalbase, aid.
   */
  private function getInvoiceDetails(string $serial): array {
      try {
          $results = $this->extdb->select('ek_sales_invoice_details', 'd')
              ->fields('d', ['id', 'serial', 'item', 'itemdetail', 'value', 'quantity', 'total', 'totalbase', 'aid'])
              ->condition('d.serial', $serial, '=')
              ->orderBy('d.id', 'ASC')
              ->execute()
              ->fetchAll();

          $items = [];
          foreach ($results as $row) {
                if($row->itemdetail != '') {
                    // the field is reference to ek_items table
                    $row->ek_items_table_id = $row->itemdetail;
                    unset($row->itemdetail);
                }
                $items[] = (array) $row;
          }

          return $items;
      } catch (\Exception $e) {
          $this->logger->error('Error retrieving invoice details for serial @serial: @message', [
              '@serial' => $serial,
              '@message' => $e->getMessage(),
          ]);

          return [];
      }
  }

  /**
   * Get purchase line items by serial number.
   *
   * @param string $serial
   *   The purchase serial number.
   *
   * @return array
   *   Array of detail items with fields: id, serial, item, itemdetail, value,
   *   quantity, total, aid.
   */
  private function getPurchaseDetails(string $serial): array {
      try {
          $results = $this->extdb->select('ek_sales_purchase_details', 'd')
              ->fields('d', ['id', 'serial', 'item', 'itemdetail', 'value', 'quantity', 'total', 'aid'])
              ->condition('d.serial', $serial, '=')
              ->orderBy('d.id', 'ASC')
              ->execute()
              ->fetchAll();

          $items = [];
          foreach ($results as $row) {
                if($row->itemdetail != '') {
                    // the field is reference to ek_items table
                    $row->ek_items_table_id = $row->itemdetail;
                    unset($row->itemdetail);
                }
               $items[] = (array) $row;
          }

          return $items;
      } catch (\Exception $e) {
          $this->logger->error('Error retrieving purchase details for serial @serial: @message', [
              '@serial' => $serial,
              '@message' => $e->getMessage(),
          ]);

          return [];
      }
  }

  /**
   * Get quotation line items by serial number.
   *
   * Maps quotation-specific field names (itemid, itemdetails, unit)
   * to standard field names (item, itemdetail, quantity).
   *
   * @param string $serial
   *   The quotation serial number.
   *
   * @return array
   *   Array of detail items with standard fields: id, serial, item, itemdetail,
   *   value, quantity, total, aid.
   */
  private function getQuotationDetails(string $serial): array {
      try {
          $results = $this->extdb->select('ek_sales_quotation_details', 'd')
              ->fields('d', ['id', 'serial', 'itemid', 'itemdetails', 'value', 'unit', 'total'])
              ->condition('d.serial', $serial, '=')
              ->orderBy('d.id', 'ASC')
              ->execute()
              ->fetchAll();

          $items = [];
          foreach ($results as $row) {
              $items[] = [
                  'id' => $row->id,
                  'serial' => $row->serial,
                  'ek_items_table_itemcode' => $row->itemid,
                  'itemdetail' => $row->itemdetails,
                  'value' => $row->value,
                  'quantity' => $row->unit,
                  'total' => $row->total,
                  'totalbase' => NULL,
                  'aid' => NULL,
              ];
          }

          return $items;
      } catch (\Exception $e) {
          $this->logger->error('Error retrieving quotation details for serial @serial: @message', [
              '@serial' => $serial,
              '@message' => $e->getMessage(),
          ]);

          return [];
      }
  }

  /**
   * {@inheritdoc}
   */
  public function editDocument(array $data): array {

    // Allowed fields per document type.
    $allowed_fields = [
      'head',
      'allocation',
      'client',
      'date',
      'pcode',
      'bank_account',
      'comment',
    ];

    // Validate required parameters.
    if (empty($data['doc_type']) || !in_array($data['doc_type'], ['quotation', 'invoice', 'purchase'])) {
      throw new \InvalidArgumentException('Invalid or missing doc_type. Must be "quotation", "invoice", or "purchase".');
    }

    if (empty($data['doc_id']) || !is_numeric($data['doc_id'])) {
      throw new \InvalidArgumentException('Invalid or missing doc_id (numeric row ID required).');
    }

    $doc_type = $data['doc_type'];
    $doc_id = (int) $data['doc_id'];
    $table_name = 'ek_sales_' . $doc_type;

    // Fetch current record to check status and get serial.
    $query = "SELECT * FROM {" . $table_name . "} WHERE id=:id";
    $record = $this->extdb->query($query, [':id' => $doc_id])->fetchObject();

    if (!$record) {
      throw new \RuntimeException("Document not found in {$table_name} with ID={$doc_id}.");
    }

    // Determine editability based on document status (same logic as QuickEdit).
    $edit = TRUE;
    if ($doc_type == 'quotation' && $record->status == 2) {
      $edit = FALSE;
    }
    if (($doc_type == 'purchase' || $doc_type == 'invoice') && ($record->status > 1 || $record->lock == 1)) {
      $edit = FALSE;
    }

    // Build dynamic fields array from provided data only.
    $fields = [];
    $updated_field_names = [];
    $pcode_value = NULL;

    // Process each allowed field present in the request.
    foreach ($allowed_fields as $field) {
      if (!array_key_exists($field, $data)) {
        continue;
      }
      if ($data[$field] === NULL || $data[$field] === '') {
        continue;
      }

      // For non-editable documents, restrict to pcode only.
      if (!$edit && $field !== 'pcode') {
        continue;
      }

      switch ($field) {
        case 'head':
          $fields['head'] = (int) $data['head'];
          break;

        case 'allocation':
          $fields['allocation'] = (int) $data['allocation'];
          break;

        case 'client':
          $fields['client'] = (int) $data['client'];
          break;

        case 'date':
          $new_date = date('Y-m-d', strtotime($data['date']));
          $fields['date'] = $new_date;
          break;

        case 'pcode':
          $pcode_input = trim($data['pcode']);
          if (!empty($pcode_input)) {
            // Validate project code via project service if module exists.
            if ($this->moduleHandler->moduleExists('ek_projects')) {
              $p = explode(' ', $pcode_input);
              $pid = \Drupal::service('project.service')->getId($p[1] ?? $p[0]);
              if ($pid) {
                $pcode_value = trim($p[1] ?? $p[0]);
              } else {
                throw new \InvalidArgumentException("Unknown project code: {$data['pcode']}");
              }
            } else {
              $pcode_value = $pcode_input;
            }
          } else {
            // Default pcode based on doc type.
            if ($doc_type == 'quotation') {
              $pcode_value = '';
            } else {
              $pcode_value = 'n/a';
            }
          }
          $fields['pcode'] = $pcode_value;
          break;

        case 'bank_account':
          // Only applicable for invoice documents.
          if ($doc_type == 'invoice') {
            $fields['bank'] = (int) $data['bank_account'];
          }
          break;

        case 'comment':
          $fields['comment'] = Xss::filter($data['comment']);
          break;
      }

      $updated_field_names[] = $field;
    }

    if (empty($updated_field_names)) {
      return [
        'success' => FALSE,
        'message' => 'No valid fields to update.',
        'updated_fields' => [],
        'serial' => $record->serial,
      ];
    }

    // Perform the update.
    $update = $this->extdb->update($table_name)
      ->fields($fields)
      ->condition('serial', $record->serial)
      ->execute();

    // Update journal entries if finance module is enabled and relevant fields changed.
    if ($this->moduleHandler->moduleExists('ek_finance') && ($doc_type == 'invoice' || $doc_type == 'purchase')) {
      // If head changed, update journal company header and account.
      if (isset($fields['head'])) {
        $coSettings = new \Drupal\ek_admin\CompanySettings($fields['head']);
        if ($doc_type == 'invoice') {
          $account = $coSettings->get('asset_account', $record->currency);
        } else {
          $account = $coSettings->get('liability_account', $record->currency);
        }
        $journalType = ($doc_type == 'invoice') ? 'debit' : 'credit';
        $this->extdb->update('ek_journal')
          ->fields(['aid' => $account])
          ->condition('source', $doc_type)
          ->condition('type', $journalType)
          ->condition('reference', $doc_id)
          ->execute();
        $this->extdb->update('ek_journal')
          ->fields(['coid' => $fields['head']])
          ->condition('source', $doc_type)
          ->condition('reference', $doc_id)
          ->execute();
      }

      // If date changed, update journal date.
      if (isset($fields['date'])) {
        $this->extdb->update('ek_journal')
          ->fields(['date' => $fields['date']])
          ->condition('source', $doc_type)
          ->condition('reference', $doc_id)
          ->execute();
      }
    }

    // Invalidate cache tag for project pages.
    Cache::invalidateTags(['project_page_view']);

    // Notify project followers if linked to a project.
    if ($this->moduleHandler->moduleExists('ek_projects') && $pcode_value && $pcode_value != 'n/a') {
      if ($doc_type != 'quotation') {
        \Drupal::service('project.service')->status($pcode_value, 'awarded');
      }
      $pid = $this->extdb->query('SELECT id FROM {ek_project} WHERE pcode=:p', [':p' => $pcode_value])->fetchField();
      if ($pid) {
        $param = serialize([
          'id' => $pid,
          'field' => $doc_type . '_edit',
          'input' => $updated_field_names,
          'value' => $record->serial,
          'pcode' => $pcode_value,
        ]);
        \Drupal::service('project.service')->notify_user($param);
      }
    }

    return [
      'success' => TRUE,
      'message' => "The {$doc_type} document updated successfully.",
      'updated_fields' => $updated_field_names,
      'serial' => $record->serial,
    ];
  }
}
