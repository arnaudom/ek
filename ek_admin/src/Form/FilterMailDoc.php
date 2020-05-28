<?php

/**
 * @file
 * Contains \Drupal\ek_admin\Form\FilterMailDoc.
 */

namespace Drupal\ek_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a form to filter print docs.
 */
class FilterMailDoc extends FormBase {

    /**
     * The module handler.
     * @var \Drupal\Core\Extension\ModuleHandler
     */
    protected $moduleHandler;

    /**
     * @param \Drupal\Core\Extension\ModuleHandler $module_handler
     *   The module handler.
     */
    public function __construct(ModuleHandler $module_handler) {
        $this->moduleHandler = $module_handler;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('module_handler')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_admin_doc_mail_filter';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $param = null) {
        $param = unserialize($param);

        $form['source'] = array(
            '#type' => 'hidden',
            '#value' => $param[1],
        );

        //insert the create file mode into param
        array_push($param, '1');
        $nparam = serialize($param);

        $form['param'] = array(
            '#type' => 'hidden',
            '#value' => $nparam,
        );

        $form['maildoc'] = array(
            '#type' => 'details',
            '#title' => $this->t('Share this document via email'),
            '#open' => isset($param['open']) ? $param['open'] : false,
            '#attributes' => array('class' => ''),
        );

        if ($this->moduleHandler->moduleExists('ek_address_book')) {
            $form['maildoc']['email'] = array(
                '#type' => 'textarea',
                '#rows' => 2,
                '#id' => 'edit-email',
                //'#required' => TRUE,
                '#attributes' => array('placeholder' => $this->t('enter email addresses separated by comma (autocomplete enabled).')),
                '#attached' => array(
                    'library' => array(
                        'ek_address_book/ek_address_book.email_autocomplete'
                    ),
                ),
            );
        } else {
            $form['maildoc']['email'] = array(
                '#type' => 'textarea',
                '#rows' => 2,
                //'#required' => TRUE,
                '#attributes' => array('placeholder' => $this->t('enter email addresses separated by comma.')),
            );
        }
        $form['maildoc']['copy'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Send me a copy'),
            '#default_value' => 1,
        ];

        $form['maildoc']['message'] = array(
            '#type' => 'textarea',
            '#rows' => 2,
            '#attributes' => array('placeholder' => $this->t('add optional message')),
        );

        $form['maildoc']['actions'] = array('#type' => 'actions');
        $form['maildoc']['actions']['send'] = array(
            '#id' => 'sharebuttonid',
            '#type' => 'button',
            '#value' => $this->t('Send'),
            //'#limit_validation_errors' => array(),
            '#ajax' => array(
                'callback' => array($this, 'ProcessMail'),
                'wrapper' => 'SendMessage',
            ),
        );

        $form['maildoc']['alert'] = array(
            '#type' => 'item',
            '#markup' => '',
            '#prefix' => "<div id='SendMessage'>",
            '#suffix' => '</div>',
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        // Nothing to submit.
    }

    /**
     * process mail
     */
    public function ProcessMail(array &$form, FormStateInterface $form_state) {

        /*
         * filter emails
         */

        // clear the session data for filter to avoid errors in other modules
        // $_SESSION['printfilter']= array();

        $recipients = array();
        if ($form_state->getValue('email') == '') {
            $form['maildoc']['alert']['#prefix'] = "<div id='SendMessage' class='messages messages--error'>";
            $form['maildoc']['alert']['#markup'] = $this->t('There is no email address.');

            return $form['maildoc']['alert'];
        } else {
            $addresses = explode(',', $form_state->getValue('email'));
            $error = '';
            foreach ($addresses as $email) {
                if ($email <> ' ') {
                    $email = trim($email);

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error .= $email . ' ';
                    } else {
                        array_push($recipients, $email);
                    }
                }
            }

            if ($error != '') {
                $form['maildoc']['alert']['#prefix'] = "<div id='SendMessage' class='messages messages--error'>";
                $form['maildoc']['alert']['#markup'] = $this->t('Address(es) not valid: @a', array('@a' => $error));

                return $form['maildoc']['alert'];
            } else {
                //proceed with mailing
                $param = $form_state->getValue('param');

                switch ($form_state->getValue('source')) {
                    //generate the pdf file and save in tmp dir
                    case 'expenses_memo':
                        include_once drupal_get_path('module', 'ek_finance') . '/manage_pdf_output.inc';
                        $file = \Drupal::config('system.file')->get('path.temporary') . "/" . str_replace("/", "_", $head->serial) . ".pdf";
                        $options['user'] = \Drupal::currentUser()->getAccountName();
                        $options['filename'] = str_replace("/", "_", $head->serial) . ".pdf";
                        $options['origin'] = 'memo';
                        break;

                    case 'invoice':
                    case 'purchase':
                    case 'quotation':

                        include_once drupal_get_path('module', 'ek_sales') . '/manage_print_output.inc';

                        $file = \Drupal::config('system.file')->get('path.temporary') . "/" . str_replace("/", "_", $head->serial) . ".pdf";
                        $options['user'] = \Drupal::currentUser()->getAccountName();
                        $options['filename'] = str_replace("/", "_", $head->serial) . ".pdf";
                        $options['origin'] = 'sales';
                        if (!null == $form_state->getValue('stripe_add') && $form_state->getValue('stripe_add') == 1) {
                            $options['stripe'] = 1;
                            $options['checkout'] = $form_state->getValue('checkout');
                        }
                        break;

                    case 'delivery':
                    case 'logi_delivery':
                    case 'receiving':
                    case 'logi_receiving':
                        include_once drupal_get_path('module', 'ek_logistics') . '/manage_pdf_output.inc';
                        $file = \Drupal::config('system.file')->get('path.temporary') . "/" . str_replace("/", "_", $head->serial) . ".pdf";

                        $options['user'] = \Drupal::currentUser()->getAccountName();
                        $options['filename'] = str_replace("/", "_", $head->serial) . ".pdf";
                        $options['origin'] = 'logistics';
                        break;

                    case 'project_documents':
                        // get the file
                        $ids = unserialize($param);
                        $query = 'SELECT pcode,filename,uri FROM {ek_project_documents} WHERE id=:id';
                        $data = Database::getConnection('external_db', 'external_db')
                                ->query($query, array(':id' => $ids[0]))
                                ->fetchObject();
                        $file = $data->uri;
                        $options['user'] = \Drupal::currentUser()->getAccountName();
                        $options['filename'] = $data->filename;
                        $options['origin'] = 'project_documents';
                        $options['pcode'] = $data->pcode;
                        break;
                }

                $options['copy'] = $form_state->getValue('copy');
                $message = $form_state->getValue('message');
                $send = mail_attachment($recipients, $file, $message, $options);


                if ($send['error'] == '') {
                    $form['maildoc']['alert']['#prefix'] = "<div id='SendMessage' class='messages messages--status'>";
                    $t = rtrim($send['send'], ',');
                    $form['maildoc']['alert']['#markup'] = $this->t('Document sent to @t.', array('@t' => $t));
                } else {
                    $form['maildoc']['alert']['#prefix'] = "<div id='SendMessage' class='messages messages--error'>";
                    $t = rtrim($send['send'], ',');
                    $form['maildoc']['alert']['#markup'] = $this->t('Document not sent to @t', array('@t' => $t));
                }
                if ($form_state->getValue('source') != 'project_documents') {
                    unlink($file);
                }
                return $form['maildoc']['alert'];
            }
        }

        return $form['maildoc']['alert'];
    }

}
