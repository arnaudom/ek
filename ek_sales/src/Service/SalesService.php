<?php

namespace Drupal\ek_sales\Service;

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
            'type' => NULL
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

            $query->orderBy('s.date', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $inv = [];
            foreach ($results as $row) {
                $inv[] = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'status' => $row->status,
                'value' => $row->amount . " " . $row->currency,
                'date' => $row->date,
                'type' => $type[$row->type],
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount_base_currency' => $row->amountbase . " " . $this->baseCurrency,

                ];
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
            'type' => NULL
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

            $query->orderBy('p.date', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $pur = [];
            foreach ($results as $row) {
                $pur[] = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'status' => $row->status,
                'value' => $row->amount . " " . $row->currency,
                'date' => $row->date,
                'type' => $type[$row->type],
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount_base_currency' => $row->amountbase . " " . $this->baseCurrency,

                ];
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
            'client_id' => NULL
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

            $query->orderBy('q.date', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $pur = [];
            foreach ($results as $row) {
                $pur[] = [
                'serial' => $row->serial,
                'company_id' => $row->head,
                'date' => $row->date,
                'project_code' => $row->pcode,
                'comment' => $row->comment, 
                'client_id' => $row->client, 
                'amount' => $row->amount . " " . $row->currency,

                ];
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



}