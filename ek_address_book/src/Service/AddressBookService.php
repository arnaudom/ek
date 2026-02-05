<?php

namespace Drupal\ek_address_book\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddressBookService.
 */
class AddressBookService implements AddressBookServiceInterface {

    protected $extdb;
    protected $logger;
    protected $configFactory;

  /**
   * Constructs a PromptService object.
   */
    public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
        $this->configFactory = $config_factory;
        $this->logger = $logger_factory->get('ek_address_book');
        $this->extdb = Database::getConnection('external_db', 'external_db');
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('config.factory'),
                $container->get('logger.factory')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMain(array $options = []) {

        // Set default options
        $options += [
            'type' => NULL,
            'activity' => NULL,
            'name' => NULL,
            'id' => NULL
        ];
        $type = [1 => 'client', 2 => 'supplier', 3 => 'other'];

        try {
            // Build the query
            $query = $this->extdb->select('ek_address_book', 'ab')
                ->fields('ab', ['id','name', 'shortname', 'address', 'address2', 'postcode', 'city', 'state', 'country', 'telephone','fax', 'website', 'type', 'category','status', 'activity', 'stamp']);

            if ($options['id']) {
                // search by id has no other condition
                $query->condition('ab.id',$options['id'], '=');
            } elseif($options['name']) {
                $query->condition('ab.name', '%'. $options['name'] . '%', 'LIKE');
            } elseif($options['tag']) {
                $query->condition('ab.activity', '%'. $options['tag'] . '%', 'LIKE');
            } elseif($options['type']) {
                $op = ['client' => 1, 'supplier' => 2, 'other' => 3 ];
                $query->condition('ab.type', $op[$options['type']], '=');
            }
            

            $query->orderBy('ab.id', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $ab = [];
            foreach ($results as $row) {
                $ab[] = [
                'id' => $row->id,
                'name' => $row->name,
                'short_name' => $row->shortname,
                'address_line_1' => $row->address,
                'address_line_2' => $row->address2,
                'postcode' => $row->postcode,
                'state' => $row->state,
                'city' => $row->city,
                'country' => $row->country, 
                'telephone' => $row->telephone, 
                'fax' => $row->fax, 
                'web_site' => $row->website, 
                'type' => $type[$row->type], 
                'tag' => $row->activity, 
                'category' => $row->category, 
                'status' => $row->status, 
                'last_update' => (null != $row->stamp) ? date('Y-m-d', $row->stamp) : '', 

                ];
            }

            return $ab;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving address book: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve address book data', 0, $e);
        }

    }

    /**
     * {@inheritdoc}
     */
    public function getContact(array $options = []) {
        // Set default options
        $options += [
            'abid' => NULL,
            'name' => NULL,
        ];

        try {
            // Build the query
            $query = $this->extdb->select('ek_address_book_contacts', 'abc')
                ->fields('abc', ['id','contact_name', 'salutation', 'title', 'telephone', 'mobilephone', 'email', 'department', 'link', 'comment', 'stamp']);
            $query->leftJoin('ek_address_book', 'ab', 'ab.id = abc.abid');
            $query->fields('ab', ['id', 'name']);
            $query->addExpression('ab.id', 'main_id');
            if ($options['abid']) {
                // search by main abid has no other condition
                $query->condition('abc.abid',$options['abid'], '=');
            } elseif($options['name']) {
                $query->condition('abc.contact_name', '%'. $options['name'] . '%', 'LIKE');
            } 

            $query->orderBy('abc.id', 'ASC');

            // Execute query
            $results = $query->execute()->fetchAll();

            // Format results
            $ab = [];
            foreach ($results as $row) {
                $ab[] = [
                'id' => $row->id,
                'name' => $row->contact_name,
                'salutation' => $row->salutation,
                'title' => $row->title,
                'telephone' => $row->telephone,
                'mobilephone' => $row->mobilephone,
                'email' => $row->email,
                'social_media' => $row->link,
                'comment' => $row->comment, 
                'company' => $row->name,
                'main_id' => $row->main_id,
                'last_update' => (null != $row->stamp) ? date('Y-m-d', $row->stamp) : '', 

                ];
            }

            return $ab;

        } catch (\Exception $e) {
            // Log the error
            $this->logger->error('Error retrieving address book: @message', [
                '@message' => $e->getMessage(),
            ]);
            
            throw new \RuntimeException('Unable to retrieve address book data', 0, $e);
        }

    }

    /**
     * {@inheritdoc}
     */
    public function record($table, string $data = "") {

        if ($data == "") {
            return null;
        }

        $data = unserialize($data);
        switch ($table) {
                case 'main': 
                    $tb = "ek_address_book";
                break;

                case 'contact':
                    $tb = "ek_address_book_contacts";
                break;
        }
            
        if($tb == "ek_address_book") {

            try {
                $fields = [
                    'name' => $data['name'],
                    'shortname' => $data['shortname'],
                    'reg' => $data['reg'],
                    'address' => $data['address'],
                    'address2' => $data['address2'],
                    'city' => $data['city'],
                    'postcode' => $data['postcode'],
                    'state' => $data['state'],
                    'country' => $data['country'],
                    'telephone' => $data['telephone'],
                    'fax' => $data['fax'],
                    'website' => $data['website'],
                    'type' => $data['type'],
                    'category' => $data['category'],
                    'activity' => $data['activity'],
                    'status' => 1,
                    'stamp' => strtotime("now"),
                    'created' => date('Y-m-d')
                ];

                $insert = $this->extdb
                    ->insert($tb)
                    ->fields($fields)
                    ->execute();

            } catch (\Exception $e) {
                // Log the error
                $this->logger->error('Error record address book: @message', [
                    '@message' => $e->getMessage(),
                ]);
                
                throw new \RuntimeException('Unable to record address book data', 0, $e);
            }

        }


        if($tb == "ek_address_book_contacts") {

            try {
                $fields = [
                    'abid' => $data['abid'],
                    'contact_name' => $data['contact_name'],
                    'salutation' => $data['salutation'],
                    'title' => $data['title'],
                    'telephone' => $data['telephone'],
                    'mobilephone' => $data['mobilephone'],
                    'email' => $data['email'],
                    'card' => $data['card'],
                    'department' => $data['department'],
                    'link' => $data['link'],
                    'comment' => $data['comment'],
                    'main' => $data['main'],
                    'stamp' => strtotime("now"),
                ];

                $insert = $this->extdb
                    ->insert($tb)
                    ->fields($fields)
                    ->execute();

            } catch (\Exception $e) {
                // Log the error
                $this->logger->error('Error record address book contact: @message', [
                    '@message' => $e->getMessage(),
                ]);
                
                throw new \RuntimeException('Unable to record address book contact data', 0, $e);
            }

        }
        
        return $insert;
    }


    /**
     * {@inheritdoc}
     */
    public function update($table, $id, string $data = "") {

        if ($data == "") {
            return null;
        }

        $data = unserialize($data);
        switch ($table) {
                case 'main': 
                    $tb = "ek_address_book";
                break;

                case 'contact':
                    $tb = "ek_address_book_contacts";
                break;
        }
            
        if($tb == "ek_address_book") {

            try {
                $fields = [
                    'name' => $data['name'],
                    'shortname' => $data['shortname'],
                    'reg' => $data['reg'],
                    'address' => $data['address'],
                    'address2' => $data['address2'],
                    'city' => $data['city'],
                    'postcode' => $data['postcode'],
                    'state' => $data['state'],
                    'country' => $data['country'],
                    'telephone' => $data['telephone'],
                    'fax' => $data['fax'],
                    'website' => $data['website'],
                    'type' => $data['type'],
                    'category' => $data['category'],
                    'activity' => $data['activity'],
                    'status' => $data['status'],
                    'stamp' => $data['stamp'],
                ];

                $update = $this->extdb
                    ->update($tb)
                    ->fields($fields)
                    ->condition('id', $id)
                    ->execute();

            } catch (\Exception $e) {
                // Log the error
                $this->logger->error('Error update address book: @message', [
                    '@message' => $e->getMessage(),
                ]);
                
                throw new \RuntimeException('Unable to update address book data', 0, $e);
            }

        }

        if($tb == "ek_address_book_contacts") {

            try {
                $fields = [
                    'abid' => $data['abid'],
                    'contact_name' => $data['contact_name'],
                    'salutation' => $data['salutation'],
                    'title' => $data['title'],
                    'telephone' => $data['telephone'],
                    'mobilephone' => $data['mobilephone'],
                    'email' => $data['email'],
                    'card' => $data['card'],
                    'department' => $data['department'],
                    'link' => $data['link'],
                    'main' => $data['main'],
                    'comment' => $data['comment'],
                    'stamp' => $data['stamp'],
                ];

                $update = $this->extdb
                    ->update($tb)
                    ->fields($fields)
                    ->condition('id', $id)
                    ->execute();

            } catch (\Exception $e) {
                // Log the error
                $this->logger->error('Error update address book contact: @message', [
                    '@message' => $e->getMessage(),
                ]);
                
                throw new \RuntimeException('Unable to update address book contact data', 0, $e);
            }

        }
        
        return $update;

    }
}