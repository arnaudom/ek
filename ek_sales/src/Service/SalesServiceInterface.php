<?php

namespace Drupal\ek_sales\Service;

/**
 * Interface for SalesService.
 */
interface SalesServiceInterface {

/**
 * Get invoice by request.
 *  company_id|project_code|year|type
 *
 * @param array $options
 *   The query options.
 *
 * @return array
 *   Invoices data.
 */
  public function getInvoice(array $options = []);

/**
 * Get invoice by request.
 *  company_id|project_code|year|type
 *
 * @param array $options
 *   The query options.
 *
 * @return array
 *   Purchases data.
 */
  public function getPurchase(array $options = []);

/**
 * Get quotation by request.
 *  company_id|project_code|year|client_id
 *
 * @param array $options
 *   The query options.
 *
 * @return array
 *   Quotations data.
 */
  public function getQuotation(array $options = []);

}