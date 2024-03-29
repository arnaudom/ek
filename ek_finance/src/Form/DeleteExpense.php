<?php

/**
 * @file
 * Contains \Drupal\ek_finance\Form\DeleteExpense.
 */

namespace Drupal\ek_finance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to delete expense entry.
 */
class DeleteExpense extends FormBase {

    /**
     * The module handler.
     *
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
        return 'ek_hr_delete_expense';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $id = null, $j = null) {
        $query = "SELECT * from {ek_expenses} where id=:id";
        $data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();

        $form['edit_expense'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Expense ref. @p', array('@p' => $id)),
        );
        $form['edit_expense2'] = array(
            '#type' => 'item',
            '#markup' => $this->t('Description : @p', array('@p' => $data->comment)),
        );

        if ($j == '0') {
            $form['for_id'] = array(
                '#type' => 'hidden',
                '#value' => $id,
            );

            $form['coid'] = array(
                '#type' => 'hidden',
                '#value' => $data->company,
            );

            $form['attachment'] = array(
                '#type' => 'hidden',
                '#value' => $data->attachment,
            );

            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('Are you sure you want to delete this entry ?'),
            );

            if ($data->attachment <> '') {
                $parts = explode('/', $data->attachment);
                $file = array_reverse($parts);
                $link = "<a href='" . \Drupal::service('file_url_generator')->generateAbsoluteString($data->attachment) . "' target='_blank'>" . $file[0] . "</a>";

                $form['alert2'] = array(
                    '#type' => 'item',
                    '#markup' => $this->t('This record has an attachment : ' . $link . ''),
                );
            }

            $form['actions'] = array(
                '#type' => 'actions',
                '#attributes' => array('class' => array('container-inline')),
            );
            $form['actions']['record'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete'),
            );

            $form['actions']['cancel'] = array(
                '#type' => 'item',
                '#markup' => $this->t('<a href="@url" >Cancel</a>', array('@url' => Url::fromRoute('ek_finance.manage.list_expense', array(), array())->toString())),
            );
        } else {
            $form['alert'] = array(
                '#type' => 'item',
                '#markup' => $this->t('This entry cannot be deleted because it has been reconciled.'),
            );
        }
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
        $delete = Database::getConnection('external_db', 'external_db')
                        ->delete('ek_expenses')->condition('id', $form_state->getValue('for_id'))->execute();

        if ($form_state->getValue('attachment') != null && file_exists($form_state->getValue('attachment'))) {
            \Drupal::service('file_system')->delete($form_state->getValue('attachment'));
        }

        $journal = new \Drupal\ek_finance\Journal();
        $journalId = $journal->delete('expense%', $form_state->getValue('for_id'), $form_state->getValue('coid'));
        //count field sequence must be restored
        $journal->resetCount($form_state->getValue('coid'), $journalId[1]);

        if ($this->moduleHandler->moduleExists('ek_assets')) {
            $query = "SELECT id,amort_record from {ek_assets} a INNER JOIN {ek_assets_amortization} b "
                    . "ON a.id = b.asid WHERE amort_record <> :r ORDER by id";

            $data = Database::getConnection('external_db', 'external_db')
                    ->query($query, [':r' => '']);


            while ($r = $data->fetchObject()) {
                $schedule = unserialize($r->amort_record);
                $i = 0;
                foreach ($schedule['a'] as $key => $value) {
                    if ($value['journal_reference'] != '') {
                        if ($value['journal_reference']['expense'] == $form_state->getValue('for_id')) {
                            $schedule['a'][$key]['journal_reference'] = '';
                            Database::getConnection('external_db', 'external_db')
                                    ->update('ek_assets_amortization')
                                    ->condition('asid', $r->id)
                                    ->fields(['amort_record' => serialize($schedule), 'amort_status' => 0])
                                    ->execute();
                            $url = Url::fromRoute('ek_assets.set_amortization', ['id' => $r->id])->toString();
                            \Drupal::messenger()->addWarning(t("An amortization record was updated for asset id <a href='@url'>@id</a>", ['@url' => $url, '@id' => $r->id]));
                        }
                    }
                }
            }
        }

        if ($delete) {
            \Drupal::messenger()->addStatus(t('The entry has been deleted'));
            \Drupal\Core\Cache\Cache::invalidateTags(['reporting']);
            $form_state->setRedirect("ek_finance.manage.list_expense");
        }
    }

}
