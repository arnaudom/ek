<?php

namespace Drupal\ek_projects\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ProjectService.
 */
class ProjectService implements ProjectServiceInterface {


  protected $extdb;
  protected $logger;
  protected $configFactory;


  /**
   * Constructs a PromptService object.
   */
    public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
        $this->configFactory = $config_factory;
        $this->extdb = Database::getConnection('external_db', 'external_db');
        $this->logger = $logger_factory->get('ek_projects');
    }


    /**
     * {@inheritdoc}
     */
    public function geturl($id, $abs = null, $dest = null, $short = null, $text = null, $param = null, $fragment = null) {
        $query = $this->extdb->select('ek_project', 'p');
        $query->fields('p', ['id', 'pcode', 'pname']);

        if (is_numeric($id)) {
            $query->condition('id', $id);
        } else {
            $query->condition('pcode', $id);
        }
        $p = $query->execute()->fetchObject();

        if ($p) {
            // create a driect link
            $link = Url::fromRoute('ek_projects_view', ['id' => $p->id], ['absolute' => $abs, 'query' => $param, 'fragment' => $fragment])->toString();

            if ($dest == true) {
                // create a link for external notification, i.e. email with user login and page as destination
                $link = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $link]])->toString();
            }

            if ($short != null) {
                $p->pcode = str_replace('/', '-', $p->pcode); //old format
                $parts = explode('-', $p->pcode);
                $code = array_reverse($parts);
                $pcode = $code[0];
            } elseif ($text != null) {
                $pcode = $text;
            } else {
                $pcode = $p->pcode;
            }
            $pname = html_entity_decode($p->pname);
            return "<a title='" . $pname . "' href='" . $link . "'>" . $pcode . "</a>";
        } else {
            return "";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function file_owner($uri) {
        $file = Database::getConnection()
                    ->select('file_managed', 'f')
                    ->fields('f')
                    ->condition('uri', $uri)
                    ->execute()->fetchObject();
        return $file;
    }

    /**
     * {@inheritdoc}
     */
    public function format_project_list($param) {
        $list = array();
        foreach ($param as $key => $code) {
            $query = $this->extdb->select('ek_project', 'p');
            $query->fields('p', ['id', 'status', 'pname']);
            $query->condition('pcode', $code);
            $p = $query->execute()->fetchObject();
            if ($p) {
                $pname = substr($p->pname, 0, 75) . '...';
                $pcode_parts = explode("-", $code);
                $pcode = array_reverse($pcode_parts);
                $string = $pcode[0] . " | " . $p->status;
                $string .= isset($pcode[4]) ? " | " . $pcode[4] : '';
                $string .= isset($pcode[3]) ? " - " . $pcode[3] : '';
                $string .= isset($pcode[2]) ? " - " . $pcode[2] : '';
                $string .= isset($pcode[1]) ? " - " . $pcode[1] : '';
                $string .= " | " . $pname;
                $list[$code] = $string;
            }
        }

        return $list;
    }
    
    /**
     * {@inheritdoc}
     */
    public function listprojects($archive = '%') {
        $access = AccessCheck::GetCompanyByUser();
        $company = implode(',', $access);

        $access = AccessCheck::GetCountryByUser();
        $country = implode(',', $access);

        $query = $this->extdb->select('ek_project_type', 't');
        $query->fields('t', ['id', 'type']);
        $query->orderBy('type');
        $result = $query->execute();
        $optgrouptype = [];
        $optgrouptype['-'] = ['n/a' => t('not applicable')];

        $query2 = "SELECT DISTINCT pcode, cid, pname, status,date from {ek_project} 
                    where 
                    category=:cat 
                    and (status=:stat1 or status=:stat2) 
                    AND (FIND_IN_SET (cid, :c ))
                    AND archive like :a
                    ORDER by status,date";

        while ($r = $result->fetchObject()) {
            $a =[
                ':cat' => $r->id,
                ':stat1' => 'open',
                ':stat2' => 'awarded',
                ':c' => $country,
                ':a' => $archive,
            ];
            $option1 = [];
            $key = $r->type . ' | ' . t('OPEN & AWARDED');
            $optgrouptype[$key] = [];
            $result2 = $this->extdb->query($query2, $a);

            foreach ($result2 as $r2) {
                $pname = substr($r2->pname, 0, 75) . '...';
                $pcode_parts = explode("-", $r2->pcode);
                $pcode = array_reverse($pcode_parts);
                if (!isset($pcode[4])) {
                    $pcode[4] = '-';
                }
                $option1[$r2->pcode] = $pcode[0] . " | " . $r2->status . " | "
                        . $pcode[4] . "-" . $pcode[3] . '-' . $pcode[2] . "-"
                        . $pcode[1] . " | " . $pname;
            }

            $optgrouptype[$key] = $option1;

            $a = [
                ':cat' => $r->id,
                ':stat1' => 'completed',
                ':stat2' => 'closed',
                ':c' => $country,
                ':a' => $archive,
            ];
            $option2 = [];
            $key = $r->type . ' | ' . t('COMPLETED & CLOSED');
            $optgrouptype[$key] = [];
            $result3 = $this->extdb->query($query2, $a);

            foreach ($result3 as $r3) {
                $pname = substr($r3->pname, 0, 75) . '...';
                $pcode_parts = explode("-", $r3->pcode);
                $pcode = array_reverse($pcode_parts);
                if (!isset($pcode[4])) {
                    $pcode[4] = '-';
                }
                $option2[$r3->pcode] = $pcode[0] . " | " . $r3->status . " | "
                        . $pcode[4] . "-" . $pcode[3] . '-' . $pcode[2] . "-"
                        . $pcode[1] . " | " . $pname;
            }

            $optgrouptype[$key] = $option2;
        }

        return $optgrouptype;
    }

    /**
     * {@inheritdoc}
     */
    public function validate_access($id, $uid = null) {
        if ($uid == null) {
            $uid = \Drupal::currentUser()->id();
        }

        $query = $this->extdb->select('ek_project', 'p');
        $query->fields('p', ['cid', 'share', 'deny']);
        $query->condition('id', $id);
        $data = $query->execute()->fetchObject();

        $query = $this->extdb->select('ek_country', 'c');
        $query->fields('c', ['access']);
        $query->condition('id', $data->cid);
        $r = $query->execute()->fetchField();
        $access = $r !== null ? explode(',', unserialize($r)) : [];

        if ($data->share == '0') {
            // no special restriction.
            // check access by country
            if (in_array($uid, $access)) {
                return true;
            } else {
                return false;
            }
        } else {
            // restricted
            // use share / deny data
            $share = explode(',', (string) ($data->share ?? ''));
            $deny = explode(',', (string) ($data->deny ?? ''));
            if (in_array($uid, $share) && !in_array($uid, $deny)) {
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * {@inheritdoc}
     */
    public function validate_file_access($id, $uid = null) {
        
        if ($uid == null) {
            $uid = \Drupal::currentUser()->id();
        }

        $query = $this->extdb->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $s = $settings !== null ? unserialize($settings) : [];

        $query = $this->extdb->select('ek_project_documents', 'd');
        $query->fields('d', ['share','deny']);
        $query->leftJoin('ek_project', 'p', 'p.pcode=d.pcode');
        $query->fields('p', ['id','cid']);
        $query->condition('d.id', $id);
        $data = $query->execute()->fetchObject();

        // if settings are set to block all at page level, and page is blocked, return False
        
        if (isset($s['access_level']) && $s['access_level'] == 1 && !self::validate_access($data->id, $uid)) {
            return false;
        }

        $query = $this->extdb->select('ek_country', 'c');
        $query->fields('c', ['access']);
        $query->condition('id', $data->cid);
        $a = $query->execute()->fetchField();
        $access = $a !== null ? explode(',', unserialize($a)) : [];

        if ($data->share == '0') {
            // no special restriction.
            // check access by country  or owner
            if (in_array($uid, $access) || $uid == $data->owner) {
                return true;
            } else {
                return false;
            }
        } else {
            // restricted
            // use share / deny data
            $share = explode(',', (string) ($data->share ?? '')); 
            $deny = explode(',', (string) ($data->deny ?? '')); 
            if (in_array($uid, $share) && !in_array($uid, $deny)) {
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * {@inheritdoc}
     */
    public function validate_section_access($uid) {
        
        $query = $this->extdb->select('ek_project_users', 'p');
        $query->fields('p');
        $query->condition('uid', $uid);
        $access = $query->execute()->fetchObject();

        $sections = [];
        if ($access) {
            if ($access->section_1 == 1) {
                array_push($sections, 1);
            }
            if ($access->section_2 == 1) {
                array_push($sections, 2);
            }
            if ($access->section_3 == 1) {
                array_push($sections, 3);
            }
            if ($access->section_4 == 1) {
                array_push($sections, 4);
            }
            if ($access->section_5 == 1) {
                array_push($sections, 5);
            }
        }

        return $sections;
    }


    /**
     * {@inheritdoc}
     */
    public function sectionsName() {
        $query = $this->extdb->select('ek_project_settings', 'p');
        $query->fields('p', ['settings']);
        $query->condition('coid', 0);
        $settings = $query->execute()->fetchField();
        $s = unserialize($settings);
        
        if (isset($s['sections'])) {
            return [
                $s['sections']['s1'],
                $s['sections']['s2'],
                $s['sections']['s3'],
                $s['sections']['s4'],
                $s['sections']['s5'],
            ];
        } else {
            return [
                t("Section 1"),
                t("Section 2"),
                t("Section 3"),
                t("Section 4"),
                t("Section 5"),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function data_fill($id) {
        
        $fields = ['priority','submission','deadline','start_date','validation','completion','project_description','project_comment','product','supplier_offer',
        'current_offer','perso_1','repo_1','task_1','perso_2','repo_2','task_2','payment_terms','purchase_value','discount_offer','paymentdate_d','paymentdate_i','project_amount',
        'lc_status','tender_offer','down_payment','offer_delivery','offer_validity','currency','payment','ship_status'];

        // Initialize the query
        $query = $this->extdb->select('ek_project', 'p');
        $query->fields('p');
        // Add LEFT JOINs
        $query->leftJoin('ek_project_description', 'd', 'p.pcode = d.pcode');
        $query->fields('d');
        $query->leftJoin('ek_project_shipment', 's', 'p.pcode = s.pcode');
        $query->fields('s');
        $query->leftJoin('ek_project_finance', 'f', 'p.pcode = f.pcode');
        $query->fields('f');

        // Add condition for pcode
        $query->condition('p.id', $id);

        // Initialize total counts
        $total_null_count = 0;
        $total_non_null_count = 0;
        
        $results = $query->execute()->fetchAssoc();
        foreach ($results as $key => $data) {
            if (in_array($key, $fields) && $data == "") {
                $total_null_count++;
            }
        }
        // Calculate the ratio
        return round(((count($fields) - $total_null_count)/count($fields))*100);

    }

    /**
     * {@inheritdoc}
     */
    public function followers($id) {
        $query = $this->extdb->select('ek_project', 'p');
            $query->fields('p', ['notify']);
            $query->condition('id', $id, '=');
            $data = $query->execute();
            $notify = explode(',', $data->fetchField());
            $list = '';
                        
            foreach ($notify as $value) {
                $account = \Drupal\user\Entity\User::load($value);
                if ($account) {
                    $avatar = ($account->get('user_picture')->entity) ? $account->get('user_picture')->entity->getFileUri(): null;
                    if($avatar) {
                        $list .= "<div><a href='/user/".$value."'><img src='". \Drupal::service('file_url_generator')->generateAbsoluteString($avatar) ."' class='avatar'  title='".$account->getDisplayName() ."'></a></div>";
                    } else {
                        $avatar = \Drupal::service('file_url_generator')->generateAbsoluteString(\Drupal::service('extension.path.resolver')->getPath('module','ek_admin') 
                        . "/art/avatar/default.jpeg");
                        $list .= "<div><a href='/user/".$value."'><img src='". $avatar ."' class='avatar' title='".$account->getDisplayName() ."'></a></div>";
                    }
                    
                }
            }

            return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function notify_user($param) {
        
        $param = unserialize($param);
        if (!isset($param['mail']) || $param['mail'] == null) {
            $param['mail'] = 'nomail';
        }
        $data = [];
        if ($param['field'] != 'new_project') {
            // send to users following project
            // note user still in project will be filtered out if non active
           $notify = $this->extdb
            ->select('ek_project')
            ->fields('ek_project', ['notify'])
            ->condition('id', $param['id'])
            ->execute()
            ->fetchField();

            if ($notify != '0') {
                $notify = explode(',', $notify);
            }
           
        } elseif ($param['field'] == 'new_project') {
            //send to all users in country
            $access = AccessCheck::GetCountryAccess($param['cid']);
            $notify = $access[$param['cid']];
        }

        if (!empty($notify)) {
            $currentuserid = \Drupal::currentUser()->id();
            $query = Database::getConnection()
            ->select('users_field_data')
            ->fields('users_field_data', ['mail', 'name']);
            $or = $query->orConditionGroup()
            ->condition('uid', $currentuserid)
            ->condition('mail', $param['mail']);
            $query->condition($or);
            $from = $query->execute()->fetchObject();

            $params = [];
            $link = Url::fromRoute('ek_projects_view', ['id' => $param['id']])->toString();
            $params['options']['url'] = Url::fromRoute('user.login', [], ['absolute' => true, 'query' => ['destination' => $link]])->toString();
            $params['options']['base_url'] = \Drupal::request()->getSchemeAndHttpHost();
            $params['pcode'] = $param['pcode'];
            $params['priority'] = 1;
            
            switch ($param['field']) {
                case 'invoice_payment':
                    $text = "<p>" . t('Payment received for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Invoice : @v', ['@v' => $param['value']]) . ".</p>";
                    $params['subject'] = t("Project invoicing update");
                    $subscription = "sales_payment_subscription";
                    break;
                case 'quotation_edit':
                    $text = "<p>" . t('Quotation edited for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Quotation : @v', ['@v' => $param['value']]) . ".</p>";
                    $params['subject'] = t("Project quotation update");
                    $subscription = "edit_sales_doc_subscription";
                    if ($param['input'] && !empty($param['input'])) {
                        $text .= "<p>" . t('Edit : @v', ['@v' => implode(",", str_replace("_", " ", $param['input']))]) . ".</p>";
                        $text .= "<p>" .  t('By : @b', ['@b' => $from->name]) . ".</p>";
                    }
                    break;
                case 'invoice_edit':
                    $text = "<p>" . t('Invoice edited for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Invoice : @v', ['@v' => $param['value']]);
                    $params['subject'] = t("Project invoicing update");
                    $subscription = "edit_sales_doc_subscription";
                    if ($param['input'] && !empty($param['input'])) {
                        $text .= "<p>" . t('Edit : @v', ['@v' => implode(",", str_replace("_", " ", $param['input']))]) . ".</p>";
                        $text .= "<p>" .  t('By : @b', ['@b' => $from->name]) . ".</p>";
                    }
                    break;
                case 'purchase_payment':
                    $text = "<p>" . t('Purchase paid for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Purchase : @v', ['@v' => $param['value']]) . ".</p>";
                    $params['subject'] = t("Project purchase update");
                    $subscription = "sales_payment_subscription";
                    break;
                case 'purchase_edit':
                    $text = "<p>" . t('Purchase edited for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" .  t('Purchase : @v', ['@v' => $param['value']]) . ".</p>";
                    $params['subject'] = t("Project purchase update");
                    $subscription = "edit_sales_doc_subscription";
                    if ($param['input'] && !empty($param['input'])) {
                        $text .= "<p>" .  t('Edit : @v', ['@v' => implode(",", str_replace("_", " ", $param['input']))]) . ".</p>";
                        $text .= "<p>" .  t('By : @b', ['@b' => $from->name]) . ".</p>";
                    }
                    break;
                case 'new_project':
                    $text = "<p>" . t('New project created with ref @r', ['@r' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Name : @v', ['@v' => $param['pname']]) . ".</p>";
                    $text .= "<p>" . t('By : @v', ['@v' => $from->name]) . ".</p>";
                    $params['subject'] = t("New project in @c", ['@c' => $param['country']]);
                    $subscription = "new_project_subscription";                    
                    break;
                default:
                    $text = "<p>" . t('Data edited for project ref. @p', ['@p' => $param['pcode']]) . ".</p>";
                    $text .= "<p>" . t('Field : @f', ['@f' => str_replace('_', ' ', $param['field'])]) . ".</p>";
                    $subscription = "edit_project_subscription";
                    if ($param['value']) {
                        $text .= "<p>" .  t('Value : @v', ['@v' => $param['value']]) . ".</p>";
                    }
                    $text .= "<p>" . t('By : @b', ['@b' => $from->name]) . ".</p>";
                    $params['subject'] = t("Project Edited");
                    break;
            }
            $params['body'] = $text;
            if (empty($params['from'])) {
                $params['from'] = \Drupal::config('system.site')->get('mail_notification');
            }
            $queue = \Drupal::queue('ek_email_queue');
            $queue->createQueue();
            $data['module'] = 'ek_projects';
            $data['key'] = 'project_note';
            $data['params'] = $params;
            $userData = \Drupal::service('user.data');
            foreach (User::loadMultiple($notify) as $account) {
                if ($account->isActive()) {
                    // insert option per user
                    if($userData->get('ek_alert_subscriptions', $account->id(), $subscription) == 1){
                        // send notification email to central email queue;
                        $data['email'] = $account->getEmail();
                        $data['lang'] = $account->getPreferredLangcode();
                        if($param['field'] == "new_project") {
                            // force follow route
                            $data['params']['options']['url'] = Url::fromRoute('ek_projects_follow', [], ['absolute' => true, 'query' => ['pid' => $param['id'], 'u' => $data['email']]])->toString();
                        }
                        $queue->createItem($data);
                    }
                }
            }

            return new Response('', 204);
        } 

        return new Response('', 204);
    }

    /**
     * {@inheritdoc}
     */
    public function status($pcode, $status = null) {
        $query = $this->extdb->select('ek_project', 'p')
                ->fields('p', ['status'])
                ->condition('pcode', $pcode);
        $current_status = $query->execute()->fetchField();

        if($current_status ) {
            if (!null == $status && $status != $current_status && $current_status == 'open') {
                if(in_array($status, ['open', 'awarded', 'completed', 'closed'])) {
                    $query = $this->extdb->update('ek_project')
                    ->fields(['status'=> $status])
                    ->condition('pcode', $pcode)
                    ->execute();
                    return (string) $status;
                } else {
                    return (string) $current_status;
                }
                
            } else {
                return (string) $current_status;
            }
        } 
        
        return null;

    }


    /**
     * {@inheritdoc}
     */
    public function getId($pcode) {
        $query = $this->extdb->select('ek_project', 'p');        
        $data = $query
              ->fields('p', ['id'])
              ->condition('p.pcode', $pcode , '=')
              ->execute()
              ->fetchField();

        return ($data) ? $data : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getProjects(array $options = []) {

        // Set default options
        $options += [
            'include_archived' => FALSE,
            'status_filter' => NULL,
            'owner_id' => NULL,
            'country' => NULL,
            'project_code' => NULL,
            'since' => NULL
        ];

        try {
            // Build the query
            $query = $this->extdb->select('ek_project', 'p')
                ->fields('p', ['id','pcode', 'status', 'pname', 'owner', 'last_modified']);

            // priority filter for data with project code
            if($options['project_code']) {
                $query->condition('p.pcode', $options['project_code'], '=');
            } else {
                // filter multi
                if ($options['since']) {
                    $since = is_numeric($options['since']) ? date('Y-m-d', $options['since']) : date('Y-m-d', strtotime($options['since']));
                    $query->condition('p.date', $since, '>=');
                }

                if ($options['owner_id'] && is_numeric($options['owner_id']) && $options['owner_id'] > 0) {
                    $query->condition('p.owner', $options['owner_id'], '=');
                }
            
                if (!$options['include_archived']) {
                    $query->condition('p.archive', 0, '=');
                }

                if ($options['status_filter']) {
                    $query->condition('p.status', $options['status_filter'], '=');
                }
                if ($options['country']) {
                    $query->condition('p.status', $options['country'], '=');
                }
            }

            // Order by project code
            $query->orderBy('p.pcode', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $projects = [];
            foreach ($results as $row) {
                $fill = self::data_fill($row->id);
                $stamp = explode("|", $row->last_modified)[0];
                $projects[] = [
                    'id' => (int) $row->id,
                    'project_code' => $row->pcode,
                    'status' => $row->status,
                    'project_name' => $row->pname,
                    'data fill' => $fill . '%',
                    'owner' => $row->owner,
                    'last_modified' => date('Y-m-d H:i:s', $stamp)
                ];
            }

            return $projects;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving projects data: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve project data', 0, $e);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function getProjectDescription($project_code) {
        try {
            $query = $this->extdb->select('ek_project_description', 'p')
                ->fields('p', ['pcode', 'project_description', 'project_comment', 'submission', 'deadline', 'start_date', 'validation', 'validation', 'completion', 'current_offer'])
                ->condition('p.pcode', $project_code, '=');
           
            $results = $query->execute()->fetchAll();
            
            $projects = [];
            foreach ($results as $row) {
                $projects[] = [
                    'project_description' => $row->project_description,
                    'project_comment' => $row->project_comment,
                    'submission_date' => $row->submission,
                    'completion_target' => $row->deadline,
                    'start_date' => $row->start_date,
                    'validation_date' => $row->validation,
                    'completion_date' => $row->completion,
                    'offer_reference' => $row->current_offer,
                ];
            }

            return $projects;
        
        } catch (\Exception $e) {
            $this->logger->error('Error getting project info: @message', [
                '@message' => $e->getMessage(),
            ]);
            return ['data' => null];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectDocument($project_code) {
        try {
            $query = $this->extdb->select('ek_project_documents', 'p')
                ->fields('p', ['id','pcode', 'filename', 'folder', 'sub_folder', 'comment', 'date'])
                ->condition('p.pcode', $project_code, '=');
            
            $results = $query->execute()->fetchAll();
            $fold = ['fi' => 'finance', 'com' => 'info'];
            
            $projects = [];
            foreach ($results as $row) {
                $projects[] = [
                    'file_id'=> $row->id,
                    'file_name' => $row->filename,
                    'folder' => $fold[$row->folder],
                    'tag' => $row->sub_folder,
                    'file_comment' => $row->comment,
                    'upload_date' => date('Y-m-d H:i:s', $row->date),
                ];
            }

            return $projects;
        
        } catch (\Exception $e) {
            $this->logger->error('Error getting project documents: @message', [
                '@message' => $e->getMessage(),
            ]);
            return ['data' => null];
        }

    }

    /**
     * {@inheritdoc}
     */
    public function getProjectFinance($project_code) {
        
        try {
            $query = $this->extdb->select('ek_project_finance', 'p')
                ->fields('p')
                ->condition('p.pcode', $project_code, '=');
            
            $results = $query->execute()->fetchAll();
            $fold = ['fi' => 'finance', 'com' => 'info'];
            
            $projects = [];
            foreach ($results as $row) {
                $projects[] = [
                    'currency' => $row->currency,
                    'payment_terms' => $row->payment_terms,
                    'purchase_value' => $row->purchase_value,
                    'discount_offer' => $row->discount_offer,
                    'project_amount' => $row->project_amount,
                    'offer_made' => $row->tender_offer,
                    'offer_validity_date' => $row->offer_validity,
                    'offer_deadline_date' => $row->offer_delivery,
                    'lc_expiry_date' => $row->lc_expiry,
                    'down_payment' => $row->down_payment,
                    'comment' => $row->comment,
                ];
            }

            return $projects;
        
        } catch (\Exception $e) {
            $this->logger->error('Error getting project documents: @message', [
                '@message' => $e->getMessage(),
            ]);
            return ['data' => null];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function downloadProjectDocument($document_id) {
        try {
            $query = $this->extdb->select('ek_project_documents', 'd')
                ->fields('d', ['id', 'pcode', 'filename', 'uri', 'fid'])
                ->condition('d.id', $document_id);
            $doc = $query->execute()->fetchObject();

            if (!$doc) {
                return ['success' => FALSE, 'error' => 'Document not found'];
            }

            if ($doc->fid == '0') {
                return ['success' => FALSE, 'error' => 'File has been deleted'];
            }

            if (!$this->validate_file_access($document_id)) {
                return ['success' => FALSE, 'error' => 'User access denied to this document'];
            }

            $realpath = \Drupal::service('file_system')->realpath($doc->uri);
            if (!$realpath || !file_exists($realpath)) {
                return ['success' => FALSE, 'error' => 'File not found on disk'];
            }

            return [
                'success' => TRUE,
                'file' => [
                    'uri' => $doc->uri,
                    'realpath' => $realpath,
                    'filename' => $doc->filename,
                    'pcode' => $doc->pcode,
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error downloading project document: @message', [
                '@message' => $e->getMessage(),
            ]);
            return ['success' => FALSE, 'error' => 'Internal error retrieving document'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function uploadProjectDocument($project_code, $file_data, $folder, $sub_folder = null, $comment = null) {
        try {
            // Validate project exists
            $query = $this->extdb->select('ek_project', 'p')
                ->fields('p', ['id', 'pcode'])
                ->condition('p.pcode', $project_code);
            $project = $query->execute()->fetchObject();

            if (!$project) {
                return ['success' => FALSE, 'errors' => ['project_code' => 'Project not found']];
            }

            // Validate folder type
            $allowed_folders = ['fi', 'com'];
            if (!in_array($folder, $allowed_folders)) {
                return ['success' => FALSE, 'errors' => ['folder' => 'Invalid folder type. Use "fi" or "com"']];
            }

            // Validate file data
            if (empty($file_data['tmp_name']) || !file_exists($file_data['tmp_name'])) {
                return ['success' => FALSE, 'errors' => ['file' => 'No valid file provided']];
            }

            // Validate sub_folder if provided
            if (!empty($sub_folder) && !preg_match('/^[a-zA-Z0-9 _-]+$/', $sub_folder)) {
                return ['success' => FALSE, 'errors' => ['sub_folder' => 'Sub-folder contains invalid characters']];
            }

            // Build destination directory
            $pcode_parts = explode('-', $project_code);
            $pcode_reversed = array_reverse($pcode_parts);
            $dir = $pcode_reversed[0];
            $destination = "private://projects/documents/{$dir}";

            /** @var \Drupal\Core\File\FileSystemInterface $file_system */
            $file_system = \Drupal::service('file_system');
            $file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

            // Move uploaded file to destination
            $filename = $file_data['name'];
            $destination_uri = $destination . '/' . $filename;

            // Handle duplicate filenames
            $counter = 0;
            $base_name = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            while (file_exists($file_system->realpath($destination_uri))) {
                $counter++;
                $filename = $base_name . '_' . $counter . '.' . $extension;
                $destination_uri = $destination . '/' . $filename;
            }

            $uri = $file_system->copy($file_data['tmp_name'], $destination_uri, FileSystemInterface::EXISTS_REPLACE);

            if (!$uri) {
                return ['success' => FALSE, 'errors' => ['file' => 'Failed to save file']];
            }

            // Create file managed entry
            $file = File::create([
                'uri' => $uri,
                'uid' => \Drupal::currentUser()->id(),
                'filename' => $filename,
                'filesize' => filesize($file_data['tmp_name']),
                'filemime' => $file_data['type'] ?? mime_content_type($file_data['tmp_name']),
                'status' => FileInterface::STATUS_PERMANENT,
            ]);
            $file->save();

            /** @var \Drupal\file\FileUsage\FileUsageInterface $file_usage */
            $file_usage = \Drupal::service('file.usage');
            $file_usage->add($file, 'ek_projects', 'project_document', $file->id());

            // Insert record into ek_project_documents
            $fields = [
                'pcode' => $project_code,
                'filename' => $filename,
                'uri' => $uri,
                'folder' => $folder,
                'sub_folder' => $sub_folder,
                'comment' => $comment,
                'date' => time(),
                'size' => $file->getSize(),
            ];

            $document_id = $this->extdb->insert('ek_project_documents')
                ->fields($fields)
                ->execute();

            // Log the action
            $log = $project_code . '|' . \Drupal::currentUser()->id() . '|upload|' . $filename;
            $this->logger->notice($log);

            // Track the action
            $this->extdb->insert('ek_project_tracker')
                ->fields([
                    'pcode' => $project_code,
                    'uid' => \Drupal::currentUser()->id(),
                    'stamp' => time(),
                    'action' => 'upload ' . $filename,
                ])
                ->execute();

            // Notify followers
            $param = serialize([
                'id' => $project->id,
                'field' => 'File attachment',
                'value' => $filename,
                'pcode' => $project_code,
            ]);
            $this->notify_user($param);

            return [
                'success' => TRUE,
                'document_id' => (int) $document_id,
                'filename' => $filename,
                'message' => 'Document uploaded successfully',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error uploading project document: @message', [
                '@message' => $e->getMessage(),
            ]);
            return ['success' => FALSE, 'errors' => ['file' => 'Internal error uploading document']];
        }
    }

}
