<?php

/**
 * @file
 * Contains \Drupal\ek_messaging\Form\EncryptionSettingsForm.
 */

namespace Drupal\ek_messaging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ek_messaging\Service\MessageEncryptionService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administration form for ek_messaging encryption settings.
 *
 * Path: /ek_messaging/admin/settings  (add route – see notes in routing patch).
 *
 * Key management recommendations shown to the administrator:
 */
class EncryptionSettingsForm extends ConfigFormBase {

    /** @var \Drupal\ek_messaging\Service\MessageEncryptionService */
    protected $encryption;

    public static function create(ContainerInterface $container) {
        $instance = parent::create($container);
        $instance->encryption = $container->get('ek_messaging.encryption');
        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array {
        return ['ek_messaging.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'ek_messaging_encryption_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array {
        //$config = $this->config('ek_messaging.settings');
        //$keyIsSet = !empty($config->get('encryption_key'));
        //$overridden = $this->configFactory()
        //    ->get('ek_messaging.settings')
        //    ->hasOverrides('encryption_key');
        $keyRepo = \Drupal::service('key.repository');
        $keyEntity = $keyRepo->getKey('ek_messaging_key');
        $keyIsSet = ($keyEntity === null) ? NULL : TRUE;

        $form['encryption'] = [
            '#type'  => 'details',
            '#title' => $this->t('Message body encryption'),
            '#open'  => true,
        ];

        $form['encryption']['info'] = [
            '#type'   => 'item',
            '#markup' => $this->t(
                '<p>Messages are encrypted at rest using <strong>AES-256-GCM</strong>. '
                . 'A unique IV is generated per message; the authentication tag is stored '
                . 'together with the ciphertext.</p>'
                . '<p><strong>Recommended production setup:</strong> store the key in a '
                . 'file <br><code>$ openssl rand -base64 32 > ek_messaging.key</code>'
                . '<br><code>$ chmod 600 /path/to/ek_messaging.key</code>'
                . '<br><code>$ chown www-data:www-data /path/to/ek_messaging.key</code><br>'
                . 'Then use Drupal key module to register the key file.</p>'
                . 'This form dos not save the key but can generate a base64 string you can copy it to create your key file.'
            ),
        ];



        // Show masked current key status.
        $form['encryption']['key_status'] = [
            '#type'   => 'item',
            '#title'  => $this->t('Current key status'),
            '#markup' => $keyIsSet
                ? '<span style="color:green">&#10003; ' . $this->t('Key is configured') . '</span>'
                : '<span style="color:red">&#10005; ' . $this->t('No key - messages stored in plaintext') . '</span>',
        ];

        $form['encryption']['generate'] = [
            '#type'   => 'button',
            '#value'  => $this->t('Generate new key'),
            '#ajax'   => [
                'callback' => '::ajaxGenerateKey',
                'wrapper'  => 'generated-key-wrapper',
                'effect'   => 'fade',
            ],
        ];

        $form['encryption']['generated_key'] = [
            '#type'       => 'item',
            '#prefix'     => '<div id="generated-key-wrapper">',
            '#suffix'     => '</div>',
            '#markup'     => $form_state->get('generated_key')
                ? '<code>' . $form_state->get('generated_key') . '</code>'
                : '',
        ];

        $form['migration'] = [
            '#type'  => 'details',
            '#title' => $this->t('Database migration'),
            '#open'  => false,
        ];

        $form['migration']['info'] = [
            '#type'   => 'item',
            '#markup' => $this->t(
                'After saving a new key, run the batch migration below to encrypt '
                . 'all existing plaintext messages. This operation is safe to re-run '
                . '- already-encrypted rows are detected and skipped automatically. '
                . '<br>Alternatively run via Drush: <code>drush ek-messaging-encrypt</code>'
            ),
        ];

        $form['migration']['run_migration'] = [
            '#type'  => 'submit',
            '#value' => $this->t('Encrypt existing messages now'),
            '#submit' => ['::runMigration'],
            '#disabled' => !$keyIsSet,
        ];

        return $form;
    }

    /**
     * AJAX callback – generates a fresh key and puts it in the form.
     */
    public function ajaxGenerateKey(array &$form, FormStateInterface $form_state): array {
        $key = MessageEncryptionService::generateKey();
        $form_state->set('generated_key', $key);
        $form['encryption']['generated_key']['#markup'] =
            '<strong>' . $this->t('Copy this key now - it will not be shown again:') . '</strong><br>'
            . '<code>' . $key . '</code>';
        return $form['encryption']['generated_key'];
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void {
        $key = trim($form_state->getValue('encryption_key'));
        if ($key !== '') {
            $raw = base64_decode($key, true);
            if ($raw === false || strlen($raw) !== 32) {
                $form_state->setErrorByName(
                    'encryption_key',
                    $this->t('Invalid key: must be exactly 32 raw bytes encoded as base64 (44 characters).')
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void {
        
    }

    /**
     * Submit handler for the "Encrypt existing messages" button.
     */
    public function runMigration(array &$form, FormStateInterface $form_state): void {
        $batch = [
            'title'      => $this->t('Encrypting existing messages'),
            'operations' => [
                ['\Drupal\ek_messaging\Update\EncryptExistingMessages::batchProcess', []],
            ],
            'finished'   => '\Drupal\ek_messaging\Update\EncryptExistingMessages::batchFinished',
        ];
        batch_set($batch);
    }
}