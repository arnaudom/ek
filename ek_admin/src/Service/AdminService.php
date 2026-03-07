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
}