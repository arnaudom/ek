<?php

namespace Drupal\ek_projects;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Url;
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
        $company = implode(',', $access);

        $access = AccessCheck::GetCountryByUser();
        $country = implode(',', $access);

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_type', 't');
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
            $result2 = Database::getConnection('external_db', 'external_db')->query($query2, $a);

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
            $result3 = Database::getConnection('external_db', 'external_db')->query($query2, $a);

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
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 'p');
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

    /*
     * @param mix $id
     *  project id or project serial code
     * @param bolean $abs
     *  true|false absolute url 
     * @param bolean $dest
     *  true|false create link as destination
     * @param bolean $short
     *  true|false return only last part of serial code (number)
     * @param string $text
     *  custom text for link
     * @param array $param
     *  query string option
     * @param array $fragment
     *  fragment string option
     * @return html link or empty string
     *  a project code view url from id or serial
     *  generate internal/external link to the project
     *
     */

    public static function geturl($id, $abs = null, $dest = null, $short = null, $text = null, $param = null, $fragment = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project', 'p');
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

    /*
     * @return
     *  a project name from id
     *
     */

    public static function getname($id) {
        $query = "SELECT pname from {ek_project} where id=:id";
        $name = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchField();

        return $name;
    }

    /*
     * @return
     *  access validation to a project by uid
     * @param int  $id project id,
     * @param int $uid  user id provided if not current user to be checked
     */

    public static function validate_access($id, $uid = null) {
        if ($uid == null) {
            $uid = \Drupal::currentUser()->id();
        }

        $query = "SELECT cid,share,deny FROM {ek_project} WHERE id=:id";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

        $query = "SELECT access FROM {ek_country} WHERE id=:id";
        $access = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->cid))->fetchField();
        $access = explode(',', unserialize($access));


        if ($data->share == '0') {
            //no special restriction.
            //check access by country

            if (in_array($uid, $access)) {
                return true;
            } else {
                return false;
            }
        } else {
            //restricted
            //use share / deny data
            $share = explode(',', $data->share);
            $deny = explode(',', $data->deny);
            if (in_array($uid, $share) && !in_array($uid, $deny)) {
                return true;
            } else {
                return false;
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
        $query = "SELECT settings from {ek_project_settings} WHERE coid=:c";
        $settings = Database::getConnection('external_db', 'external_db')
                        ->query($query, [':c' => 0])->fetchField();
        $s = unserialize($settings);

        $query = "SELECT p.id,cid,d.share,d.deny,owner FROM {ek_project_documents} d "
                . "INNER JOIN {ek_project} p ON d.pcode=p.pcode WHERE d.id=:f";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':f' => $id))
                ->fetchObject();

        //if settings are set to block all at page level, and page is blocked, return False
        
        if (isset($s['access_level']) && $s['access_level'] == 1 && !self::validate_access($data->id)) {
            return false;
        }


        $query = "SELECT access FROM {ek_country} WHERE id=:id";
        $access = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $data->cid))
                ->fetchField();
        $access = explode(',', unserialize($access));

        $uid = \Drupal::currentUser()->id();

        if ($data->share == '0') {
            //no special restriction.
            //check access by country  or owner
            if (in_array($uid, $access) || $uid == $data->owner) {
                return true;
            } else {
                return false;
            }
        } else {
            //restricted
            //use share / deny data
            $share = explode(',', $data->share);
            $deny = explode(',', $data->deny);
            if (in_array($uid, $share) && !in_array($uid, $deny)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /*
     * @return
     *  array of custom sections names
     *
     */

    public static function sectionsName() {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_project_settings', 'p');
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

        $sections = [];
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

        return $sections;
    }

    /*
     * Get file managed data
     * @param uri = file uri
     * @return object query
     *
     */

    public static function file_owner($uri) {
        $file = Database::getConnection()
                    ->select('file_managed', 'f')
                    ->fields('f')
                    ->condition('uri', $uri)
                    ->execute()->fetchObject();
        return $file;
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
        if (!isset($param['mail']) || $param['mail'] == null) {
            $param['mail'] = 'nomail';
        }
        $data = [];
        if ($param['field'] != 'new_project') {
            //send to users following project
            // note user still in project will be filtered out if non active
            $query = "SELECT notify from {ek_project} WHERE id=:id";
            $p = Database::getConnection('external_db', 'external_db')
                            ->query($query, [':id' => $param['id']])->fetchObject();
            if ($p->notify != '0') {
                $notify = explode(',', $p->notify);
            }
        } elseif ($param['field'] == 'new_project') {
            //send to all users in country
            $access = AccessCheck::GetCountryAccess($param['cid']);
            $notify = $access[$param['cid']];
        }

        if (!empty($notify)) {
            $currentuserid = \Drupal::currentUser()->id();
            $query = "SELECT mail,name from {users_field_data} WHERE uid=:u OR mail=:m";
            $from = Database::getConnection('default', 'default')
                            ->query($query, [':u' => $currentuserid, ':m' => $param['mail']])->fetchObject();
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

}
