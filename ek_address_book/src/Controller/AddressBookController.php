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
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
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
                $container->get('module_handler'),
                $container->get('file_url_generator'),
                $container->get('file_system')

        );
    }
    

    /**
     * Constructs a  object.
     *
     */
    public function __construct(ModuleHandler $module_handler,FileUrlGeneratorInterface $file_url_generator, FileSystemInterface $file_system) {
        $this->moduleHandler = $module_handler;
        $this->fileUrlGenerator = $file_url_generator;
        $this->fileSystem = $file_system;
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
            '#title' => $this->t('Address book'),
            '#attached' => array(
                'library' => array('ek_address_book/ek_address_book_css'),
            ),
        );
    }

    /**
     * Return lookup address form page and display address book data.
     *
     */
    public function viewaddressbook(Request $request, $abid = null) {

        //if no id, get the search form
        if ($abid == null || $abid == 0) {
            
        } else {
            //return the data
            $items = [];

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'a');
            $query->fields('a');
            $query->leftJoin('ek_address_book_comment', 'b', 'a.id = b.abid');
            $query->fields('b');
            $query->condition('id', $abid, '=');

            $r = $query->execute()->fetchAssoc();

            $url_search = Url::fromRoute('ek_address_book.search', [], [])->toString();
            $url_add = Url::fromRoute('ek_address_book.newcard', ['abid' => $r['id']], [])->toString();

            if ($this->moduleHandler->moduleExists('ek_sales')) {
                $url_sales = Url::fromRoute('ek_sales.data', ['abid' => $r['id']], [])->toString();
                $items['sales'] = $this->t('<a href="@url" >Sales data</a>', ['@url' => $url_sales]);
            }

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'type']);
            $query->condition('name', $r['name']);
            $query->condition('type', $r['type'], '<>');
            $check = $query->execute()->fetchObject();

            if (isset($check->type) && $check->type == '1') {
                $clone = false;
            } elseif (isset($check->type) && $check->type == '2') {
                $clone = false;
            } elseif (isset($check->type) && $check->type == '3') {
                $clone = true;
                $into = $this->t('client');
            } else {
                $clone = true;
            }
            $into = ($r['type'] == '1') ? $this->t('supplier') : $this->t('client');
            if ($clone == true) {
                $url_clone = Url::fromRoute('ek_address_book.clone', ['abid' => $r['id']], [])->toString();
                $items['clone'] = $this->t('<a href="@url" title="@i">Clone</a>', ['@url' => $url_clone, '@i' => $into]);
            } else {
                $url_clone = Url::fromRoute('ek_address_book.view', ['abid' => $check->id], [])->toString();
                $items['clone'] = $this->t('<a href="@url" title="@i"><-></a>', ['@url' => $url_clone, '@i' => $into]);
            }
            $items['stamp'] = date('Y-m-d', $r['stamp']);
            $items['id'] = $r['id'];
            $items['search'] = $this->t('<a href="@url" >New search</a>', ['@url' => $url_search]);

            $items['add'] = $this->t('<a href="@url" >Add contact</a>', ['@url' => $url_add]);
            $items['name'] = ucwords($r['name']);
            $items['shortname'] = $r['shortname'];
            $items['address'] = ucwords($r['address']);
            $items['address2'] = ucwords($r['address2']);
            $items['state'] = ucwords($r['state']);
            $items['postcode'] = ucwords($r['postcode']);
            $items['city'] = ucwords($r['city']);
            $items['country'] = ucwords($r['country']);
            $items['telephone'] = $r['telephone'];
            $items['fax'] = $r['fax'];
            $items['website'] = $r['website'];
            $items['reg'] = $r['reg'];
            $items['activity'] = ucwords($r['activity']);
            $t = [1 => $this->t('client'), 2 => $this->t('supplier'), 3 => $this->t('other')];
            $items['type'] = $t[$r['type']];



            $c = [1 => $this->t('Head office'), 2 => $this->t('Store'), 3 => $this->t('Factory'), 4 => $this->t('Other')];
            $items['category'] = $c[$r['category']];

            if (\Drupal::currentUser()->hasPermission('sales_data')) {
                $items['comment'] = $r['comment'];
            }

            if ($r['logo'] != '' && file_exists($r['logo'])) {
                $items['logo_url'] = \Drupal::service('file_url_generator')->generateAbsoluteString($r['logo']);
                $items['logo_img'] = "<img class='thumbnail' src='" 
                        . $items['logo_url'] . "'>";
            } else {
                $items['logo_url'] = '';
                $items['logo_img'] = '';
            }

            $s = array(0 => $this->t('inactive'), 1 => $this->t('active'));
            $items['status'] = $s[$r['status']];
            $id = $r['id'];
            $items['contacts'] = array();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc');
            $query->condition('abid', $abid);
            $query->orderBy('main', 'DESC');
            $data = $query->execute();

            while ($r = $data->fetchAssoc()) {
                $contact = array();
                $contact['id'] = $r['id'];
                $contact['contact_name'] = ucwords($r['contact_name']);
                $contact['salutation'] = $r['salutation'];
                $contact['title'] = ucwords($r['title']);
                $contact['telephone'] = $r['telephone'];
                $contact['mobilephone'] = $r['mobilephone'];
                $contact['email'] = $r['email'];
                $contact['card'] = (!Null == $r['card']) ? $this->fileUrlGenerator->generateAbsoluteString($r['card']): '';
                if ($r['card'] <> '') {
                    $image = "<img class='thumbnail' src=" . $contact['card'] . ">";
                    $markup = "<a href='modal/nojs/" . $r['id']
                            . "' class='use-ajax'><img class='thumbnail' src=" . $contact['card'] . "></a>";

                    $contact['card_'] = [
                        '#type' => 'markup',
                        '#markup' => $markup,
                    ];
                } else {
                    $contact['card_'] = "";
                }
                $contact['department'] = ucwords($r['department']);
                $contact['link'] = $r['link'];
                $contact['comment'] = $r['comment'];

                $url_pdf = Url::fromRoute('ek_address_book.pdf', ['abid' => $abid, 'cid' => $r['id']], [])->toString();
                $contact['pdf'] = $this->t('<a href="@url" target="_blank">Pdf</a>', ['@url' => $url_pdf]);

                array_push($items['contacts'], $contact);
            }


            return array(
                '#theme' => 'ek_address_book_card',
                '#items' => $items,
                '#attached' => [
                    'library' => ['ek_address_book/ek_address_book.dialog', 'ek_admin/ek_admin_css'],
                ],
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
    public function modal($js = 'nojs', $abid = null) {
        if ($js == 'ajax') {
            $options = array('width' => '30%');
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc', ['card']);
            $query->condition('id', $abid);
            $data = $query->execute()->fetchField();

            $image = "<img  src=" . \Drupal::service('file_url_generator')->generateAbsoluteString($data) . ">";
            $content = array(
                'content' => array(
                    '#markup' => $image,
                ),
            );
            $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
            $response = new AjaxResponse();
            $response->addCommand(
                    new OpenModalDialogCommand(t('Card'), $content, $options)
            );
            return $response;
        } else {
            return $this->t('You need javascript to be enabled.');
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
    public function newaddressbook(Request $request, $abid = null) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookForm', $abid);

        return array(
            '#theme' => 'ek_address_book_form',
            '#items' => $response,
            '#title' => $this->t('Edit address book'),
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
    public function cloneaddressbook(Request $request, $abid = null) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book', 'ab');
        $query->fields('ab');
        $query->condition('id', $abid);
        $r = $query->execute()->fetchObject();

        //check the entry has not been cloned already
        // there should be only 3 types per name
        $clone = true;
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book', 'ab');
        $query->fields('ab', ['type']);
        $query->condition('name', $r->name);
        $query->condition('type', $r->type, '<>');
        $check = $query->execute()->fetchField();

        if ($check == '1') {
            $clone = false;
        } elseif ($check == '2') {
            $clone = false;
        } elseif ($check == '3') {
            $newtype = 1;
        } else {
            $newtype = ($r->type == '1') ? 2 : 1;
        }

        if ($clone == true) {
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
                    ->condition('c.abid', $abid, '=')
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

            return array('#markup' => $this->t('This entry cannot be cloned.'));
        }
    }

    /**
     * @param int $abid address book main table ID
     * @return add name card form page object.
     *
     */
    public function newaddressbookcard(Request $request, $abid = null) {
        $form_builder = $this->formBuilder();
        $response = $form_builder->getForm('Drupal\ek_address_book\Form\NewAddressBookCardForm', $abid);

        return $response;
    }

    /**
     * @param int $abid address book main table ID
     * @return array the delete organization form page object.
     *
     */
    public function deleteaddressbook($abid = null) {

        //filter usage of address book entry before deletion
        //finance
        $usage = [];
        if ($this->moduleHandler->moduleExists('ek_finance')) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_expenses', 't');
            $condition = $query->orConditionGroup()
                    ->condition('clientname', $abid)
                    ->condition('suppliername', $abid);
            $query->condition($condition);
            $data = $query->countQuery()->execute()->fetchField();

            if ($data > 0) {
                $usage[] = $this->t('finance');
            }
        }
        if ($this->moduleHandler->moduleExists('ek_products')) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_items', 't');
            $query->condition('supplier', $abid, '=');
            $data = $query->countQuery()->execute()->fetchField();

            if ($data > 0) {
                $usage[] = $this->t('products & services');
            }
        }
        if ($this->moduleHandler->moduleExists('ek_logistics')) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_delivery', 't');
            $query->condition('client', $abid, '=');
            $data = $query->countQuery()->execute()->fetchField();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_logi_receiving', 't');
            $query->condition('supplier', $abid, '=');
            $data2 = $query->countQuery()->execute()->fetchField();

            if ($data > 0 || $data2 > 0) {
                $usage[] = $this->t('logistics');
            }
        }
        if ($this->moduleHandler->moduleExists('ek_sales')) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_invoice', 't');
            $query->condition('client', $abid, '=');
            $data = $query->countQuery()->execute()->fetchField();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_purchase', 't');
            $query->condition('client', $abid, '=');
            $data2 = $query->countQuery()->execute()->fetchField();

            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_sales_quotation', 't');
            $query->condition('client', $abid, '=');
            $data3 = $query->countQuery()->execute()->fetchField();

            if ($data > 0 || $data2 > 0 || $data3 > 0) {
                $usage[] = $this->t('sales');
            }
        }
        if ($this->moduleHandler->moduleExists('ek_projects')) {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_project', 't');
            $query->condition('client_id', $abid, '=');
            $data = $query->countQuery()->execute()->fetchField();

            if ($data > 0) {
                $usage[] = $this->t('projects');
            }
        }

        if (!empty($usage)) {
            $modules = implode(', ', $usage);
            $items['type'] = 'delete';
            $items['message'] = ['#markup' => $this->t('This address book entry cannot be deleted.')];
            $items['description'] = ['#markup' => $this->t('Used in @m', ['@m' => $modules])];
            $url = Url::fromRoute('ek_address_book.view', ['abid' => $abid], [])->toString();
            $items['link'] = ['#markup' => $this->t("<a href=\"@url\">Back</a>", ['@url' => $url])];

            $response = [
                '#items' => $items,
                '#theme' => 'ek_admin_message',
                '#attached' => array(
                    'library' => array('ek_admin/ek_admin_css'),
                ),
                '#cache' => ['max-age' => 0,],
            ];
        } else {
            $form_builder = $this->formBuilder();
            $response = $form_builder->getForm('Drupal\ek_address_book\Form\DeleteAddressBook', $abid);
        }

        return $response;
    }

    /**
     * Util to return contact name callback.
     * @param (string) $type type of contact % default, 1: client, 2: supplier, 3: other, 4: cards, tip
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   A Json response object.
     */
    public function ajaxlookupcontact(Request $request, $type) {
        $text = (null !== $request->query->get('q')) ? $request->query->get('q') : $request->query->get('term');
        $option = $request->query->get('option');
        $name = array();
        if (strpos($text ?? '', '%') >= 0) {
            $text = str_replace('%', '', $text);
        }

        if ($type == 'tip') {
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab')
                    ->fields('ab', ['id', 'name', 'logo']);
            $query->condition('id', $text, '=');
            $data = $query->execute()->fetchObject();
            $logo = '';
            $url = '';
            if ($data->logo && file_exists($data->logo)) {
                // $url = file_create_url($data->logo);
                $url = \Drupal::service('file_url_generator')->generateAbsoluteString($data->logo);
                $logo = "<img class='thumbnail' src='" . $url . "'>";
            }
            $name = html_entity_decode($data->name);
            $render = array(
                '#theme' => 'ek_address_book_tip',
                '#items' => ['type' => 'display', 'name' => $name, 'logo' => $logo, 'url' => $url],
            );
            $card = \Drupal::service('renderer')->render($render);
            return new JsonResponse(['card' => $card]);
        } elseif ($type < 4 || $type == '%') {

            //pull company names
            $types = array(1 => $this->t('client'), 2 => $this->t('supplier'), 3 => $this->t('other'));
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
            $query->fields('ab', ['id', 'name', 'type', 'logo'])->distinct();

            $query->leftJoin('ek_address_book_contacts', 'bc', 'ab.id = bc.abid');
            $or = $query->orConditionGroup()
                    ->condition('name', $text . "%", 'like')
                    ->condition('shortname', $text . "%", 'like')
                    ->condition('contact_name', $text . "%", 'like')
                    ->condition('activity', "%" . $text . "%", 'like');
            $query->condition($or);

            if ($type != '%') {
                $query->condition('type', $type, '=');
            }
            $data = $query->execute();
            $result = [];
            if ($option == 'image') {
                while ($r = $data->fetchObject()) {
                    $line = [];

                    if ($r->logo) {
                        $pic = "<img class='abook_thumbnail'' src='"
                                . \Drupal::service('file_url_generator')->generateAbsoluteString($r->logo) . "'>";
                    } else {
                        $default = \Drupal::service('file_url_generator')
                                ->generateAbsoluteString(\Drupal::service('extension.path.resolver')->getPath('module', 'ek_address_book') . '/art/default.jpg');
                        $pic = "<img  class='abook_thumbnail' src='"
                                . $default . "'>";
                    }
                    $line['picture'] = isset($pic) ? $pic : '';
                    $line['type'] = $types[$r->type];
                    $line['name'] = html_entity_decode($r->name);
                    $line['id'] = $r->id;

                    $result[] = $line;
                }
            } else {
                while ($r = $data->fetchObject()) {
                    $result[] = html_entity_decode($r->name);
                }
            }
        } else {
            //pull name cards
            $types = [1 => $this->t('client'), 2 => $this->t('supplier'), 3 => $this->t('other')];
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'abc');
            $query->fields('abc', ['id', 'contact_name', 'salutation', 'abid']);
            $query->innerJoin('ek_address_book', 'ab', 'ab.id=abc.abid');
            $query->fields('ab', ['name', 'type']);
            $or = $query->orConditionGroup();
            $or->condition('contact_name', $text . "%", 'like');
            $or->condition('contact_name', "%" . $text . "%", 'like');
            $query->condition($or);

            $data = $query->execute();
            while ($r = $data->fetchObject()) {
                $result[] = $r->contact_name . " [" . $r->name . " | " . $types[$r->type] . "]";
            }
        }

        return new JsonResponse($result);
    }

    /**
     * Util to return contact email callback.
     * @param (string) $type type of contact %: ek address book & users, user: users table, book: Ek address book
     * @return \Symfony\Component\HttpFoundation\JsonResponse;
     *   A Json response object.
     */
    public function ajaxlookupemail(Request $request, $type) {
        $text = $request->query->get('term') . "%";
        $name = array();
        if ($type == '%') {
            //look in address book and users
            //used in docs print/share
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'a');
            $query->fields('a', ['email']);
            $condition = $query->orConditionGroup()
                    ->condition('email', $text, 'LIKE')
                    ->condition('contact_name', $text, 'LIKE');
            $query->condition($condition);
            $query->distinct();
            $name1 = $query->execute()->fetchCol();

            $query = Database::getConnection()
                    ->select('users_field_data', 'u');
            $query->fields('u', ['mail']);
            $condition = $query->orConditionGroup()
                    ->condition('mail', $text, 'LIKE')
                    ->condition('name', $text, 'LIKE');
            $query->condition($condition);
            $query->condition('status', 0, '>');
            $query->distinct();
            $name2 = $query->execute()->fetchCol();
            $name = array_merge($name1, $name2);
        } elseif ($type == 'user') {
            //look in  users
            //use in notif
            $query = Database::getConnection()
                    ->select('users_field_data', 'u');
            $query->fields('u', ['name']);
            $condition = $query->orConditionGroup()
                    ->condition('mail', $text, 'LIKE')
                    ->condition('name', $text, 'LIKE');
            $query->condition($condition);
            $query->condition('status', 0, '>');
            $query->distinct();
            $name = $query->execute()->fetchCol();
        } elseif ($type == 'book') {
            //look in  book
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'a');
            $query->fields('a', ['email']);
            $condition = $query->orConditionGroup()
                    ->condition('email', $text, 'LIKE')
                    ->condition('contact_name', $text, 'LIKE');
            $query->condition($condition);
            $query->distinct();
            $name = $query->execute()->fetchCol();
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
                } elseif (substr($entry[$i], 0, 1) == '/') {
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

        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_address_book', 'ab');
        //$query->fields('ab');
        $query->condition('name', $valid, 'LIKE');
        $query->addExpression('Count(name)', 'count');
        $Obj = $query->execute();
        $count = $Obj->fetchObject()->count;

        $alert = null;
        if ($count >= 1) {
            $alert = $this->t('This name already exist in the records');
        }
        return new JsonResponse(array('sn' => $short_name, 'name' => $count, 'alert' => $alert));
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

        $or = $query->orConditionGroup();
        $or->condition('activity', $text . "%", 'like');
        $or->condition('activity', "%," . $text . "%", 'like');
        $query->condition($or);
        $data = $query->execute();

        while ($string = $data->fetchObject()) {
            $parts = explode(",", $string->activity);
            foreach ($parts as $key => $word) {
                if (stristr($word, $request->query->get('term'))) {
                    $result[] = $word;
                }
            }
        }

        return new JsonResponse(array_unique($result));
    }

    /**
     * Return contact names in pdf file.
     * @return pdf document
     */
    public function pdfaddressbook(Request $request, $abid, $cid) {
        $markup = array();
        include_once \Drupal::service('extension.path.resolver')->getPath('module', 'ek_address_book') . '/contact_pdf.inc';
        return $markup;
    }

}
