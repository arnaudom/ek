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

    /**
     * {@inheritdoc}
     */
    public function getUserAccessMap($uid = null) {

        $core = Database::getConnection();
        $extdb = Database::getConnection('external_db', 'external_db');
        $user = filter_var($uid, FILTER_VALIDATE_INT) ? (int) $uid : null;

        $query = $core->select('users_field_data', 'u');
        $query->fields('u', ['uid', 'name']);
        $query->distinct(TRUE);

        if ($user !== null) {
            $query->condition('uid', $user);
        }

        $result = $query->execute();
        $users = [];

        while ($r = $result->fetchObject()) {
            $uid = (int) $r->uid;
            $users[$uid] = [
                'uid' => $uid,
                'name' => trim((string) $r->name),
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

        $company_query = $extdb->select('ek_company', 'c');
        $company_query->fields('c', ['id', 'name', 'access']);
        $companies = $company_query->execute();

        while ($company = $companies->fetchObject()) {
            $cid = (int) $company->id;
            $name = trim((string) $company->name);
            $access_list = $this->normalizeAccessList($company->access);

            foreach ($access_list as $access_uid) {
                if (isset($users[$access_uid])) {
                    $users[$access_uid]['company_access'][] = [
                        'id' => $cid,
                        'name' => $name,
                    ];
                }
            }
        }

        $country_query = $extdb->select('ek_country', 'c');
        $country_query->fields('c', ['id', 'name', 'access']);
        $countries = $country_query->execute();

        while ($country = $countries->fetchObject()) {
            $cid = (int) $country->id;
            $name = trim((string) $country->name);
            $access_list = $this->normalizeAccessList($country->access);

            foreach ($access_list as $access_uid) {
                if (isset($users[$access_uid])) {
                    $users[$access_uid]['country_access'][] = [
                        'id' => $cid,
                        'name' => $name,
                    ];
                }
            }
        }

        return array_values($users);
    }

    /**
     * Convert a serialized access field into a clean uid list.
     *
     * @param mixed $access
     *   Serialized list/string from ek_company.access or ek_country.access.
     *
     * @return array
     *   Array of unique integer user ids.
     */
    protected function normalizeAccessList($access) {
        if ($access === null || $access === '') {
            return [];
        }

        $decoded = @unserialize($access);
        if ($decoded === FALSE && $access !== 'b:0;') {
            $decoded = $access;
        }

        if (is_array($decoded)) {
            $values = $decoded;
        }
        else {
            $values = explode(',', (string) $decoded);
        }

        $uids = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && is_numeric($value)) {
                $uids[] = (int) $value;
            }
        }

        return array_values(array_unique($uids));
    }
}