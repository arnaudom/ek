<?php

namespace Drupal\ek_projects;

use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;
use Drupal\ek_admin\Access\AccessCheck;


/**
 * data interface  
 *
 * 
 *  
 */
 class ProjectData {
 
 
    public function __construct() {
    
    }
    /*
     * @return 
     *  an array of projects by user access - country / company 
     *  classified projects per staus and return array $key => $description
     * 
     * where $key = project code
     * $descritopn = extended description parameters (ie code, name, status)
     */

    public static function listprojects($archive = '%') { 
  
     $access = AccessCheck::GetCompanyByUser();
     $company = implode(',',$access);

     $access = AccessCheck::GetCountryByUser();
     $country = implode(',',$access);
     
     $query="SELECT id, type from {ek_project_type} order by type";
     $result = Database::getConnection('external_db', 'external_db')->query($query);
     $optgrouptype =array();
     $optgrouptype['-']= array('n/a' => t('not applicable') );
      
          $query2="SELECT DISTINCT pcode, cid, pname, status,date from {ek_project} 
                    where 
                    category=:cat 
                    and (status=:stat1 or status=:stat2) 
                    AND (FIND_IN_SET (cid, :c ))
                    AND archive like :a
                    ORDER by status,date";
          
      foreach ($result as $r) {

          $a = array(
            ':cat' => $r->id,
            ':stat1' => 'open',
            ':stat2' => 'awarded',
            ':c'=> $country,
            ':a' => $archive,
          );
          $option1 = array();
          $key = $r->type . ' | ' . t('OPEN & AWARDED');
          $optgrouptype[$key]=[];
          $result2 = Database::getConnection('external_db', 'external_db')->query($query2,$a);      
          
              foreach ($result2 as $r2)  { 
                $pname=substr($r2->pname,0,75).'...';
                $pcode_parts = explode("-" , $r2->pcode);
                $pcode = array_reverse($pcode_parts);
                if(!isset($pcode[4])) $pcode[4] = '-';
                $option1[$r2->pcode] = $pcode[0] ." | ". $r2->status ." | "
                        . $pcode[4] . "-" .$pcode[3] . '-' . $pcode[2] ."-" 
                        . $pcode[1] . " | ".  $pname ;
              }
              
              $optgrouptype[$key]= $option1;

          $a = array(
            ':cat' => $r->id,
            ':stat1' => 'completed',
            ':stat2' => 'closed',
            ':c'=> $country,
            ':a' => $archive,
          );
          $option2 = array();
          $key = $r->type . ' | ' . t('COMPLETED & CLOSED');
          $optgrouptype[$key]=[];
          $result3 = Database::getConnection('external_db', 'external_db')->query($query2,$a);      
          
              foreach ($result3 as $r3)  { 
                $pname=substr($r3->pname,0,75).'...';
                $pcode_parts = explode("-" , $r3->pcode);
                $pcode = array_reverse($pcode_parts); 
                if(!isset($pcode[4])) $pcode[4] = '-';
                $option2[$r3->pcode] = $pcode[0] ." | ". $r2->status ." | "
                        . $pcode[4] . "-" .$pcode[3] . '-' . $pcode[2] ."-" 
                        . $pcode[1] . " | ".  $pname ;
              }
              
              $optgrouptype[$key] = $option2;
   
      }
   
   return $optgrouptype;
    }

    
    /*
     * @return 
     *  array of formated project list for select field 
     *  pcode => description
     * @param
     *  param = array of project code
     * 
     */

    public static function format_project_list($param) {

        $list = array();
        foreach ($param as $key => $code) {

            $query2 = "SELECT pname, status FROM {ek_project} WHERE pcode =:p";
            $a = array(':p' => $code);
            $p = Database::getConnection('external_db', 'external_db')->query($query2, $a)->fetchObject();
            $pname = substr($p->pname, 0, 75) . '...';
            $pcode_parts = explode("-", $code);
            $pcode = array_reverse($pcode_parts);
            $string = $pcode[0] . " | " . $p->status ;
            $string .= isset($pcode[4]) ? " | " . $pcode[4] : '';
            $string .= isset($pcode[3]) ? " - " . $pcode[3] : '';
            $string .= isset($pcode[2]) ? " - " . $pcode[2] : '';
            $string .= isset($pcode[1]) ? " - " . $pcode[1] : '';
            $string .= " | " . $pname;
            $list[$code] = $string;
        }

        return $list;
    }
    /*
     * @param mix id 
     *  project id or project serial code
     * @param bolean ext
     *  flag to open link in new window
     * @param bolean short
     *  return only last part of serial code (number)
     * @return html link
     *  a project code view url from id or serial
     *  generate internal/external link to the project
     * 
     */

    public static function geturl($id, $ext = NULL, $base = NULL, $short = NULL) { 
        
        if(is_numeric($id)){
            $query = "SELECT id,pcode,pname from {ek_project} where id=:id";
        } else {
            $query = "SELECT id,pcode,pname from {ek_project} where pcode=:id";
        }
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $id))
                ->fetchObject();
        
        if($ext == TRUE) {
           $link =  Url::fromRoute('ek_projects_view', array('id' => $p->id), ['absolute' => TRUE])->toString();
        } else {
           $link =  Url::fromRoute('ek_projects_view', array('id' => $p->id), [])->toString(); 
        }
        
        if($base != NULL) {
            $link = $GLOBALS['base_url'] . $link;
        }

        if($short != NULL) {
            $r->pcode = str_replace('/', '-', $r->pcode);//old format
            $parts = explode('-', $p->pcode);
            $code = array_reverse($parts);
            $pcode = $code[0];
        } else {
            $pcode = $p->pcode;
        }
        return  "<a title='" . $p->pname . "' href='". $link ."'>" . $pcode . "</a>";   
    
    }
    
    /*
     * @return 
     *  a project name from id 
     *  
     */

    public static function getname($id) { 
 
    $query = "SELECT pname from {ek_project} where id=:id";
    $name = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();
    
    return  $name;   
    
    }

    /*
     * @return 
     *  access validation to a project by uid 
     * @param
     *  id = project id, $uid = user id provided if not current user to be checked
     */

    public static function validate_access($id , $uid = NULL) { 
 
    if($uid == NULL) {
    $uid =  \Drupal::currentUser()->id();
    }

    $query = "SELECT cid,share,deny FROM {ek_project} WHERE id=:id";
    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

    $query = "SELECT access FROM {ek_country} WHERE id=:id";
    $access = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->cid))->fetchField();
    $access = explode(',', unserialize($access));
    
    
      if($data->share == '0') {
      //no special restriction.
      //check access by country
        
        if(in_array($uid, $access)) {
          return TRUE;
        } else {
          return FALSE;
        }
      
      } else {
      //restricted
      //use share / deny data    
      $share = explode(',', $data->share);
      $deny = explode(',', $data->deny);
        if(in_array($uid, $share) && !in_array($uid, $deny)) {
          return TRUE;
        } else {
          return FALSE;
        }      
      
      }

    
    }


    /*
     * @return 
     *  access validation to a file in a project by uid
     * @param 
     *  id = file id
     *
     */

    public static function validate_file_access($id) { 
 

    $query = "SELECT cid,d.share,d.deny,owner FROM {ek_project_documents} d INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE d.id=:f";
    $data = Database::getConnection('external_db', 'external_db')->query($query, array(':f' => $id))->fetchObject();

    $query = "SELECT access FROM {ek_country} WHERE id=:id";
    $access = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->cid))->fetchField();
    $access = explode(',', unserialize($access));
    
    $uid = \Drupal::currentUser()->id();
    
      if($data->share == '0') {
      //no special restriction.
      //check access by country  or owner     
        if(in_array($uid , $access) || $uid == $data->owner) {
          return TRUE;
        } else {
          return FALSE;
        }
      
      } else {
      //restricted
      //use share / deny data    
      $share = explode(',', $data->share);
      $deny = explode(',', $data->deny);
        if(in_array($uid, $share) && !in_array($uid, $deny)) {
          return TRUE;
        } else {
          return FALSE;
        }      
      }
     
    } 


    /*
     * @return 
     *  access to section by user
     *  return an array of accessible sections i.e (1,2,5) => access to section 1, 2 and 5
     * @param
     *  uid = user id
     * 
     */

    public static function validate_section_access($uid) { 
    
    $query = 'SELECT * from {ek_project_users} wHERE uid=:u';
    $access = Database::getConnection('external_db', 'external_db')->query($query, array(':u' => $uid))->fetchobject();
    
      $sections = array();
      if ($access->section_1 == 1) array_push($sections, 1);
      if ($access->section_2 == 1) array_push($sections, 2);
      if ($access->section_3 == 1) array_push($sections, 3);
      if ($access->section_4 == 1) array_push($sections, 4);
      if ($access->section_5 == 1) array_push($sections, 5);
    
    return $sections;
    }

    /*
     * @return 
     *  array (uid , owner) of a file attached in a project
     * @param
     *  id = file id
     *
     */
    public static function file_owner($id) { 
    /*
    $query = "SELECT uid from {file_managed} WHERE id =:f";
    $owner = db_query($query, array(':f' => $id))->fetchField();
    $name = db_query('SELECT name from {users_field_data} WHERE uid=:u', array(':u' => $owner))->fetchField();
    
    return array($owner, $name);
    */
    }
    
    /*
     * @return
     *  email notification about changes in project
     * @param
     *  param = serialize data
     *  id = project id, field = field edited, value = new value, 
     */
    public static function notify_user($param) { 
    
    $param = unserialize($param);
    if(!isset($param['mail']) || $param['mail'] == NULL ) {
        $param['mail'] = 'nomail';
    }
    $error = '';
      if($param['field'] != 'new_project') {
          //send to users following project
          // note user still in project will be filtered out if non active
        $query = "SELECT notify from {ek_project} WHERE id=:id";
        $p = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $param['id']))->fetchObject();
        if($p->notify != '0') {
            $notify = explode(',', $p->notify);
        }
      } elseif($param['field'] == 'new_project') {
          //send to all users in country
          $access = AccessCheck::GetCountryAccess($param['cid']);
          $notify = $access[$param['cid']];
      }
      
      if(!empty($notify)) {
                
        $currentuserid = \Drupal::currentUser()->id();
        $query = "SELECT mail,name from {users_field_data} WHERE uid=:u OR mail=:m";
        $from = Database::getConnection('default', 'default')
                ->query($query, array(':u' => $currentuserid, ':m' => $param['mail']))->fetchObject(); 
        $body = [];
        $params['options']['url'] = self::geturl($param['id'],0,1);
        switch($param['field']) {

            case 'invoice_payment':
                $body[] = t('Payment received for project ref. @p', ['@p' => $param['pcode']]) ;
                $body[] = t('Invoice : @v' , array('@v' => $param['value']));
                $params['subject'] = t("Project invoicing update");
                break;
            case 'purchase_payment':
                $body[] = t('Purchase paid for project ref. @p', ['@p' => $param['pcode']]) ;
                $body[] = t('Purchase : @v' , array('@v' => $param['value']));
                $params['subject'] = t("Project purchase update");
                break;
            case 'new_project':
                $body[] = t('New project created with ref @r' , array('@r' => $param['pcode']));
                $body[] = t('Name : @v' , array('@v' => $param['pname']));
                $body[] = t('By : @v' , array('@v' => $from->name));
                $params['subject'] = t("New project in @c", array('@c' => $param['country'])) . '</p>';
                break;                
            default:
                $body[] =   t('Data edited for project ref. @p', ['@p' => $param['pcode']]) ;
                $body[] =  t('Field : @f' , array('@f' => str_replace('_', ' ' ,$param['field']))) ;
                if($param['value']) {
                    $body[] =  t('Value : @v' , array('@v' => $param['value'])) ;
                }
                    $body[] =  t('By : @b' , array('@b' => $from->name)) ;

                $params['subject'] = t("Project Edited");
                break;
        
        }
        
        $params['body'] = $body;
        
        foreach (User::loadMultiple($notify) as $account) {
            if($account->isActive()) {
                
                $send = \Drupal::service('plugin.manager.mail')->mail(
                  'ek_projects',
                  'project_note',
                  $account->getEmail(),
                  $account->getPreferredLangcode(),
                  $params,
                  $from->mail,
                  TRUE
                );
              
                if($send['result'] == FALSE) {
                  $error .= $account->getEmail() . ' ';
                }
                
            }
        }
        
        return TRUE;
        
      } //if>0 
   
    return $error;
    }    
 
 }//class
