<?php

namespace Drupal\ek_sales;

use Drupal\Core\Database\Database;
use Drupal\Core\Url;

/**
 * Interface for sales data
 *
 */
class SalesData
{

    /**
     * Constructs
     *
     *
     */
    public function __construct()
    {
    }

    /**
     * return an url to html document display
     * @param source invoice, purchase, quotation
     * @param id document id
     * @param option array
     * @return markup
     */
    public static function DocumentHtml($source = null, $id = null, $option = null)
    {
        switch ($source) {
            case 'invoice':
                $link = Url::fromRoute('ek_sales.invoices.print_html', array('id' => $id))->toString();
                break;
            case 'purchase':
                $link = Url::fromRoute('ek_sales.purchases.print_html', array('id' => $id))->toString();
                break;
            case 'quotation':
                $link = Url::fromRoute('ek_sales.quotations.print_html', array('id' => $id))->toString();
                break;
        }

        $target = '';
        if (isset($option['target']) && $option['target'] = 'blank') {
            $target = '_blank';
        }
        $markup = "<a target = '$target' title='" . $option['title'] . "' href='" . $link . "'>" . $option['name'] . "</a>";
        if (isset($option['string'])) {
            $markup .= " " . $option['string'];
        }

        return ['#markup' => $markup];
    }

    /**
     * return document status
     * @param source invoice, purchase, quotation
     * @param id document unique id|serial
     * @return int
     */
    public static function status($source = null, $id = null)
    {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_'.$source, 's');
        $query->fields('s', ['status']);
        $or = $query->orConditionGroup();
        $or->condition('id', $id, '=');
        $or->condition('serial', $id, '=');
        $query->condition($or);
        
        return $query->execute()->fetchField();
    }
    
    /**
     * return document data
     * @param source invoice, purchase, quotation
     * @param id document unique id | serial
     * @return array Object
     */
    public static function data($source = null, $id = null)
    {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_sales_'.$source, 's');
        $query->fields('s');
        $or = $query->orConditionGroup();
        $or->condition('id', $id, '=');
        $or->condition('serial', $id, '=');
        $query->condition($or);
        $data = $query->execute()->fetchObject();
        return $data;
    }
}
