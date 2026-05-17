<?php

namespace Drupal\ek_sales\Service;

/**
 * Interface for SalesService.
 */
interface SalesServiceInterface {

/**
 * Get invoice by request.
 *  company_id|project_code|year|type|serial
 *
 * @param array $options
 *   The query options. When 'serial' is provided, returns a single invoice
 *   with line items from ek_sales_invoice_details attached as 'items' key.
 *
 * @return array
 *   Invoices data (with optional 'items' when queried by serial).
 */
  public function getInvoice(array $options = []);

/**
 * Get purchase by request.
 *  company_id|project_code|year|type|serial
 *
 * @param array $options
 *   The query options. When 'serial' is provided, returns a single purchase
 *   with line items from ek_sales_purchase_details attached as 'items' key.
 *
 * @return array
 *   Purchases data (with optional 'items' when queried by serial).
 */
  public function getPurchase(array $options = []);

/**
 * Get quotation by request.
 *  company_id|project_code|year|client_id|serial
 *
 * @param array $options
 *   The query options. When 'serial' is provided, returns a single quotation
 *   with line items from ek_sales_quotation_details attached as 'items' key.
 *
 * @return array
 *   Quotations data (with optional 'items' when queried by serial).
 */
  public function getQuotation(array $options = []);

/**
 * Edit a sales document (quotation/invoice/purchase) fields.
 *
 * @param array $data
 *   The data array containing:
 *   - doc_type: (string) 'quotation', 'invoice', or 'purchase'
 *   - doc_id: (int) the document row ID
 *   - head: (int, optional) company header ID
 *   - allocation: (int, optional) allocation entity ID
 *   - client: (int, optional) client/supplier ID from address book
 *   - date: (string, optional) date in Y-m-d format
 *   - pcode: (string, optional) project code reference
 *   - bank_account: (int, optional) bank account ID (invoice only)
 *   - comment: (string, optional) comment text
 *
 * @return array
 *   Result array with keys:
 *   - success: (bool)
 *   - message: (string)
 *   - updated_fields: (array) list of fields that were updated
 *   - serial: (string) document serial number
 */
  public function editDocument(array $data);

}
