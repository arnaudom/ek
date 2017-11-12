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
        /*
          return $response;
         */
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
                        '#attached' => array('library' => array(array('system', 'drupal.ajax'),),),
                    );
                } else {
                    $pic = '../modules/ek_address_book/art/nocard.png';
                    $contact['card'] = "<img class='thumbnail' src='$pic' />";
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
            );
        }
    }

    /**
     * AJAX callback handler 
     */
    public function modal($js = 'nojs', $id = NULL) {
        if ($js == 'ajax') {
            $options = array('width' => '30%');
            $query = "SELECT card from {ek_address_book_contacts} where id=:id";
            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id))
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
dpm($abid);
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
            );

            $newid = Database::getConnection('external_db', 'external_db')
                            ->insert('ek_address_book')
                            ->fields($fields)->execute();
            //copy contacts
            $query = "SELECT * from {ek_address_book_contacts} WHERE abid=:id";
            $result = Database::getConnection('external_db', 'external_db')
                    ->query($query, array(':id' => $id));

            while ($r = $result->fetchObject()) {

                $fields = array(
                    'abid' => $newid,
                    'contact_name' => $r->contact_name,
                    'salutation' => $r->salutation,
                    'title' => $r->title,
                    'telephone' => $r->ctelephone,
                    'mobilephone' => $r->cmobilephone,
                    'email' => $r->email,
                    'card' => $r->card,
                    'department' => $r->department,
                    'link' => $r->link,
                    'comment' => $r->ccomment,
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

            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookForm', $newid);

            return array(
                '#theme' => 'ek_address_book_form',
                '#items' => $response,
                '#title' => t('Edit cloned address book'),
                '#attached' => array(
                    'library' => array('ek_address_book/ek_address_book_css'),
                ),
            );
        } else {
            //cannot clone this entry  
            return array('#markup' => t('This entry cannot be cloned.'));
        }
    }

    /**
     * Return add name card form page.
     *
     */
    public function newaddressbookcard(Request $request, $id = NULL) {


        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookCardForm', $id);

        return $response;
    }

    /**
     * Return the delete organization form page.
     *
     */
    public function deleteaddressbook(Request $request, $id = NULL) {

        $form_builder = $this->formBuilder();
        $form_builder->setRequest($request);
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\DelAddressBookForm', $id);

        return $response;
    }

    /**
     * Util to return contact name callback.
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   An Json response object.
     */
    public function ajaxlookupcontact(Request $request, $type) {

        $text = $request->query->get('q');
        $name = array();
        if ($type == '%') {
            $query = "SELECT distinct name FROM {ek_address_book} a "
                    . "LEFT JOIN {ek_address_book_contacts} c "
                    . " ON a.id=c.abid WHERE "
                    . " name like :t1 OR shortname like :t2 OR contact_name like :t3";
            $a = array(':t1' => "$text%", ':t2' => "$text%", ':t3' => "%$text%");
            $name = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();
        } else {
            $query = "SELECT distinct name FROM {ek_address_book} a "
                    . "LEFT JOIN {ek_address_book_contacts} c "
                    . " ON a.id=c.abid where type=:t AND (name like :t1 OR shortname like :t2 Or contact_name like :t3)";
            $a = array(':t' => $type, ':t1' => "$text%", ':t2' => "$text%", ':t3' => "%$text%");
            $name = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchCol();
        }

        return new JsonResponse($name);
    }

    /**
     * Util to return contact email callback.
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

        $text = $request->query->get('term') . '%';
        $query = "SELECT Distinct activity from {ek_address_book} where activity like :text";
        $data = Database::getConnection('external_db', 'external_db')
                ->query($query, array(':text' => $text))
                ->fetchCol();


        return new JsonResponse($data);
    }

    /**
     * Return contact names in pdf file.
     * @return pdf document
     */
    public function pdfaddressbook(Request $request, $id, $cid) {
        $markup = array();
        include_once drupal_get_path('module', 'ek_address_book') . '/contact_pdf.inc';
        return $markup;
    }

}

?>