<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\BankForm.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ek_admin\Access\AccessCheck;
use Drupal\ek_finance\BankData;

/**
 * Provides a form to create and edit bank reference.
 */
class BankForm extends FormBase {

    /**
     * The country manager.
     *
     * @var \Drupal\Core\Locale\CountryManagerInterface
     */
    protected $countryManager;

    public function __construct(CountryManagerInterface $country_manager) {
        $this->countryManager = $country_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('country_manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'ek_finance_bank';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null) {
        if ($id != null || $form_state->get('bid') != null) {

            if ($id == null) {
                $id = $form_state->getValue('bid');
            }
            $form_state->set('step', 2);

            if ($id > 0) {
                $query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_bank','b')
                    ->fields('b')
                    ->condition('id', $id)
                    ->execute();
                $data = $query->fetchObject();
            }

            $form['bid'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );
        } elseif ($form_state->get('step') == '') {
            $form_state->set('step', 1);
            $options = BankData::listBank();
            $options['0'] = $this->t('create a new bank');

            $form['bid'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => $options,
                '#title' => $this->t('Bank'),
                '#required' => true,
                '#prefix' => "<div class='container-inline'>",
            ];

            $form['next'] = [
                '#type' => 'submit',
                '#value' => $this->t('Next'),
                '#limit_validation_errors' => [['bid']],
                '#submit' => [[$this, 'get_accounts']],
                '#states' => [
                    'invisible' => [
                        "select[name='bid']" => ['value' => ''],
                    ],
                ],
                '#suffix' => '</div>',
            ];
        }

        if ($form_state->get('step') == 2) {
            $back = Url::fromRoute('ek_finance.manage.bank_manage', [], [])->toString();
            $table = Url::fromRoute('ek_finance.manage.bank_list', [], [])->toString();

            $form_state->set('step', 3);

            $form["back"] = [
                '#markup' => "<a href='" . $back . "' >" . $this->t('select another bank') . "</a> " . $this->t('or') . '  ',
                '#prefix' => '<div class="container-inline">',
            ];
            $form["list"] = [
                '#markup' => "<a href='" . $table . "' >" . $this->t('view list') . "</a>",
                '#suffix' => '</div>'
            ];

            $form["name"] = [
                '#type' => 'textfield',
                '#size' => 40,
                '#default_value' => $data->name,
                '#maxlength' => 100,
                '#description' => '',
                '#attributes' => ['placeholder' => $this->t('bank name')],
            ];

            $form["address1"] = [
                '#type' => 'textfield',
                '#size' => 40,
                '#default_value' => $data->address1,
                '#maxlength' => 255,
                '#description' => '',
                '#attributes' => ['placeholder' => $this->t('address line 1')],
            ];

            $form["address2"] = [
                '#type' => 'textfield',
                '#size' => 40,
                '#default_value' => $data->address2,
                '#maxlength' => 255,
                '#description' => '',
                '#attributes' => ['placeholder' => $this->t('address line 2')],
            ];

            $form["postcode"] = [
                '#type' => 'textfield',
                '#size' => 10,
                '#default_value' => $data->postcode,
                '#maxlength' => 30,
                '#description' => '',
                '#attributes' => ['placeholder' => $this->t('postcode')],
            ];


            $countries = $this->countryManager->getList();
            $form['country'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => array_combine($countries, $countries),
                '#required' => true,
                '#default_value' => isset($data->country) ? $data->country : null,
            ];

            $form["contact"] = [
                '#type' => 'textfield',
                '#size' => 20,
                '#default_value' => $data->contact,
                '#maxlength' => 30,
                '#description' => $this->t('contact name'),
                '#attributes' => ['placeholder' => $this->t('contact')],
            ];

            $form['telephone'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->telephone) ? $data->telephone : null,
                '#attributes' => ['placeholder' => $this->t('telephone')],
            ];

            $form['fax'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->fax) ? $data->fax : null,
                '#attributes' => ['placeholder' => $this->t('fax')],
            ];

            $form['email'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 160,
                '#default_value' => isset($data->email) ? $data->email : null,
                '#attributes' => ['placeholder' => $this->t('email')],
            ];

            $form['account1'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->account1) ? $data->account1 : null,
                '#attributes' => ['placeholder' => $this->t('account 1')],
                '#description' => $this->t('account no.'),
            ];

            $form['account2'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->account2) ? $data->account2 : null,
                '#attributes' => ['placeholder' => $this->t('account 2')],
                '#description' => $this->t('account no.'),
            ];

            $form['bank_code'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->bank_code) ? $data->bank_code : null,
                '#attributes' => ['placeholder' => $this->t('bank code')],
                '#description' => $this->t('bank code'),
            ];

            $form['swift'] = [
                '#type' => 'textfield',
                '#size' => 25,
                '#maxlength' => 20,
                '#default_value' => isset($data->swift) ? $data->swift : null,
                '#attributes' => ['placeholder' => $this->t('swift code')],
                '#description' => $this->t('swift, BIC'),
            ];

            $form['coid'] = [
                '#type' => 'select',
                '#size' => 1,
                '#options' => AccessCheck::CompanyListByUid(),
                '#default_value' => isset($data->coid) ? $data->coid : null,
                '#title' => $this->t('Company reference'),
                '#required' => true,
            ];

            $form['actions'] = [
                '#type' => 'actions',
                '#attributes' => ['class' => ['container-inline']],
            ];
            $form['actions']['save'] = [
                '#type' => 'submit',
                '#value' => $this->t('Save'),
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 1) {
            $form_state->set('step', 2);
            $form_state->set('bid', $form_state->getValue('bid'));
            $form_state->setRebuild();
        }

        if ($form_state->get('step') == 3) {
            if ($form_state->getValue('email') != null) {
                if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
                    $form_state->setErrorByName('email', $this->t('Invalid email'));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('step') == 3) {
            $fields = [
                'name' => Xss::filter($form_state->getValue('name')),
                'address1' => Xss::filter($form_state->getValue('address1')),
                'address2' => Xss::filter($form_state->getValue('address2')),
                'postcode' => Xss::filter($form_state->getValue('postcode')),
                'country' => $form_state->getValue('country'),
                'telephone' => Xss::filter($form_state->getValue('telephone')),
                'fax' => Xss::filter($form_state->getValue('fax')),
                'email' => $form_state->getValue('email'),
                'contact' => Xss::filter($form_state->getValue('contact')),
                'account1' => Xss::filter($form_state->getValue('account1')),
                'account2' => Xss::filter($form_state->getValue('account2')),
                'bank_code' => Xss::filter($form_state->getValue('bank_code')),
                'swift' => Xss::filter($form_state->getValue('swift')),
                'coid' => $form_state->getValue('coid'),
            ];


            if ($form_state->getValue('bid') == 0) {
                $insert = Database::getConnection('external_db', 'external_db')->insert('ek_bank')->fields($fields)->execute();
            } else {
                //update existing
                $update = Database::getConnection('external_db', 'external_db')->update('ek_bank')
                        ->condition('id', $form_state->getValue('bid'))
                        ->fields($fields)
                        ->execute();
            }

            \Drupal\Core\Cache\Cache::invalidateTags(['bank_list']);
            \Drupal::messenger()->addStatus(t('Bank data recorded'));
        }
    }

}
