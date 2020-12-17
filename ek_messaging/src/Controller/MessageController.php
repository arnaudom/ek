<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Controller\MessageController
 */

namespace Drupal\ek_messaging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for ek module routes.
 */
class MessageController extends ControllerBase {
    /* The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandler
     */

    protected $moduleHandler;

    /**
     * The database service.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * The form builder service.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('database'), $container->get('form_builder'), $container->get('module_handler')
        );
    }

    /**
     * Constructs a  object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   A database connection.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder service.
     */
    public function __construct(Connection $database, FormBuilderInterface $form_builder, ModuleHandler $module_handler) {
        $this->database = $database;
        $this->formBuilder = $form_builder;
        $this->moduleHandler = $module_handler;
    }

    /**
     * Return dashboard
     *
     */
    public function dashboard(Request $request) {
        return array('#markup' => $this->t('under construction'));
    }

    /**
     * send message form
     * @return array
     */
    public function send(Request $request, $id = null) {
                
        if($id == NULL && \Drupal::routeMatch()->getRouteName() == 'ek_messaging_send_broadcast'){
            $id = 'broadcast';
        }
        $build['message_form'] = $this->formBuilder->getForm('Drupal\ek_messaging\Form\Message', $id);
        $build['#attached'] = array(
            'library' => array('ek_messaging/ek_messaging'),
        );
        return $build;
    }

    /**
     * read message page
     * @return array
     */
    public function read(Request $request, $id) {
        $query = Database::getConnection('external_db', 'external_db')->select('ek_messaging', 'm');
        $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');
        $user = '%,' . \Drupal::currentUser()->id() . ',%';
        $or = $query->orConditionGroup();
        $or->condition('m.to', $user, 'like');
        $or->condition('m.from_uid', \Drupal::currentUser()->id(), '=');
        $data = $query
                ->fields('m')
                ->fields('t')
                ->condition('m.id', $id, '=')
                ->condition($or)
                ->execute();

        $message = $data->fetchObject();
        $account = \Drupal\user\Entity\User::load($message->from_uid);
        if ($account) {
            $message->from = $account->getDisplayName();
            $message->avatar = ($account->get('user_picture')->entity) ? file_create_url($account->get('user_picture')->entity->getFileUri())
                    : file_create_url(drupal_get_path('module','ek_admin') . "/art/avatar/default.jpeg");
               
        }

        $message->time = date('l jS \of F Y h:i:s A', $message->stamp);
        if ($message->priority == 3) {
            $message->color = "green";
        } elseif ($message->priority == 2) {
            $message->color = "blue";
        } else {
            $message->color = "red";
        }

        $message->delete = "<a href='#' title='" . $this->t('Delete') . "' id='" . $message->id . "' class='deleteButton' >" . $this->t('Delete') . "</a>";
        $message->archive = "<a href='#' title='" . $this->t('Archive') . "' id='" . $message->id . "' class='archiveButton' >" . $this->t('Archive') . "</a>";
        $url_inbox = Url::fromRoute('ek_messaging_inbox', array(), array())->toString();
        $message->inbox = $this->t('<a href="@url" >Go to inbox</a>', array('@url' => $url_inbox));
        $url_send = Url::fromRoute('ek_messaging_send', array(), array())->toString();
        $message->send = $this->t('<a href="@url" >New message</a>', array('@url' => $url_send));
        $url_reply = Url::fromRoute('ek_messaging_reply', array('id' => $message->id), array())->toString();
        $message->reply = $this->t('<a href="@url" >Reply</a>', array('@url' => $url_reply));


        //update read status in reader list
        $list = explode(',', $message->status);
        array_push($list, \Drupal::currentUser()->id());
        $unique = array_values(array_unique($list));
        $list = ',' . implode(',', $unique) . ',';
        Database::getConnection('external_db', 'external_db')
                ->update('ek_messaging')
                ->condition('id', $id)
                ->fields(array('status' => $list))
                ->execute();

        // when reading new message clear cache for menu link display
        \Drupal\Core\Cache\Cache::invalidateTags(['ek_message_inbox']);
        \Drupal\Core\Cache\Cache::invalidateTags(['config:system.menu.tools']);

        return array(
            '#theme' => 'ek_messaging_read',
            '#items' => $message,
            '#title' => $this->t('Message'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css', 'ek_messaging/ek_messaging'),
            ),
            '#cache' => ['tags' => ['ek_message_' . $id]],
        );
    }

    /**
     * inbox page
     * @return array
     */
    public function inbox(Request $request) {
        $links = array();
        $user = '%,' . \Drupal::currentUser()->id() . ',%';
        $build['filter_message_list'] = $this->formBuilder->getForm('Drupal\ek_messaging\Form\FilterMessages');

        if (isset($_SESSION['mefilter']['filter']) && $_SESSION['mefilter']['keyword'] != null && $_SESSION['mefilter']['keyword'] != '%') {

            //search inbox by keyword

            $key = Xss::filter($_SESSION['mefilter']['keyword']);


            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');
            $or = $query->orConditionGroup();
            $keyword = '%' . trim($key) . '%';

            $or->condition('m.subject', $keyword, 'like');
            $or->condition('t.text', $keyword, 'like');

            $keys = explode(' ', $key);

            if (count($keys) > 1) {
                //search with multiple keywords

                foreach ($keys as $k => $v) {
                    if ($v != '' && $v != '%') {
                        $keyword = '%' . trim($v) . '%';
                        $or->condition('m.subject', $keyword, 'like');
                        $or->condition('t.text', $keyword, 'like');
                    }
                }
            }

            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition($or)
                    ->condition('m.inbox', $user, 'like')
                    ->condition('m.archive', $user, 'not like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        } else {
            //query all inbox messages
            $query = Database::getConnection('external_db', 'external_db')->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');

            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition('m.inbox', $user, 'like')
                    ->condition('m.archive', $user, 'not like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        }

        $options = array();

        $i = 0;
        $uid = \Drupal::currentUser()->id();
        while ($r = $data->fetchObject()) {
            $i++;
            $account = \Drupal\user\Entity\User::load($r->from_uid);
            $from = '';
            if ($account) {
                $avatar = ($account->get('user_picture')->entity) ? $account->get('user_picture')->entity->getFileUri(): null;
                if($avatar) {
                    $from = "<div><img src='". file_create_url($avatar) ."' class='avatar'>" . " " . $account->getDisplayName() . "</div>";
                } else {
                    $avatar = file_create_url(drupal_get_path('module','ek_admin') . "/art/avatar/default.jpeg");
                    $from = "<div><img src='".$avatar."' class='avatar'>" . " " . $account->getDisplayName() . "</div>";
                }
                
            }

            $link = Url::fromRoute('ek_messaging_read', array('id' => $r->id))->toString();
            $subject = "<a title='" . $this->t('open') . "' href='" . $link . "'>" . $r->subject . "</a>";
            if ($r->priority == 3) {
                $priority = "<i class='fa fa-circle green' aria-hidden='true'></i>";
            } elseif ($r->priority == 2) {
                $priority = "<i class='fa fa-circle blue' aria-hidden='true'></i>";
            } else {
                $priority = "<i class='fa fa-circle red' aria-hidden='true'></i>";
            }
            $status = explode(',', $r->status);
            if (array_search($uid, $status)) {
                $read = null;
            } else {
                $read = 'read';
            }

            $action = "<a href='#'  title='" . $this->t('Archive') . "' id='" . $r->id . "' class='fa fa-folder archiveButton' ></a>";

            $options[$i] = [
                'data' => [
                    'date' => [
                        'data' => date('Y-m-d h:i', $r->stamp),
                        'title' => date('l', $r->stamp)
                    ],
                    'from' => [
                        'data' => ['#markup' => $from],
                        ],
                    'subject' => [
                        'data' => ['#markup' => $priority . ' ' . $subject],
                        'title' => '',
                        'class' => array($read),
                    ],
                    'action' => ['data' => ['#markup' => $action]],
                ],
                'id' => ['line' . $r->id],
            ];
        }



        $header = array(
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'inbox_date',
            ),
            'from' => array(
                'data' => $this->t('From'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'inbox_from',
            ),
            'subject' => array(
                'data' => $this->t('Subject'),
                'class' => array(),
                'id' => 'inbox_subject',
            ),
            'action' => array(
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'inbox_action',
            ),
        );
        $build['inbox_list'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'inbox_table'),
            '#empty' => $this->t('No message'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css', 'ek_messaging/ek_messaging'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
            '#weight' => 5,
        );

        return $build;
    }

    /**
     * Return an outbox page
     *
     */
    public function outbox(Request $request) {
        $links = array();
        $user = \Drupal::currentUser()->id();
        $archive = '%,' . $user . ',%';

        if (isset($_SESSION['mefilter']['filter']) && $_SESSION['mefilter']['keyword'] != null && $_SESSION['mefilter']['keyword'] != '%') {
            $key = Xss::filter($_SESSION['mefilter']['keyword']);
            $keyword1 = '%' . trim($key) . '%';

            $query = Database::getConnection('external_db', 'external_db')->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');


            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition('t.text', $keyword1, 'like')
                    ->condition('m.from_uid', $user, '=')
                    ->condition('m.archive', $archive, 'not like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        } else {
            //query all outbox messages
            $query = Database::getConnection('external_db', 'external_db')->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');

            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition('m.from_uid', $user, '=')
                    ->condition('m.archive', $archive, 'not like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        }

        $options = array();


        $i = 0;
        $user = \Drupal::currentUser()->id();
        while ($r = $data->fetchObject()) {
            $i++;
            $to = '';
            $addresses = explode(',', $r->to);
            foreach ($addresses as $uid) {
                if ($uid > 0) {
                    $account = \Drupal\user\Entity\User::load($uid);
                    if ($account) {
                        $to .= $account->getDisplayName() . ', ';
                    }
                }
            }
            $to = rtrim($to, ', ');
            $link = Url::fromRoute('ek_messaging_read', array('id' => $r->id))->toString();
            $subject = "<a title='" . $this->t('open') . "' href='" . $link . "'>" . $r->subject . "</a>";

            if ($r->priority == 3) {
                $priority = "<i class='fa fa-circle green' aria-hidden='true'></i>";
            } elseif ($r->priority == 2) {
                $priority = "<i class='fa fa-circle blue' aria-hidden='true'></i>";
            } else {
                $priority = "<i class='fa fa-circle red' aria-hidden='true'></i>";
            }
            $status = explode(',', $r->status);
            if (array_search($user, $status)) {
                $read = null;
            } else {
                $read = 'read';
            }

            $action = "<a href='#' title='" . $this->t('Archive') . "' id='" . $r->id . "' class='fa fa-folder archiveButton' ></a>";

            $options[$i] = [
                'data' => ['date' => array('data' => date('Y-m-d h:i', $r->stamp),
                        'title' => date('l', $r->stamp)
                    ),
                    'to' => $to,
                    'subject' => array(
                        'data' => ['#markup' => $priority . ' ' . $subject],
                        'title' => '',
                        'class' => array($read),
                    ),
                    'action' => ['data' => ['#markup' => $action]],
                ],
                'id' => ['line' . $r->id],
            ];
        }

        if (isset($_SESSION['mefilter']['filter']) && $_SESSION['mefilter']['filter'] == 1) {
            $build['alert'] = ['#markup' => '<span class="alert">' . $this->t('Filtered display') . '</span>',];
        }

        $header = array(
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'outbox_date',
            ),
            'to' => array(
                'data' => $this->t('To'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'outbox_to',
            ),
            'subject' => array(
                'data' => $this->t('Subject'),
                'class' => array(),
                'id' => 'outbox_subject',
            ),
            'action' => array(
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'outbox_action',
            ),
        );
        $build['outbox_list'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'outbox_table'),
            '#empty' => $this->t('No message'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css', 'ek_messaging/ek_messaging'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
            '#weight' => 5,
        );

        return $build;
    }

    /**
     * Return an archives page
     *
     */
    public function archives(Request $request) {
        $links = array();
        $user = \Drupal::currentUser()->id();
        $archive = '%,' . $user . ',%';

        if (isset($_SESSION['mefilter']['filter']) && $_SESSION['mefilter']['keyword'] != null && $_SESSION['mefilter']['keyword'] != '%') {
            $key = Xss::filter($_SESSION['mefilter']['keyword']);
            $keyword1 = '%' . trim($key) . '%';

            $query = Database::getConnection('external_db', 'external_db')->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');
            $or = $query->orConditionGroup();
            $or->condition('m.to', $archive, 'like');
            $or->condition('m.from_uid', $user, 'like');

            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition('t.text', $keyword1, 'like')
                    ->condition($or)
                    ->condition('m.archive', $archive, 'like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        } else {
            //query all archives
            $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_messaging', 'm');
            $query->leftJoin('ek_messaging_text', 't', 't.id=m.id');
            $or = $query->orConditionGroup();
            $or->condition('m.to', $archive, 'like');
            $or->condition('m.from_uid', $user, 'like');


            $data = $query
                    ->fields('m')
                    ->fields('t')
                    ->condition($or)
                    ->condition('m.archive', $archive, 'like')
                    ->extend('Drupal\Core\Database\Query\TableSortExtender')
                    ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
                    ->limit(20)->orderBy('m.id', 'DESC')
                    ->execute();
        }

        $options = array();


        $i = 0;
        $user = \Drupal::currentUser()->id();
        while ($r = $data->fetchObject()) {
            $i++;
            $to = '';
            $addresses = explode(',', $r->to);
            foreach ($addresses as $uid) {
                if ($uid > 0) {
                    $account = \Drupal\user\Entity\User::load($uid);
                    if ($account) {
                        $to .= $account->getDisplayName() . ', ';
                    }
                }
            }
            $account = \Drupal\user\Entity\User::load($r->from_uid);
            $from = '';
            if ($account) {
                $from = $account->getDisplayName();
            }
            $to = rtrim($to, ', ');
            $link = Url::fromRoute('ek_messaging_read', array('id' => $r->id))->toString();
            $subject = "<a title='" . $this->t('open') . "' href='" . $link . "'>" . $r->subject . "</a>";

            if ($r->priority == 3) {
                $priority = "<i class='fa fa-circle green' aria-hidden='true'></i>";
            } elseif ($r->priority == 2) {
                $priority = "<i class='fa fa-circle blue' aria-hidden='true'></i>";
            } else {
                $priority = "<i class='fa fa-circle red' aria-hidden='true'></i>";
            }

            $status = explode(',', $r->status);
            if (array_search($user, $status)) {
                $read = null;
            } else {
                $read = 'read';
            }
            $action = "<a href='#' title='" . $this->t('Delete') . "' id='" . $r->id . "' class='fa fa-times red deleteButton' ></a>";

            $options[$i] = [
                'data' => [
                    'date' => array('data' => date('Y-m-d h:i', $r->stamp),
                        'title' => date('l', $r->stamp)
                    ),
                    'to' => $to,
                    'from' => $from,
                    'subject' => array(
                        'data' => ['#markup' => $priority . ' ' . $subject],
                        'title' => '',
                        'class' => array($read),
                    ),
                    'action' => ['data' => ['#markup' => $action]],
                ],
                'id' => ['line' . $r->id],
            ];
        }


        if (isset($_SESSION['mefilter']['filter']) && $_SESSION['mefilter']['filter'] == 1) {
            $build['alert'] = ['#markup' => '<span class="alert">' . $this->t('Filtered display') . '</span>',];
        }

        $header = array(
            'date' => array(
                'data' => $this->t('Date'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'archive_date',
            ),
            'to' => array(
                'data' => $this->t('To'),
                'class' => array(RESPONSIVE_PRIORITY_LOW),
                'id' => 'archive_to',
            ),
            'from' => array(
                'data' => $this->t('From'),
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'archive_from',
            ),
            'subject' => array(
                'data' => $this->t('Subject'),
                'class' => array(),
                'id' => 'archive_subject',
            ),
            'action' => array(
                'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
                'id' => 'archive_action',
            ),
        );
        $build['archive_list'] = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $options,
            '#attributes' => array('id' => 'archive_table'),
            '#empty' => $this->t('No message'),
            '#attached' => array(
                'library' => array('ek_admin/ek_admin_css', 'ek_messaging/ek_messaging'),
            ),
        );

        $build['pager'] = array(
            '#type' => 'pager',
            '#weight' => 5,
        );

        return $build;
    }

    /**
     * delete a message
     * @return
     * @param request int id message id
     * @return array json message id if archived, 0 if error
     *
     * @TODO when user delete archived message sent by user (Outbox->archive->delete),
     * message is displaye again in archived
     */
    public function delete(Request $request) {
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_messaging', 'm');
        $user = '%,' . \Drupal::currentUser()->id() . ',%';

        //filter condition to validate message is to or from uid
        $or = $query->orConditionGroup();
        $or->condition('m.to', $user, 'like');
        $or->condition('m.from_uid', \Drupal::currentUser()->id(), '=');

        $data = $query
                ->fields('m')
                ->condition($or)
                ->condition('id', $request->get('id'))
                ->execute();
        $message = $data->fetchObject();

        //update lists
        $user = ',' . \Drupal::currentUser()->id() . ',';
        $to = str_replace($user, ',', $message->to);
        $inbox = str_replace($user, ',', $message->inbox);
        $status = str_replace($user, ',', $message->status);
        $archive = str_replace($user, ',', $message->archive);
        $update = Database::getConnection('external_db', 'external_db')
                ->update('ek_messaging')
                ->condition('id', $message->id)
                ->fields(array('ek_messaging.to' => $to, 'inbox' => $inbox, 'status' => $status, 'archive' => $archive))
                ->execute();

        if ($update) {
            return new JsonResponse(['id' => $message->id]);
        }
    }

    /**
     * archive a message
     * @param request int id message id
     * @return array json message id if archived, 0 if error
     *
     */
    public function doarchive(Request $request) {
        $uid = \Drupal::currentUser()->id();
        $query = Database::getConnection('external_db', 'external_db')
                ->select('ek_messaging', 'm');
        $user = '%,' . $uid . ',%';

        //filter condition to validate message is to or from uid
        $or = $query->orConditionGroup();
        $or->condition('m.to', $user, 'like');
        $or->condition('m.from_uid', $uid, '=');

        $data = $query
                ->fields('m')
                ->condition($or)
                ->condition('id', $request->get('id'))
                ->execute();
        $message = $data->fetchObject();
        if ($message) {
            //update archive list
            if ($message->archive != '') {
                $list = explode(',', $message->archive);
            } else {
                $list = [];
            }
            array_push($list, \Drupal::currentUser()->id());
            $unique = array_values(array_unique($list));
            $list = ',' . implode(',', $unique) . ',';
            $update = Database::getConnection('external_db', 'external_db')
                    ->update('ek_messaging')
                    ->condition('id', $message->id)
                    ->fields(array('archive' => $list))
                    ->execute();

            if ($update) {
                return new JsonResponse(['id' => $message->id]);
            }
        } else {
            return new JsonResponse(['id' => 0]);
        }
    }

    /**
     * Return ajax user autocomplete data
     * Deprecated : use default ek_admin resources userAutocomplete
     */
    public function autocomplete(Request $request) {
        /*
          $text = $request->query->get('term');
          $name = array();

          $query = "SELECT distinct name from {users_field_data} WHERE mail like :t1 or name like :t2 ";
          $a = array(':t1' => "$text%", ':t2' => "$text%");
          //$name = db_query($query, $a)->fetchCol();

          return new JsonResponse($name);

         */
    }

}
