<?php

namespace Drupal\ek_finance;

use Drupal\Core\Database\Database;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\Entity\User;
use Drupal\ek_admin\CompanySettings;
use Drupal\ek_finance\FinanceSettings;


class PrintManager {

    /**
    * The configuration object.
    */
    protected $extdb;

    /**
    * Constructs a PromptService object.
    */
    public function __construct() {
        $this->extdb = Database::getConnection('external_db', 'external_db');
    }

    /*
    * Print document in Pdf format
    *  @param array
    *   source: int, 
    *       1-expensevoucher, 2-cashvoucher, 3-reconciliationreport,
    *       4-PL, 5-BS, 6-banklabel, 7-bankaccount
    *   id: database id
    *   parameters: array 
    *   m
    */
    public function makePdf($param) {
        
        $source = $param[0];
        $id = $param[1];
        $parameters = isset($param[2]) ? $param[2] : '';

        Switch($source) {
            case 'expensevoucher':
                if(is_numeric($id)) {
                    $selection = [$id];
                    $fileName = t('voucher') . '_' . $id;
                } else {
                    $selection = unserialize($id);
                    $fileName = t('voucher') . '_' . t('range');
                }
                $data = [];    
                $settings = new FinanceSettings(); 
                $baseCurrency = $settings->get('baseCurrency'); 
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/default_voucher_pdf";
                $userName =  \Drupal::currentUser()->getAccountName();
            
                foreach ($selection as $key => $id) {
                    $query = $this->extdb->select('ek_expenses', 'e')
                                ->fields('e')
                                ->condition('id', $id)
                                ->execute();
                    $data[$id]['line'] = $query->fetchObject();
                    if(!null == $data[$id]['line']->attachment) {
                        $data[$id]['line']->attachment_realpath = \Drupal::service('file_system')->realpath($data[$id]['line']->attachment);
                        $data[$id]['line']->attachment_absolute = \Drupal::service('file_url_generator')->generateAbsoluteString($data[$id]['line']->attachment);
                        $data[$id]['line']->attachment_image = false;
                        
                        if ($data[$id]['line']->attachment_realpath && file_exists($data[$id]['line']->attachment_realpath)) {
                            // @TODO getimagesize always return false (s3//)
                            $image_info = @getimagesize($data[$id]['line']->attachment_realpath);
                            if ($image_info !== false) {
                                $data[$id]['line']->attachment_image = $image_info;
                            }
                        }
                    }

                    $data[$id]['company'] = $this->getCompanyData($data[$id]['line']->company);
                    $data[$id]['journal'] = $this->getJournalData($id,'expense', 'debit' );
                    $data[$id]['clientname'] = '';
                    if($data[$id]['line']->clientname != 'n/a') {
                        $data[$id]['clientname'] = $this->getClientName($data[$id]['line']->clientname);
                    }
                    $data[$id]['suppliername'] = '';
                    if($data[$id]['line']->suppliername != 'n/a'){
                        $data[$id]['suppliername'] = $this->getClientName($data[$id]['line']->suppliername);
                    }
                    $data[$id]['type'] = $this->getAccountName($data[$id]['line']->type,$data[$id]['line']->company);
                    if($data[$id]['line']->cash <> 'Y') {
                        $data[$id]['bank_account'] = $this->getBankAccData($data[$id]['line']->cash)->account_ref;
                    } else {
                        $data[$id]['bank_account'] = t('cash');
                    }
                }    
    
            break;

            case 'cashvoucher':
                
                $cash = $this->getCashData($id);

                if ($cash->uid == 0) { 
                    $employee = t("company cash account");
                } else {
                    $u = \Drupal\user\Entity\User::load($cash->uid);
                    $employee = '';
                    if($u) {
                        $employee = $u->getAccountName();
                    }
                }

                $company = $this->getCompanyData($cash->coid);
                $settings = new FinanceSettings(); 
                $baseCurrency = $settings->get('baseCurrency'); 
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/default_cash_voucher_pdf";
                $fileName = t('cash_voucher') . '_' . $cash->id;
                $userName =  \Drupal::currentUser()->getAccountName();

            break;

            case 'reconciliationreport':

                $reco = $this->getRecoData($id);
                $aid = $reco->aid;
                $aname = $this->getAccountName($reco->aid, $reco->coid);
                $company = $this->getCompanyData($reco->coid);
                $settings = new FinanceSettings(); 
                $baseCurrency = $settings->get('baseCurrency'); 
                $data = unserialize($reco->data);
                $template = $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/default_reco_report_pdf";
                $fileName = t('Reconciliation_report') . '_' . $reco->id. '_' . $aname. '_' . $reco->date;
                $userName =  \Drupal::currentUser()->getAccountName();

            break;

            case 'pl':
                $params = unserialize($parameters);
                $company = $this->getCompanyData($params['coid']);
                $finance = new FinanceSettings();
                $chart = $finance->get('chart');
                $CompanySettings = new CompanySettings($params['coid']);
                $fiscalYear = $CompanySettings->get('fiscal_year');
                $fiscalMonth = $CompanySettings->get('fiscal_month');
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') .'/templates/profit_and_loss_pdf';
                $fileName = t('Profit_and_loss') . '_' . $params['year'] . '_' . $params['month'];
                $userName =  \Drupal::currentUser()->getAccountName();   

            break;

            case 'bs':
                $params = unserialize($parameters);
                $company = $this->getCompanyData($params['coid']);
                $finance = new FinanceSettings();
                $chart = $finance->get('chart');
                $CompanySettings = new CompanySettings($params['coid']);
                $fiscalYear = $CompanySettings->get('fiscal_year');
                $fiscalMonth = $CompanySettings->get('fiscal_month');
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') .'/templates/balance_sheet_pdf';
                $fileName = t('Balance_sheet') . '_' . $params['year'] . '_' . $params['month'];
                $userName =  \Drupal::currentUser()->getAccountName();   

            break;

            case 'banklabel':
                $params = unserialize($parameters);
                $bank = $this->getBankData($params['id']);
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') .'/templates/bank_label_pdf';
                $fileName = $bank->name;
                $userName =  \Drupal::currentUser()->getAccountName();

            break;

            case 'bankaccount':
                $params = unserialize($parameters);
                $a = array(':id' => $params['id']);
                $query = $this->extdb->select('ek_bank_accounts', 'a');
                    $query->fields('a', ['account_ref', 'currency', 'beneficiary']);
                $query->leftJoin('ek_bank', 'b', 'b.id = a.bid');
                $query->fields('b', ['name', 'address1', 'address2', 'postcode','swift','country','bank_code']);
                $query->leftJoin('ek_company', 'c', 'c.id = b.coid');
                $query->addField('c', 'name', 'company');
                $query->condition('a.id', $params['id']);
                $bank = $query->execute()->fetchObject();
    
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') .'/templates/bank_account_label_pdf';
                $fileName = $bank->name;
                $userName =  \Drupal::currentUser()->getAccountName();
            break;

            case 'expensememo':
                $parameters = unserialize($parameters);
                $id = $parameters[1];
                $source = $parameters[2]; // expenses_memo
                $signature = $parameters[3];
                $stamp = $parameters[4];
                $tpl = (isset($parameters[5]) && $parameters[5] == 0) ? "default_expenses_memo_pdf" : $parameters[5];
                $mode = isset($parameters[6]) ? $parameters[6] : NULL;
                $head = $this->getExpensesMemo($id);

                if ($head->category < 5) {
                    $coid = $head->entity;
                    $title = t('Internal Memo');
                } else {
                    //cat 5 is for personal claim
                    $coid = $head->entity_to;
                    $title = t('Personal Claim');
                }

                $lines = $this->getExpensesMemoList($head->serial,$coid);
                $documents = $this->getExpensesMemoDoc($head->serial );

                if (isset($head->pcode) && $head->pcode != 'n/a') {
                    $head->pcode_raw = $head->pcode;
                    $head->pcode = \Drupal::service('project.service')->geturl($head->pcode);
                } else {
                    $head->pcode = "";
                    $head->pcode_raw = "";
                }

                if ($head->category < 5) {
                    $company = $this->getCompanyData($head->entity);
                } else {
                    $query = Database::getConnection()->select('users_field_data', 'u');
                    $query->fields('u');
                    $query->condition('uid', $head->entity);
                    $company = $query->execute()->fetchObject();
                }

                $auth = explode('|', $head->auth);
                $company_to = $this->getCompanyData($head->entity_to);

                $client = (object) [];
                $client_card = (object) [];
                if ($head->client != '0') {
                    $client = $this->getClientData($head->client);
                    $client_card = $this->getClientCardData($client->id, null);
                }

                $fileName = $head->serial;
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/" . $tpl;
       
            break;

            case 'memorange':

                $parameters = unserialize($parameters);
                $tpl = (isset($parameters[1]) && $parameters[1] == 0) ? "default_memo_range_pdf" : $parameters[1];
                $data = [];
                $f = ['id', 'name','contact', 'reg_number', 'address1', 'address2',
                    'postcode', 'city', 'country', 'country2', 'telephone', 'fax', 'logo'];
                $companies = $this->extdb->select('ek_company', 'c')
                        ->fields('c', $f)
                        ->execute()
                        ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

                $cl = $this->extdb->select('ek_address_book', 'ab');
                $cl->fields('ab', ['id', 'name']);
                $cl->leftJoin('ek_address_book_contacts', 'abc', 'ab.id = abc.abid');
                $cl->fields('abc', ['contact_name']);
                $clients = $cl->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

                if ($_SESSION['memrgfilter']['category'] == 'personal') {
                   
                    $list = $this->getExpenseMemoRange(
                        $_SESSION['memrgfilter']['from'],
                        $_SESSION['memrgfilter']['to'],
                        $_SESSION['memrgfilter']['coid'],
                        $_SESSION['memrgfilter']['coid2'],
                        $_SESSION['memrgfilter']['status'],
                        5, '=');

                    WHILE ($l = $list->fetchObject()) {
                        $query = Database::getConnection()->select('users_field_data', 'u');
                        $query->fields('u');
                        $query->condition('uid', $l->entity);
                        $entity = $query->execute()->fetchAssoc();
                        $entity_to = $companies[$l->entity_to]; //array

                        $data[$l->id]['main'] = [
                            'serial' => $l->serial,
                            'category' => $l->category,
                            'entity' => $entity,
                            'entity_to' => $entity_to,
                            'client' => $clients[$l->client]['name'],
                            'client_card' => $clients[$l->client]['contact_name'],
                            'pcode' => $l->pcode,
                            'mission' => $l->mission,
                            'budget' => $l->budget,
                            'refund' => $l->refund,
                            'invoice' => $l->invoice,
                            'date' => $l->date,
                            'pdate' => $l->pdate,
                            'status' => $l->status,
                            'value' => $l->value,
                            'currency' => $l->currency,
                            'value_base' => $l->value_base,
                            'amount_paid' => $l->amount_paid,
                            'amount_paid_base' => $l->amount_paid_base,
                            'comment' => $l->comment,
                            'auth' => $l->auth,
                        ];
                        
                        $lines = $this->getExpensesMemoList($l->serial, $l->entity);

                        WHILE ($ls = $lines->fetchObject()) {

                            $data[$l->id]['lines'][] = [
                                'aid' => $ls->aid,
                                'aname' => $ls->aname,
                                'description' => $ls->description,
                                'amount' => $ls->amount,
                                'value_base' => $ls->value_base,
                                'receipt' => $ls->receipt,
                            ];
                        }
                    }

                    

                } else {
                    
                    $list = $this->getExpenseMemoRange(
                        $_SESSION['memrgfilter']['from'],
                        $_SESSION['memrgfilter']['to'],
                        $_SESSION['memrgfilter']['coid'],
                        $_SESSION['memrgfilter']['coid2'],
                        $_SESSION['memrgfilter']['status'],
                        5, '<');

                    WHILE ($l = $list->fetchObject()) {

                        $entity_to = $companies[$l->entity_to]; //array
                        $entity = $companies[$l->entity]; //array

                        $data[$l->id]['main'] = [
                            'serial' => $l->serial,
                            'category' => $l->category,
                            'entity' => $entity,
                            'entity_to' => $entity_to,
                            'client' => $clients[$l->client]['name'],
                            'client_card' => $clients[$l->client]['contact_name'],
                            'pcode' => $l->pcode,
                            'mission' => $l->mission,
                            'budget' => $l->budget,
                            'refund' => $l->refund,
                            'invoice' => $l->invoice,
                            'date' => $l->date,
                            'pdate' => $l->pdate,
                            'status' => $l->status,
                            'value' => $l->value,
                            'currency' => $l->currency,
                            'value_base' => $l->value_base,
                            'amount_paid' => $l->amount_paid,
                            'amount_paid_base' => $l->amount_paid_base,
                            'comment' => $l->comment,
                            'auth' => $l->auth,
                        ];

                        $lines = $this->getExpensesMemoList($l->serial, $l->entity);

                        WHILE ($ls = $lines->fetchObject()) {

                            $data[$l->id]['lines'][] = [
                                'aid' => $ls->aid,
                                'aname' => $ls->aname,
                                'description' => $ls->description,
                                'amount' => $ls->amount,
                                'value_base' => $ls->value_base,
                                'receipt' => $ls->receipt,
                            ];
                        }
                    }

                    $fileName = 'Print_memo_range';
                    $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/" . $tpl;
                }

                $stamp =  $_SESSION['memrgfilter']['stamp'];
                $signature =  $_SESSION['memrgfilter']['signature'];
                $fileName = 'Print_memo_range_' . $_SESSION['memrgfilter']['from'] . '-' . $_SESSION['memrgfilter']['to'];
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/" . $tpl;
            
            break;

        }
       
        include_once $template;  
        
        if (headers_sent()) {
            exit('Unable to stream pdf: headers already sent');
        }
    
        header('Cache-Control: private');
        header('Content-Type: application/pdf');
        $f = $fileName . ".pdf";
        
        echo $pdf->Output($f,"I");
    
        exit ;
        
    }
    
    /*
    * Render document in html format
    *  @param array
    *   id: database id
    *   source: string, expenses_memo
    *   signature: bol
    *   stamp: bolean
    *   template: sting
    *   url edit: string
    *   url list: string
    */
    public function renderHtml($param) {
        $parameters = unserialize($param);
        $id = $parameters[0];
        $source = $parameters[1]; // expenses_memo
        $signature = $parameters[2];
        $stamp = $parameters[3];
        $tpl = (isset($parameters[4]) && $parameters[4] == 0) ? "default_expenses_memo_html" : $parameters[4];
        $url_edit = isset($parameters[5]) ? $parameters[5] : NULL;
        $url_list = isset($parameters[6]) ? $parameters[6] : NULL;
        $url_pdf = isset($parameters[7]) ? $parameters[7] : NULL;
        $head = $this->getExpensesMemo($id);

        if ($head->category < 5) {
            $coid = $head->entity;
            $title = t('Internal Memo');
        } else {
            //cat 5 is for personal claim
            $coid = $head->entity_to;
            $title = t('Personal Claim');
        }

        $lines = $this->getExpensesMemoList($head->serial,$coid);
        $attachments = $this->getExpensesMemoDoc($head->serial); 

        if (isset($head->pcode) && $head->pcode != 'n/a') {
            $head->pcode_raw = $head->pcode;
            $head->pcode = \Drupal::service('project.service')->geturl($head->pcode);
        } else {
            $head->pcode = "";
            $head->pcode_raw = "";
        }

        if ($head->category < 5) {
            $company = $this->getCompanyData($head->entity);
        } else {
            $query = Database::getConnection()->select('users_field_data', 'u');
            $query->fields('u');
            $query->condition('uid', $head->entity);
            $company = $query->execute()->fetchObject();
        }

        $auth = explode('|', $head->auth);
        $company_to = $this->getCompanyData($head->entity_to);

        $client = (object) [];
        $client_card = (object) [];
        if ($head->client != '0') {
            $client = $this->getClientData($head->client);
            $client_card = $this->getClientCardData($client->id, null);
        }

        $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_finance') . "/templates/" . $tpl;
        include_once $template; 
        return $document;

    }
    
    /*
    * Download document in excel format
    *  @param array
    *   id: database id
    *   source: string, invoice, purchase, quotation
    *   signature: array [sales, pos]
    *   stamp: bolean
    *   template: sting
    *   contact: int, address book contact id
    *   format: excel, csv
    */   

    public function exportExcel($param) {

        $param = unserialize($param);
        $id = $param[0];
        $source = $param[1];
        $signature = $param[2];
        $stamp = $param[3];
        $template = $param[4];
        $contact = $param[5];
        $mode = (isset($param[6])) ? $param[6] : 1; 
        $items = [];

        $head = $this->getHeaderData($source, $id);
        $items = $this->getItemData($source, $head);
        $company = $this->getCompanyData($head);
        $client = $this->getClientData($head);
        $client_card = $this->getClientCardData($client,$contact); 
        if($source == 'invoice' && $head->bank != 0) {
            // bank ref.
            $bank = $this->getBankData($head);
        } else {
            $bank = null;
        }

        if($mode == '1') {
            if( $template == '0' ) {
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/templates/default_" . $source . "_excel";
            } elseif($template == 'default_receipt_invoice_excel') {
                $template = \Drupal::service('extension.path.resolver')->getPath('module', 'ek_sales') . "/templates/default_receipt_invoice_excel";
            } else {
                // if print template is a custom file, copy from private to public before include
                // use this feature when storage of data is remote.
                $filesystem = \Drupal::service('file_system');
                $path = PublicStream::basePath() . "/" . $template;
                $filesystem->copy("private://sales/templates/". $source . '/' . $template, $path, FileSystemInterface::EXISTS_REPLACE );
                $template = $path ;
            }
            
            $fileName = $head->serial;
            include_once $template; 
            
          } elseif($mode == '2') {
          
                // csv file
                $header_i = "id,serial,do_no,head,allocation,status,amount,currency,date,title,pcode,comment,client,amountreceived,pay_date,class,amountbase,balancebase,terms,due,bank,tax,taxvalue,reconcile,balance_post,alert,alert_who";
                $header_v = "''," 
                      .  $head->serial . ','
                      .  $head->do_no . ','
                      .  $head->head
                      .  $head->allocation . ',' 
                      .  $head->status . ',' 
                      .  $head->amount . ',' 
                      .  $head->currency . ',' 
                      .  $head->date . ',' 
                      .  $head->title . ',' 
                      .  $head->pcode . ',' 
                      .  $head->comment . ',' 
                      .  $head->client . ',' 
                      .  $head->amountreceived . ',' 
                      .  $head->pay_date . ',' 
                      .  $head->class . ',' 
                      .  $head->amountbase . ',' 
                      .  $head->balancebase . ',' 
                      .  $head->terms . ',' 
                      .  $head->due . ',' 
                      .  $head->bank . ',' 
                      .  $head->tax . ',' 
                      .  $head->taxvalue . ',' 
                      .  $head->reconcile . ',' 
                      .  $head->balance_post . ',' 
                      .  $head->alert . ',' 
                      .  $head->alert_who;
              
                $items_i = "id,serial,item,itemdetail,value,margin,quantity,total,totalbase,opt,aid";
                $items_l = '';
                foreach ($items as $detail) {
                  $items_l .= "''," . $detail['serial'] . ',';
                  $items_l .=  $detail['item'] . ',';
                  $items_l .=  $detail['itemdetail'] . ',';
                  $items_l .=  $detail['value'] . ',';
                  $items_l .=  $detail['margin'] . ',';
                  $items_l .=  $detail['quantity'] . ',';
                  $items_l .=  $detail['total'] . ',';
                  $items_l .=  $detail['totalbase'] . ',';
                  $items_l .=  $detail['opt'] . ',';
                  $items_l .=  $detail['aid'] . '\r\n';
                  
                }
              
                $file = $header_i . '\r\n' . $header_v . '\r\n' . $items_i . '\r\n' . $items_l;
                $f = \Drupal::service('file.repository')->writeDataa($file, 'private://tmp/' . $head->serial . '.csv', NULL);
                  if ($f) {
                      $id = \Drupal::currentUser()->id();
                  if ($id) {
                      $user = User::load($id);
                      $f->setOwner($user->getAccountName());
                    } else {
                      $f->setOwner('admin');
                    }
                        // Change the file status to be temporary.
                        $f->setTemporary();
                        // Save the changes.
                        $f->save();
                  }
           
                  $build['csv']=[
                      '#markup' => "<a href='". \Drupal::service('file_url_generator')->generateAbsoluteString($f->getFileUri()) 
                          . "'>" . t('download') . "</a>"
                  ];
        }  
    }
    

    /*
    * Common data queries
    */
    private function getAccountName($aid, $coid) {
        $query = $this->extdb->select('ek_accounts', 't');
        $query->fields('t',['aname']);
        $query->condition('aid',  $aid);
        $query->condition('coid',  $coid);
        $query->execute();
        return $query->execute()->fetchField();
    }
    private function getBankAccData($id) {
        $query = $this->extdb->select('ek_bank_accounts', 'ba');
        $query->fields('ba');
        $query->leftJoin('ek_bank', 'b', 'ba.bid=b.id');
        $query->fields('b');
        $query->condition('ba.id', $id);
        return $query->execute()->fetchObject();
    }
    private function getBankData($id) {
        $query = $this->extdb->select('ek_bank', 't');
        $query->fields('t');
        $query->condition('id', $id);
        $query->execute();
        return $query->execute()->fetchObject(); 
    }
    private function getCashData($id) {
        $query = $this->extdb->select('ek_cash', 't');
        $query->fields('t');
        $query->condition('id', $id);
        return $query->execute()->fetchObject(); 
    }
    private function getClientCardData($client, $contact) {
        $query = $this->extdb->select('ek_address_book_contacts', 't');
        $query->fields('t');
        if($contact != '') {
            // selected contact from list
            $query->condition('id', $contact);
        } else {
            // default contact
            $query->condition('abid', $client);
            $query->condition('main', 1);
        }
        return $query->execute()->fetchObject();
    }
    private function getClientData($id) {
        $query = $this->extdb->select('ek_address_book', 't');
        $query->fields('t');
        $query->condition('id', $id);
        return $query->execute()->fetchObject();
    }
    private function getClientName($id) {
        $query = $this->extdb->select('ek_address_book', 't');
        $query->fields('t', ['name']);
        $query->condition('id', $id);
        return $query->execute()->fetchField();
    }
    private function getCompanyData($id) {
        $query = $this->extdb->select('ek_company', 't');
        $query->fields('t');
        $query->condition('id', $id);
        $result = $query->execute()->fetchObject();
        if($result->logo) {
                    $result->logo_realpath = \Drupal::service('file_system')->realpath($result->logo);
                    $result->logo_absolute = \Drupal::service('file_url_generator')->generateAbsoluteString($result->logo);
        }
        return $result;

    }
    private function getExpensesMemo($id) {
        $query = $this->extdb->select('ek_expenses_memo', 't');
        $query->fields('t');
        $query->condition('id', $id);
        return $query->execute()->fetchObject();                
    }
    private function getExpensesMemoList($serial, $coid) {
        $query = $this->extdb->select('ek_expenses_memo_list', 'm');
        $query->fields('m', ['aid', 'description', 'amount','value_base', 'receipt']);
        $query->leftJoin('ek_accounts', 'a', 'm.aid=a.aid');
        $query->fields('a', ['aname']);
        $query->condition('serial', $serial);
        $query->condition('a.coid', $coid);        // a.coid ? entity_to
        return $query->execute();

    }
    private function getExpenseMemoRange($start,$end,$entity,$entity_to,$status,$category, $s) {
        $query = $this->extdb->select('ek_expenses_memo', 't');
        $query->fields('t');
        $query->condition('date', $start, '>=');
        $query->condition('date', $end, '<=');
        $query->condition('entity', $entity, 'LIKE');
        $query->condition('entity_to', $entity_to, 'LIKE');
        $query->condition('status', $status, 'LIKE');
        $query->condition('category', $category, $s);
        $query->execute();
        return $query->execute();

    }
    private function getExpensesMemoDoc($serial) {
        $query = $this->extdb->select('ek_expenses_memo_documents', 't');
        $query->fields('t');
        $query->condition('serial', $serial);
        return $query->execute();    
    }
    private function getJournalData($id, $source, $type,) {
        $query = $this->extdb->select('ek_journal', 't');
        $query->fields('t');
        $query->condition('reference', $id);
        $query->condition('source', $source);
        $query->condition('type', $type);
        return $query->execute()->fetchObject();
    }
    private function getRecoData($id) {
        $query = $this->extdb->select('ek_journal_reco_history', 't');
        $query->fields(('t'));
        $query->condition('id', $id);
        return $query->execute()->fetchObject();
    }

}