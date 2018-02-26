<?php

namespace Drupal\ek_sales;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Interface for sales data
 * @DocumentHtml : return an html format url for document
 */
 class SalesData {
 
 
   /**
   * Constructs a AddressBookData
   *
   * 
   */
  public function __construct() {
    
  }  
 
   /**
   * return an url to html document display  
   * @param source invoice, purchase, quotation
   * @param id document id
   * @param option array
   * @return markup
   */ 
  public static function DocumentHtml($source = NULL, $id = NULL, $option = NULL) {
  
      switch($source) {
          case 'invoice':
              $link =  Url::fromRoute('ek_sales.invoices.print_html', array('id' => $id))->toString();
              break;
          case 'purchase':
              $link =  Url::fromRoute('ek_sales.purchases.print_html', array('id' => $id))->toString();
              break;
          case 'quotation':
              $link =  Url::fromRoute('ek_sales.quotations.print_html', array('id' => $id))->toString();
              break;
      }
     
      $target = '';
      if(isset($option['target']) && $option['target'] = 'blank') {
          $target = '_blank';
      }
      $markup = "<a target = '$target' title='" . $option['title'] . "' href='". $link ."'>" . $option['name'] . "</a>";
      if(isset($option['string'])) {
          $markup .= " " . $option['string'];
      }
    
        return ['#markup' => $markup];
  
  }
  
  
 }

