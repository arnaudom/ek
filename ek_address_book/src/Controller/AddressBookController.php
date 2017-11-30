<?php

/**
 * @file
 * Contains \Drupal\ek\Controller\AddressBookController.
 */

namespace Drupal\ek_address_book\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller routines for ek module routes.
 */
class AddressBookController extends ControllerBase {
    /* The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */

    protected $moduleHandler;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * Constructs a  object.
     *
     *   The moduleexist service.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * Return lookup address form 
     *
     */
    public function searchaddressbook(Request $request) {

        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\SearchAddressBookForm');
        
        return array(
            '#theme' => 'ek_address_book_search_form',
            '#items' => $response,
            '#title' => t('Address book'),
            '#attached' => array(
                'library' => array('ek_address_book/ek_address_book_css'),
            ),
        );
    }

    /**
     * Return lookup address form page and display address book data.
     *
     */
    public function viewaddressbook(Request $request, $abid = NULL) {

        //if no id, get the search form
        if ($abid == NULL || $abid == 0) {
            
        } else {
            //return the data
            $items = array();
            
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'a');
            $query->fields('a');
            $query->leftJoin('ek_address_book_comment', 'b', 'a.id = b.abid');
            $query->fields('b');
            $query->condition('id', $abid, '=');

            $r = $query->execute()->fetchAssoc();

            $url_search = Url::fromRoute('ek_address_book.search', array(), array())->toString();
            $url_add = Url::fromRoute('ek_address_book.newcard', array('abid' => $r['id']), array())->toString();

            if ($this->moduleHandler->moduleExists('ek_sales')) {
                $url_sales = Url::fromRoute('ek_sales.data', array('abid' => $r['id']), array())->toString();
                $items['sales'] = t('<a href="@url" >Sales data</a>', array('@url' => $url_sales));
            }

            $query = "SELECT id,type from {ek_address_book} WHERE name=:n AND type <> :t";
            $check = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':n' => $r['name'], ':t' => $r['type']))
                    ->fetchObject();

            if (isset($check->type) && $check->type == '1') {
                $clone = FALSE;
            } elseif (isset($check->type) && $check->type == '2') {
                $clone = FALSE;
            } elseif (isset($check->type) && $check->type == '3') {
                $clone = TRUE;
                $into = t('client');
            } else {
                $clone = TRUE;      
            }
            $into = ($r['type'] == '1') ? t('supplier') : t('client');
            if ($clone == TRUE) {
                $url_clone = Url::fromRoute('ek_address_book.clone', array('abid' => $r['id']), array())->toString();
                $items['clone'] = t('<a href="@url" title="@i">Clone</a>', array('@url' => $url_clone, '@i' => $into));
            } else {
                $url_clone = Url::fromRoute('ek_address_book.view', array('abid' => $check->id), array())->toString();
                $items['clone'] = t('<a href="@url" title="@i"><-></a>', array('@url' => $url_clone, '@i' => $into));
            }
            $items['stamp'] = date('Y-m-d', $r['stamp']);
            $items['id'] = $r['id'];
            $items['search'] = t('<a href="@url" >New search</a>', array('@url' => $url_search));

            $items['add'] = t('<a href="@url" >Add contact</a>', array('@url' => $url_add));
            $items['name'] = ucwords($r['name']);
            $items['shortname'] = $r['shortname'];
            $items['address'] = ucwords($r['address']);
            $items['address2'] = ucwords($r['address2']);
            $items['postcode'] = ucwords($r['postcode']);
            $items['city'] = ucwords($r['city']);
            $items['country'] = ucwords($r['country']);
            $items['telephone'] = $r['telephone'];
            $items['fax'] = $r['fax'];
            $items['website'] = $r['website'];
            $items['activity'] = ucwords($r['activity']);
            $t = array(1 => t('client'), 2 => t('supplier'), 3 => t('other'));
            $items['type'] = $t[$r['type']];



            $c = array(1 => t('Head office'), 2 => t('Store'), 3 => t('Factory'), 4 => t('Other'));
            $items['category'] = $c[$r['category']];

            if (\Drupal::currentUser()->hasPermission('sales_data')) {
                $items['comment'] = $r['comment'];
            }

            if ($r['logo']) {
                $items['logo_url'] = file_create_url($r['logo']);
                $items['logo_img'] = "<img class='thumbnail' src='"
                        . file_create_url($r['logo']) . "'>";
                
            } else {
                $items['logo_url'] = '';
                $items['logo_img'] = '';
            }

            $s = array(0 => t('inactive'), 1 => t('active'));
            $items['status'] = $s[$r['status']];
            $id = $r['id'];
            $items['contacts'] = array();

            $query = "SELECT * FROM {ek_address_book_contacts} WHERE abid=:id ORDER BY main DESC";
            $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $abid));


            while ($r = $data->fetchAssoc()) {
                $contact = array();
                $contact['id'] = $r['id'];
                $contact['contact_name'] = ucwords($r['contact_name']);
                $contact['salutation'] = $r['salutation'];
                $contact['title'] = ucwords($r['title']);
                $contact['telephone'] = $r['telephone'];
                $contact['mobilephone'] = $r['mobilephone'];
                $contact['email'] = $r['email'];

                if ($r['card'] <> '') {
                    $image = "<img class='thumbnail' src=" . file_create_url($r['card']) . ">";
                    $url = file_create_url($r['card']);
                    $markup = "<a href='modal/nojs/" . $r['id']
                            . "' class='use-ajax'><img class='thumbnail' src=" . file_create_url($r['card']) . "></a>";

                    $contact['card'] = array(
                        '#type' => 'markup',
                        '#markup' => $markup,
                    );
                } else {
                    $pic = '../modules/ek_address_book/art/nocard.png';
                    $contact['card'] = "<img class='thumbnail' src='$pic'/>";
                }
                $contact['department'] = ucwords($r['department']);
                $contact['link'] = $r['link'];
                $contact['comment'] = $r['comment'];

                $url_pdf = Url::fromRoute('ek_address_book.pdf', array('abid' => $abid, 'cid' => $r['id']), array())->toString();
                $contact['pdf'] = t('<a href="@url" target="_blank">Pdf</a>', array('@url' => $url_pdf));

                array_push($items['contacts'], $contact);
            }


            return array(
                '#theme' => 'ek_address_book_card',
                '#items' => $items,
                '#attached' => array(
                    'library' => array('ek_address_book/ek_address_book.dialog', 'ek_admin/ek_admin_css'),
                ),
                '#cache' => [
                    'tags' => ['address_book_card'],
                    'contexts' => [],
                ],
            );
        }
    }

    /**
     * AJAX callback handler 
     */
    public function modal($js = 'nojs', $abid = NULL) {
        if ($js == 'ajax') {
            $options = array('width' => '30%');
            $query = "SELECT card from {ek_address_book_contacts} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $abid))
                    ->fetchField();
            $image = "<img  src=" . file_create_url($data) . ">";
            $content = array(
                'content' => array(
                    '#markup' => $image,
                ),
            );
            $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
            $response = new AjaxResponse();
            $response->addCommand(
                    new OpenModalDialogCommand(t('Card'), $content, $options));
            return $response;
        } else {
            return t('You need javascript to be enabled.');
        }
    }

    /**
     * Return list of names.
     * access addresses from list
     */
    public function listaddressbook(Request $request) {
        return array();
    }

    /**
     * Return list by contact names.
     * access addresses from list
     */
    public function contactsaddressbook(Request $request) {
        return array();
    }

    /**
     * Return the new address book form page as well as the address book edition page.
     * @param
     *  id: main address book id
     */
    public function newaddressbook(Request $request, $abid = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookForm', $abid);

        return array(
            '#theme' => 'ek_address_book_form',
            '#items' => $response,
            '#title' => t('Edit address book'),
            '#attached' => array(
                'library' => array('ek_address_book/ek_address_book_css'),
            ),
        );
    }

    /**
     * Clone address book entry under different type
     * When an entity is both a client and supplier the entry can be cloned
     * * under different type
     */
    public function cloneaddressbook(Request $request, $abid = NULL) {

        $query = "SELECT * from {ek_address_book} WHERE id=:id";
        $r = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':id' => $abid))
                ->fetchObject();

        //check the entry has not been cloned already
        // there should be only 3 types per name
        $clone = TRUE;
        $query = "SELECT type from {ek_address_book} WHERE name=:n AND type <> :t";
        $check = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':n' => $r->name, ':t' => $r->type))
                ->fetchField();

        if ($check == '1') {
            $clone = FALSE;
        } elseif ($check == '2') {
            $clone = FALSE;
        } elseif ($check == '3') {
            $newtype = 1;
        } else {
            $newtype = ($r->type == '1') ? 2 : 1;
        }

        if ($clone == TRUE) {
            //copy main data
            $fields = array(
                'name' => $r->name,
                'shortname' => $r->shortname,
                'address' => $r->address,
                'address2' => $r->address2,
                'postcode' => $r->postcode,
                'city' => $r->city,
                'country' => $r->country,
                'telephone' => $r->telephone,
                'fax' => $r->fax,
                'website' => $r->website,
                'type' => $newtype,
                'category' => $r->category,
                'activity' => $r->activity,
                'status' => 1,
                'stamp' => strtotime("now"),
                'logo' => $r->logo,
            );

            $newid = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book')
                            ->fields($fields)->execute();
            //copy contacts
             $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book_contacts', 'c');        
      
            $data = $query
                ->fields('c')
                ->condition('c.abid', $abid , '=')
                ->execute();
            
            while ($r = $data->fetchObject()) {

                $fields = array(
                    'abid' => $newid,
                    'contact_name' => $r->contact_name,
                    'salutation' => $r->salutation,
                    'title' => $r->title,
                    'telephone' => $r->telephone,
                    'mobilephone' => $r->mobilephone,
                    'email' => $r->email,
                    'card' => $r->card,
                    'department' => $r->department,
                    'link' => $r->link,
                    'comment' => $r->comment,
                    'main' => $r->main,
                    'stamp' => strtotime("now"),
                );

                Database::getConnection('external_db', 'external_db')
                        ->insert('ek_address_book_contacts')
                        ->fields($fields)->execute();
            }
            
            //create comment entry
            Database::getConnection('external_db', 'external_db')
                        ->insert('ek_address_book_comment')
                        ->fields(['abid' => $newid])->execute();

            $url = Url::fromRoute('ek_address_book.edit', ['abid' => $newid])->toString();
            return new RedirectResponse($url);
             
        } else {
            //cannot clone this entry  
            
            return array('#markup' => t('This entry cannot be cloned.'));
        }
    }

    /**
     * @param int $abid address book main table ID
     * @return add name card form page object.
     *
     */
    public function newaddressbookcard(Request $request, $abid = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookCardForm', $abid);

        return $response;
    }

    /**
     * @param int $abid address book main table ID
     * @return array the delete organization form page object.
     *
     */
    public function deleteaddressbook($abid = NULL) {

        //filter usage of address book entry before deletion
        //finance
        $usage = [];
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_expenses', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $or = db_or();
            $or->condition('clientname', $abid, '=');
            $or->condition('suppliername', $abid, '=');
            $query->condition($or)->groupBy('t.id');
            $data = $query->execute();
            
            if($data->fetchObject()->ids > 0){
               $usage[] = t('finance'); 
            }
                
        }
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_items', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('supplier', $abid, '=');
            $query->groupBy('t.id');
            $data = $query->execute();
            
            if($data->fetchObject()->ids > 0){
               $usage[] = t('products & services'); 
            }
        }
        if ($this->moduleHandler->moduleExists('ek_logistics')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_logi_delivery', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('client', $abid, '=');
            $query->groupBy('t.id');
            $data = $query->execute();

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_logi_receiving', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('supplier', $abid, '=');
            $query->groupBy('t.id');
            $data2 = $query->execute();
            
            if($data->fetchObject()->ids > 0 || $data2->fetchObject()->ids > 0){
               $usage[] = t('logistics'); 
            }
        }
        if ($this->moduleHandler->moduleExists('ek_sales')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_invoice', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('client', $abid, '=');
            $query->groupBy('t.id');
            $data = $query->execute();

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_purchase', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('client', $abid, '=');
            $query->groupBy('t.id');
            $data2 = $query->execute();

            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_sales_quotation', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('client', $abid, '=');
            $query->groupBy('t.id');
            $data3 = $query->execute();    
            
            if($data->fetchObject()->ids > 0 || $data2->fetchObject()->ids > 0
                    || $data3->fetchObject()->ids > 0){
               $usage[] = t('sales'); 
            }
        }        
        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_project', 't');
            $query->fields('t', ['id']);
            $query->addExpression('count(id)', 'ids');
            $query->condition('client_id', $abid, '=');
            $query->groupBy('t.id');
            $data = $query->execute();
            
            if($data->fetchObject()->ids > 0){
               $usage[] = t('projects'); 
            }
        }
        
        if(!empty($usage)) {
            $modules = implode(', ', $usage);
            $response = ['#markup' => t('This address book cannot be deleted. It is used in following module(s): @m',
                        ['@m' => $modules]),
                ];
        } else {
        
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_address_book\Form\DeleteAddressBook', $abid);
        }

        return $response;
    }

    /**
     * Util to return contact name callback.
     * @param (string) $type type of contact % default, 1 client, 2 supplier, 3 other, 4 cards
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxlookupcontact(Request $request, $type) {

        $text = (null !==$request->query->get('q')) ? $request->query->get('q') : $request->query->get('term');
        $option = $request->query->get('option');
        $name = array();
        if(strpos('%', $text)>= 0) {
            $text = str_replace('%', '', $text);
        }
       

        if($type < 4 || $type == '%') {
            
            //pull company names
            $types = array(1 => t('client'), 2 => t('supplier'), 3 => t('other'));
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'name', 'type','logo'])->distinct();

            $query->leftJoin('ek_address_book_contacts', 'bc', 'ab.id = bc.abid');

            $or = db_or();
            $or->condition('name', $text . "%", 'like');
            $or->condition('shortname', $text . "%", 'like');
            $or->condition('contact_name', $text . "%", 'like');
            $or->condition('contact_name', $text . "%", 'like');
            $or->condition('activity', "%" . $text . "%", 'like');
            $query->condition($or);

            if ($type != '%') {
                $query->condition('type', $type, '=');
            } 
            $data = $query->execute();
            $result = [];
            if($option == 'image') {
                    
                    while($r = $data->fetchObject()){
                        $line = [];

                        if ($r->logo) {
                                 $pic = "<img class='abook_thumbnail'' src='"
                                . file_create_url($r->logo) . "'>";
                        } else {
                                $default = file_create_url(drupal_get_path('module', 'ek_address_book') . '/art/default.jpg');
                                $pic = "<img  class='abook_thumbnail' src='"
                                . $default . "'>";
                        }
                            $line['picture'] = isset($pic) ? $pic : '';
                            $line['type'] = $types[$r->type];
                            $line['name'] = $r->name;
                            $line['id'] = $r->id;

                            $result[] = $line;
                    }

                } else {
                    while($r = $data->fetchObject()){
                        $result[] = $r->name;
                    }
                }
        } else {
            //pull name cards
            $query = Database::getConnection('external_db', 'external_db')
                        ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc', ['id', 'contact_name', 'salutation','abid'])->distinct();
            $or = db_or();
            $or->condition('contact_name', $text . "%", 'like');
            $or->condition('contact_name', "%" . $text . "%", 'like');
            $query->condition($or);

            $data = $query->execute();
                while($r = $data->fetchObject()){
                        $result[] = $r->contact_name;
                }
            
            
        }
        
        return new JsonResponse($result);
    }

    /**
     * Util to return contact email callback.
     * @param (string) $type type of contact %: ek address book & users, user: users table, book: Ek address book
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxlookupemail(Request $request, $type) {

        $text = $request->query->get('term');
        $name = array();
        if ($type == '%') {
            //look in address book and users
            $query = "SELECT distinct email from {ek_address_book_contacts} WHERE email like :t1 or contact_name like :t2 ";
            $a = array(':t1' => "$text%", ':t2' => "$text%");
            $name1 = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();

            $query = "SELECT distinct mail from {users_field_data} WHERE (mail like :t1 OR name like :t2) AND status <> :s";
            $a = array(':t1' => "$text%", ':t2' => "$text%", ':s' => 0);
            $name2 = db_query($query, $a)->fetchCol();

            $name = array_merge($name1, $name2);
        } elseif ($type == 'user') {
            //look in  users
            $query = "SELECT distinct name from {users_field_data} WHERE (mail like :t1 or name like :t2) AND status <> :s";
            $a = array(':t1' => "$text%", ':t2' => "$text%", ':s' => 0);
            $name = db_query($query, $a)->fetchCol();
        } elseif ($type == 'book') {
            //look in  book   
            $query = "SELECT distinct email from {ek_address_book_contacts} WHERE email like :t1 or contact_name like :t2 ";
            $a = array(':t1' => "$text%", ':t2' => "$text%");
            $name = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();
        }

        return new JsonResponse($name);
    }

    /**
     * Util to return short name data
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxsnbuilt(Request $request) {

        $text = $request->query->get('term');

        $entry = explode(' ', $text);
        $terms = count($entry);
        $short_name = '';

        if ($terms == 1) {
            $short_name .= substr($text, 0, 4);
        } elseif ($terms > 1) {

            for ($i = 0; $i <= 3; $i++) {

                if (!isset($entry[$i])) {
                    $short_name .= '-';
                } elseif(substr($entry[$i], 0, 1) == '/') {
                    $short_name .= '|';
                } else {
                    $short_name .= substr($entry[$i], 0, 1);
                }
            }
        }
        
        

        //confirm entry
        $valid = '';
        for ($i = 0; $i < $terms; $i++) {
            $valid .= $entry[$i] . '%';
        }

        $query = "SELECT count(name) from {ek_address_book} where name like :text";
        $ok = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':text' => $valid))
                ->fetchField();
        $alert = NULL;
        if ($ok == 1) {
            $alert = t('This name already exist in the records');
        }

        return new JsonResponse(array('sn' => $short_name, 'name' => $ok, 'alert' => $alert));
    }

    /**
     * Return ajax activity data
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxactivity(Request $request) {

        $text = $request->query->get('term');
        $result = [];
        $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
        $query->fields('ab', ['activity'])->distinct();

        $or = db_or();
        $or->condition('activity', $text . "%", 'like');
        $or->condition('activity', "%," . $text . "%", 'like');
        $query->condition($or);
        
        $data = $query->execute();
       
        while($string = $data->fetchObject()) {
            $parts = explode(",", $string->activity);
            foreach($parts as $key => $word) {
                if(stristr ($word, $request->query->get('term'))) {
                    $result[] = $word;
                }
            }
        }
        
        return new JsonResponse($result);
    }

    /**
     * Return contact names in pdf file.
     * @return pdf document
     */
    public function pdfaddressbook(Request $request, $abid, $cid) {
        $markup = array();
        include_once drupal_get_path('module', 'ek_address_book') . '/contact_pdf.inc';
        return $markup;
    }

}

?>