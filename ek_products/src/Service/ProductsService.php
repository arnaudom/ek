<?php

namespace Drupal\ek_products\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProductsService.
 */
class ProductsService implements ProductsServiceInterface {

  /**
   * External DB connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $extdb;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs ProductsService.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->extdb = Database::getConnection('external_db', 'external_db');
    $this->logger = $logger_factory->get('ek_products');
  }

  /**
   * Creates the service from container.
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(array $options = []): array {
    $options += [
      'id' => NULL,
      'itemcode' => NULL,
      'supplier_code' => NULL,
      'company_id' => NULL, // maps to coid
      'active' => NULL,
      'include' => [],
      'limit' => 100,
      'offset' => 0,
    ];

    $allowed_includes = ['barcodes', 'images', 'packing', 'prices'];
    $include = array_values(array_intersect($allowed_includes, (array) $options['include']));

    $limit = (int) $options['limit'];
    $offset = (int) $options['offset'];
    if ($limit <= 0) {
      $limit = 100;
    }
    if ($limit > 500) {
      $limit = 500;
    }
    if ($offset < 0) {
      $offset = 0;
    }

    try {
      $query = $this->extdb->select('ek_items', 'i')
        ->fields('i', [
          'id', 'itemcode', 'coid', 'type', 'description1', 'description2',
          'supplier_code', 'active', 'collection', 'department', 'family',
          'size', 'color', 'supplier', 'stamp', 'format', 'specs', 'source_url',
        ]);

      if (!empty($options['id'])) {
        $query->condition('i.id', (int) $options['id'], '=');
      }

      if (!empty($options['itemcode'])) {
        $query->condition('i.itemcode', $options['itemcode'], '=');
      }

      if (!empty($options['supplier_code'])) {
        $query->condition('i.supplier_code', $options['supplier_code'], '=');
      }

      if (!empty($options['company_id'])) {
        $query->condition('i.coid', (string) $options['company_id'], '=');
      }

      if ($options['active'] !== NULL && $options['active'] !== '') {
        $query->condition('i.active', (string) $options['active'], '=');
      }

      $query->orderBy('i.id', 'DESC');
      $query->range($offset, $limit);

      $rows = $query->execute()->fetchAll();

      $items = [];
      $itemcodes = [];
      foreach ($rows as $row) {
        $items[] = [
          'id' => (int) $row->id,
          'itemcode' => $row->itemcode,
          'company_id' => $row->coid,
          'type' => $row->type,
          'description1' => $row->description1,
          'description2' => $row->description2,
          'supplier_code' => $row->supplier_code,
          'active' => $row->active,
          'collection' => $row->collection,
          'department' => $row->department,
          'family' => $row->family,
          'size' => $row->size,
          'color' => $row->color,
          'supplier' => $row->supplier,
          'stamp' => $row->stamp,
          'format' => $row->format,
          'specs' => $row->specs,
          'source_url' => $row->source_url,
        ];
        $itemcodes[] = $row->itemcode;
      }

      if (!empty($items) && !empty($include)) {
        $related = [];

        if (in_array('barcodes', $include, TRUE)) {
          $related['barcodes'] = $this->loadRelated('ek_item_barcodes', ['id', 'itemcode', 'barcode', 'encode'], $itemcodes);
        }
        if (in_array('images', $include, TRUE)) {
          $related['images'] = $this->loadRelated('ek_item_images', ['id', 'itemcode', 'uri'], $itemcodes);
        }
        if (in_array('packing', $include, TRUE)) {
          $related['packing'] = $this->loadRelated('ek_item_packing', ['id', 'itemcode', 'units', 'unit_measure', 'item_size', 'pack_size', 'qty_pack', 'logistic_cost', 'c20', 'c40', 'min_order'], $itemcodes);
        }
        if (in_array('prices', $include, TRUE)) {
          $related['prices'] = $this->loadRelated('ek_item_prices', ['id', 'itemcode', 'purchase_price', 'currency', 'date_purchase', 'selling_price', 'promo_price', 'discount_price', 'exp_selling_price', 'exp_promo_price', 'exp_discount_price', 'loc_currency', 'exp_currency'], $itemcodes);
        }

        foreach ($items as &$item) {
          $code = $item['itemcode'];
          foreach ($include as $section) {
            $item[$section] = $related[$section][$code] ?? [];
          }
        }
        unset($item);
      }

      return $items;
    }
    catch (\Exception $e) {
      $this->logger->error('Error retrieving items: @message', ['@message' => $e->getMessage()]);
      throw new \RuntimeException('Unable to retrieve items data', 0, $e);
    }
  }

  /**
   * Loads related rows grouped by itemcode.
   */
  protected function loadRelated(string $table, array $fields, array $itemcodes): array {
    $map = [];

    if (empty($itemcodes)) {
      return $map;
    }

    $query = $this->extdb->select($table, 't')
      ->fields('t', $fields)
      ->condition('t.itemcode', array_values(array_unique($itemcodes)), 'IN');

    if (in_array('id', $fields, TRUE)) {
      $query->orderBy('t.id', 'DESC');
    }

    $rows = $query->execute()->fetchAll();

    foreach ($rows as $row) {
      $record = [];
      foreach ($fields as $field) {
        $record[$field] = $row->{$field};
      }
      $map[$row->itemcode][] = $record;
    }

    return $map;
  }

}