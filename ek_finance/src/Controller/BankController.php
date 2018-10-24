<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\EkController.
 */

namespace Drupal\ek_finance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\SafeMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Controller routines for ek module routes.
 */
class BankController extends ControllerBase {

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler service
     */
    public function __construct(FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * list of banks references 
     * filtered by company access
     * 
     * @retrun array
     *  renderer Html
     *
     */
    public function banklist(Request $request) {
        $new = Url::fromRoute('ek_finance.manage.bank_manage', array('id' => 0), array())->toString();
        $build["new"] = array(
            '#markup' => "<a href='" . $new . "' >" . t('new') . "</a>",
        );

        $header = array(
            'name' => array(
                'data' => $this->t('Bank name'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'coid' => array(
                'data' => $this->t('Company reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'operations' => $this->t('Operations'),
        );

        $company = AccessCheck::GetCompanyByUser();
        $company = implode(',', $company);
        $query = "SELECT b.id, b.name as bank, c.name as co "
                . "FROM {ek_bank} b "
                . "INNER JOIN {ek_company} c "
                . "ON b.coid=c.id "
                . "WHERE FIND_IN_SET(coid, :c ) ORDER by b.name";
        $list = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $company));
        $row = 0;
        while ($l = $list->fetchObject()) {

            $row++;
            $options[$row] = array(
                'name' => $l->bank,
                'coid' => $l->co,
            );

            $links['edit'] = array(
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('ek_finance.manage.bank_manage', ['id' => $l->id]),
            );
            $param = serialize(['id' => $l->id]);
            $links['label'] = array(
                'title' => $this->t('Print label'),
                'url' => Url::fromRoute('ek_finance.manage.bank_label', (['type' => 6, 'param' => $param])),
                'attributes' => array('target' => '_blank'),
            );

            $options[$row]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }

        $build['bank_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'bank_table'),
            '#empty' => $this->t('No bank reference found.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance'),
            ),
            '#cache' => [
                'tags' => ['bank_list'],
            ],
        );

        return $build;
    }

    /**
     * print a label  
     * 
     * @param array
     *  key: type (output type)
     *  key: param (array 'id' => value)
     * 
     * @return Object
     *  Fpdf render object
     */
    public function banklabel($type, $param) {

        //generate bank contact card with type 6
        $markup = array();
        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     *  output statement per bank account
     * 
     * @param int $id
     *  account id
     * 
     * @return array
     *  render Html
     *
     */
    public function statement($id = NULL) {


        $access = AccessCheck::GetCompanyByUser();
        $query = "SELECT coid,account_ref, ba.currency, c.name FROM {ek_bank_accounts} ba "
                . "INNER JOIN {ek_bank} b "
                . "ON ba.bid=b.id "
                . "INNER JOIN {ek_company} c "
                . "ON b.coid=c.id "
                . "WHERE ba.id=:id";
        $acc = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();

        if (in_array($acc->coid, $access)) {
            $build['bank'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\FilterStatement', $id);

            if (isset($_SESSION['statfilter']) && $_SESSION['statfilter']['filter'] == 1) {
                $list = Url::fromRoute('ek_finance.manage.bank_accounts_list', array(), array())->toString() ;
                $build["list"] = array(
                    '#markup' => "<div><a href='" . $list . "' >" . t('list') . "</a>",
                    '#suffix' => '</div>'
                );
                $build['company'] = ['#markup' => '<b>' . $acc->name . '</b><br/>'];
                $build['account'] = ['#markup' => $acc->account_ref . ' ' . $acc->currency . '<br/>'];
                $build['year'] = ['#markup' => $_SESSION['statfilter']['year'] . '<br/>'];

                $query = "SELECT * FROM {ek_bank_transactions} "
                        . "WHERE year_transaction = :y "
                        . "AND account_ref =:r "
                        . "AND currency =:c ORDER by date_transaction";

                $data = Database::getConnection('external_db', 'external_db')
                        ->query($query, array(':y' => $_SESSION['statfilter']['year'], ':r' => $acc->account_ref, ':c' => $acc->currency));

                $header = array(
                    'id' => array(
                        'data' => $this->t('Id'),
                        'class' => array(RESPONSIVE_PRIORITY_LOW),
                    ),
                    'date_transaction' => array(
                        'data' => $this->t('Date'),
                        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    ),
                    'amount' => array(
                        'data' => $this->t('Amount'),
                        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                    ),
                    'description' => array(
                        'data' => $this->t('Description'),
                        'class' => array(RESPONSIVE_PRIORITY_LOW),
                    ),
                );

                $row = 0;
                while ($d = $data->fetchObject()) {

                    $row++;
                    if ($d->type == 'credit') {
                        $amount = '(' . number_format($d->amount, 2) . ')';
                    } else {
                        $amount = number_format($d->amount, 2);
                    }
                    $options[$row] = array(
                        'id' => $row . '-' . $d->id,
                        'date_transaction' => $d->date_transaction,
                        'amount' => $amount,
                        'description' => $d->description
                    );
                }
                $build['statement_table'] = array(
                    '#type' => 'table',
                    '#header' => $header,
                    '#rows' => $options,
                    '#attributes' => array('id' => 'statement_table'),
                    '#empty' => $this->t('No transaction recorded.'),
                    '#attached' => array(
                        'library' => array('ek_finance/ek_finance_css'),
                    ),
                );
            }
        } else {
            $build['bank'] = ['#markup' => t('You cannot view this account statement.')];
        }
        return $build;
    }

    /**
     *  create or edit bank 
     *  linked to company - coid
     * 
     *  @param int $id
     *      bank id
     *  @return array
     *      form
     */
    public function bank(Request $request, $id) {

        $build['bank'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\BankForm', $id);
        return $build;
    }

    /**
     *  delete bank 
     *  linked to company - coid
     * 
     *  @param int $id
     *      bank id
     *  @return array
     *      form
     */
    public function deletebank(Request $request, $id) {
        //ToDo
        return array('#markup' => t('Under construction'));
    }

    /**
     * list of bank accounts references  
     * filtered by company access
     * 
     * @retrun array
     *  renderer Html
     */
    public function bankaccountslist(Request $request) {
        
        unset($_SESSION['statfilter']);
        $new = Url::fromRoute('ek_finance.manage.bank_accounts_manage', array('id' => 0), array())->toString();
        $build["new"] = array(
            '#markup' => "<a href='" . $new . "' >" . t('new') . "</a>",
        );

        $build['filter'] = $this->formBuilder->getForm('Drupal\ek_admin\Form\FilterCompany');

        $header = array(
            'account_ref' => array(
                'data' => $this->t('Account reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'bid' => array(
                'data' => $this->t('Bank reference'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
            ),
            'currency' => array(
                'data' => $this->t('Currency'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'status' => array(
                'data' => $this->t('Status'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'aid' => array(
                'data' => $this->t('Accounting'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
            ),
            'operations' => $this->t('Operations'),
        );

        $company = AccessCheck::GetCompanyByUser();
        $company = implode(',', $company);
        $query = "SELECT a.id, a.account_ref, a.active,currency,aid, b.name as bank, c.name as co
            FROM {ek_bank_accounts} a
            LEFT JOIN {ek_bank} b
            ON a.bid = b.id
            LEFT JOIN {ek_company} c ON b.coid = c.id WHERE FIND_IN_SET(coid, :c )  ORDER by a.id";
        
        
        //$list = Database::getConnection('external_db', 'external_db')->query($query, array(':c' => $company));
        
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank_accounts', 'ba');
            $query->fields('ba', ['id','account_ref','active','currency','aid']);
            $query->leftJoin('ek_bank', 'b', 'ba.bid = b.id');
            $query->addField('b', 'name', 'bank');
            $query->leftJoin('ek_company', 'c', 'c.id = b.coid');
            $query->addField('c', 'name', 'co');
            if(isset($_SESSION['coidfilter']['coid'])) {
                $query->condition('coid', $_SESSION['coidfilter']['coid'], '=');
            } else {
                $query->condition('coid', $company, 'IN');  
            }
            $list = $query->execute();
        
        $row = 0;
        $status = ['0' => t('inactive'), '1' => t('active')];
        while ($l = $list->fetchObject()) {

            $row++;
            $options[$row] = array(
                'account_ref' => $l->account_ref,
                'bid' => $l->bank . ' - ' . $l->co,
                'currency' => $l->currency,
                'status' => $status[$l->active],
                'aid' => $l->aid
            );

            $links['edit'] = array(
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('ek_finance.manage.bank_accounts_manage', ['id' => $l->id]),
            );
            $links['statement'] = array(
                'title' => $this->t('Statement'),
                'url' => Url::fromRoute('ek_finance.manage.bank_statement', ['id' => $l->id]),
            );
            $param = serialize(['id' => $l->id]);
            $links['label'] = array(
                'title' => $this->t('Print label'),
                'url' => Url::fromRoute('ek_finance.manage.bank_accounts_label', (['type' => 7, 'param' => $param])),
                'attributes' => array('target' => '_blank'),
            );

            $options[$row]['operations']['data'] = array(
                '#type' => 'operations',
                '#links' => $links,
            );
        }

        $build['bank_table'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'bank_table'),
            '#empty' => $this->t('No bank account reference found.'),
            '#attached' => array(
                'library' => array('ek_finance/ek_finance_css'),
            ),
            '#cache' => [
                'tags' => ['bank_account_list'],
            ],
        );

        return $build;
    }

    /**
     * bank accounts label  
     * @param array
     *  key: type (output type)
     *  key: param (array 'id' => value)
     * 
     * @return Object
     *  Fpdf render object
     */
    public function bankaccountslabel($type, $param) {

        //generate bank account card with type 7
        $markup = array();
        include_once drupal_get_path('module', 'ek_finance') . '/pdf.inc';
        return $markup;
    }

    /**
     *  reate or edit bank accounts
     *  linked to bank id
     * 
     *  @param int $id
     *      account id
     *  @return array
     *      form
     */
    public function bankaccount(Request $request, $id) {

        $build['bank'] = $this->formBuilder->getForm('Drupal\ek_finance\Form\BankAccountForm', $id);
        return $build;
    }

    /**
     *  delete bank account
     * 
     *  @param int $id
     *      account id
     *  @return array
     *      form
     */
    public function deletebankaccount(Request $request, $id) {
        //ToDo
        return array('#markup' => t('Under construction'));
    }

}

//class