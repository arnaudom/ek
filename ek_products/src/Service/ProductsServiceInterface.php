<?php

namespace Drupal\ek_products\Service;

/**
 * Interface for ProductsService.
 */
interface ProductsServiceInterface {

  /**
   * Get items by filters.
   *
   * Supported filters:
   * - id
   * - itemcode
   * - supplier_code
   * - company_id (maps to coid)
   * - active
   * - include: barcodes,images,packing,prices
   * - limit, offset
   *
   * @param array $options
   *   Query options.
   *
   * @return array
   *   Item data.
   */
  public function getItems(array $options = []): array;

}