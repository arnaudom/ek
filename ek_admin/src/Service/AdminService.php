<?php

namespace Drupal\ek_admin\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ek_admin\Access\AccessCheck;

/**
 * Class AdminService.
 */
class AdminService implements AdminServiceInterface {


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
    public function getCompany($id) {

        $company = filter_var($id, FILTER_VALIDATE_INT) ? $id: null;
        $query = $this->extdb
            ->select('ek_company', 'c');
        $query->fields('c');
        
        if($id != null) {
            $query->condition('id', $company);
        } 
                        
        $result = $query->execute();
        $data = [];

        while($r = $result->fetchObject()) {
            $data[] = [
                'id' => $r->id, 
                'name' => trim($r->name),
                'country' => trim($r->country)];
        }

        return $data;

    }

    /**
     * {@inheritdoc}
     */
    public function getUser($uid = null) {

        $core = Database::getConnection();
        $user = filter_var($uid, FILTER_VALIDATE_INT) ? (int) $uid : null;

        $query = $core->select('users_field_data', 'u');
        $query->fields('u', ['uid', 'langcode', 'preferred_langcode', 'name', 'mail', 'timezone', 'status']);

        if ($user !== null) {
            $query->condition('uid', $user);
        }

        $result = $query->execute();
        $users = [];

        while ($r = $result->fetchObject()) {
            $users[(int) $r->uid] = [
                'uid' => (int) $r->uid,
                'name' => trim((string) $r->name),
                'mail' => (string) $r->mail,
                'status' => (int) $r->status,
                'langcode' => (string) $r->langcode,
                'preferred_langcode' => (string) $r->preferred_langcode,
                'timezone' => (string) $r->timezone,
                'roles' => [],
                'company_access' => [],
                'country_access' => [],
            ];
        }

        if (!$users) {
            return [];
        }

        $uids = array_keys($users);

        $roles_query = $core->select('user__roles', 'r');
        $roles_query->fields('r', ['entity_id', 'roles_target_id']);
        $roles_query->condition('entity_id', $uids, 'IN');
        $roles = $roles_query->execute();

        while ($role = $roles->fetchObject()) {
            $rid = (int) $role->entity_id;
            if (isset($users[$rid])) {
                $users[$rid]['roles'][] = (string) $role->roles_target_id;
            }
        }

        foreach ($users as $id => &$item) {
            $company_list = AccessCheck::CompanyListByUid($id);
            $country_list = AccessCheck::CountryListByUid($id);

            $item['company_access'] = [];
            foreach ($company_list as $cid => $name) {
                $item['company_access'][] = [
                    'id' => (int) $cid,
                    'name' => trim((string) $name),
                ];
            }

            $item['country_access'] = [];
            foreach ($country_list as $cid => $name) {
                $item['country_access'][] = [
                    'id' => (int) $cid,
                    'name' => trim((string) $name),
                ];
            }
        }

        return array_values($users);
    }
}