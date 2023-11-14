<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Plugin\Block\ExpensesTableBlock.
 */

namespace Drupal\ek_finance\Plugin\Block;

use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\FinanceSettings;

/**
 * Provides an expenses summary table block
 *
 * @Block(
 *   id = "expenses_table_block",
 *   admin_label = @Translation("Expenses per category table block"),
 *   category = @Translation("Ek finance block")
 * )
 */
class ExpensesTableBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function build() {
        $items = [];
        $items['content'] = '';
        $items['title'] = t('Expenses per category table');
        $items['id'] = 'expensescategories-table';
        $settings = new FinanceSettings();
        //$chart = $settings->get('chart');

        //$access = AccessCheck::GetCompanyByUser();
        //$company = implode(',', $access);

        $y = date('Y');
        $coids = AccessCheck::GetCompanyByUser();
        $companies = AccessCheck::CompanyListByUid();
        $companies[0] = $settings->get('baseCurrency');
        $data = [];
        $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 't');
        $query->fields('t', ['class']);
        $condition = $query->orConditionGroup()
            ->condition('class', $chart['expenses'] . '%' ,'LIKE')
            ->condition('class', $chart['other_expenses'] . '%' ,'LIKE');
        $query->condition($condition);
        $query->distinct();
        $classes = $query->execute()->fetchCol();
        
        foreach ($classes as $key => $c) {
            $line = [];
            foreach ($coids as $coid) {
                if($coid > 0) {
                    $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 'e');    
                    $query->addExpression('SUM(amount)', 'sumAmount');
                    $query->condition('pdate', $y . '%','LIKE');
                    $query->condition('company', $coid, '=');
                    $query->condition('class', $c, '=');
                    
                    $Obj = $query->execute();
                    $v = $Obj->fetchObject()->sumAmount; 
                    $line[$coid] = round($v, 2);
                    $co = $coid;
                }
            } //foreach coid

            $query = "SELECT aname from {ek_accounts} WHERE atype=:t "
                    . "AND aid like :id AND coid = :c";
            $a = array(
                ':t' => 'class',
                ':id' => $c . '%',
                ':c' => $co,
            );
            $line['classe'] = Database::getConnection('external_db', 'external_db')
                            ->query($query, $a)->fetchField();
            $line['classe_'] = $line['classe'];
            if (strlen($line['classe']) > 10) {
                $line['classe'] = substr($line['classe'], 0, 10) . '...';
            }
            array_push($data, $line);
            
        } // for each classe
       
        $items['content'] .= "<table><tr><th>'000 " .$companies[0]. "<br/>" .  $y . "</th>";
        for ($i = 1; $i <= count($companies); $i++) {
            $items['content'] .= "<th>" . $companies[$i] . "</th>";
        }
        foreach ($data as $key => $arr) {
            $items['content'] .= "<tr>";
            $items['content'] .= "<td title='" . $arr['classe_']. "'>" . $arr['classe'] . "</td>";
            for ($i = 1; $i <= count($companies); $i++) {
                $items['content'] .= "<td>" . round($arr[$i]/1000) . "</td>";
            }
            $items['content'] .= "</tr>";
        }
        $items['content'] .= "</table>";

        return array(
            '#items' => $items,
            '#theme' => 'ek_finance_dashboard',
            '#attached' => array(
                'library' => array('ek_finance/ek_finance.dashboard',)
            ),
            '#cache' => [
               'tags' => ['reporting'],
               'max-age' => 86400,
            ],
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account) {
        if (!$account->isAnonymous() && $account->hasPermission('manage_expense')) {
            return AccessResult::allowed();
        }
        return AccessResult::forbidden();
    }

}
