<?php

namespace Drupal\ek_admin\Service;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ek_admin\GlobalSettings;

/**
 * Service for handling task notifications across modules.
 */
class TaskNotificationService {

  use StringTranslationTrait;

  /**
   * Notification bitmask constants.
   */
  const NOTIFY_NEVER = 0;
  const NOTIFY_DAILY = 1;           // 2^0
  const NOTIFY_WEEKLY = 2;          // 2^1
  const NOTIFY_MONTHLY = 4;         // 2^2
  const NOTIFY_5_DAYS_BEFORE = 8;   // 2^3
  const NOTIFY_3_DAYS_BEFORE = 16;  // 2^4
  const NOTIFY_1_DAY_BEFORE = 32;   // 2^5
  const NOTIFY_ON_OVERDUE = 64;     // 2^6
  const NOTIFY_ON_COMPLETE = 128;   // 2^7

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Current timestamp.
   *
   * @var int
   */
  protected $currentStamp;

  /**
   * Current day of week.
   *
   * @var string
   */
  protected $weekday;

  /**
   * Current day of month.
   *
   * @var int
   */
  protected $dayOfMonth;

  /**
   * Processing statistics.
   *
   * @var array
   */
  protected $stats;

  /**
   * Mail queue items to be processed.
   *
   * @var array
   */
  protected $mailQueue;

  /**
   * Constructs a TaskNotificationService object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler
  ) {
    $this->database = Database::getConnection('external_db', 'external_db');
    $this->logger = $logger_factory->get('ek_admin');
    $this->moduleHandler = $module_handler;
    $this->stats = [];
    $this->mailQueue = [];
  }

  /**
   * Returns the notification options for forms.
   *
   * @return array
   *   Array of notification options with bitmask values as keys.
   */
  public static function getNotificationOptions(): array {
    return [
      self::NOTIFY_DAILY => t('Daily reminder'),
      self::NOTIFY_WEEKLY => t('Weekly reminder (Mondays)'),
      self::NOTIFY_MONTHLY => t('Monthly reminder (1st of month)'),
      self::NOTIFY_5_DAYS_BEFORE => t('5 days before deadline'),
      self::NOTIFY_3_DAYS_BEFORE => t('3 days before deadline'),
      self::NOTIFY_1_DAY_BEFORE => t('1 day before deadline'),
      self::NOTIFY_ON_OVERDUE => t('When task becomes overdue'),
      self::NOTIFY_ON_COMPLETE => t('When task is completed'),
    ];
  }

  /**
   * Process all task notifications.
   *
   * @param \Drupal\ek_admin\GlobalSettings $settings
   *   The global settings object.
   *
   * @return array
   *   Statistics about processed notifications.
   */
  public function processAllNotifications(GlobalSettings $settings): array {
    $this->currentStamp = time();
    $this->weekday = date('D');
    $this->dayOfMonth = (int) date('j');
    $this->mailQueue = [];
    $this->stats = [
      'start_time' => date('Y-m-d H:i:s', $this->currentStamp),
      'modules' => [],
    ];

    $this->logger->notice('Starting task notification processing at: @time', [
      '@time' => $this->stats['start_time'],
    ]);

    // Process sales module tasks
    if ($this->moduleHandler->moduleExists('ek_sales')) {
      if ($settings->get('sale_tasks') == 1) {
        $this->stats['modules']['sales_invoice'] = $this->processSalesInvoiceTasks();
      }
      if ($settings->get('purchase_tasks') == 1) {
        $this->stats['modules']['sales_purchase'] = $this->processSalesPurchaseTasks();
      }
    }

    // Process projects module tasks
    if ($this->moduleHandler->moduleExists('ek_projects')) {
      if ($settings->get('project_tasks') == 1) {
        $this->stats['modules']['projects'] = $this->processProjectTasks();
      }
    }

    $this->stats['end_time'] = date('Y-m-d H:i:s');
    $this->stats['total_queued'] = count($this->mailQueue);

    $this->logger->notice('Task notification completed. Stats: @stats', [
      '@stats' => json_encode($this->stats),
    ]);

    return $this->stats;
  }

  /**
   * Get collected mail items for queue processing.
   *
   * @return array
   *   Array of mail parameters to be sent.
   */
  public function getMailQueue(): array {
    return $this->mailQueue;
  }

  /**
   * Process sales invoice tasks.
   *
   * @return array
   *   Statistics for this task type.
   */
  protected function processSalesInvoiceTasks(): array {
    $taskStats = ['processed' => 0, 'notified' => 0, 'skipped' => 0, 'errors' => 0];

    try {
      $query = $this->database->select('ek_sales_invoice_tasks', 't');
      $query->fields('t');
      $query->leftJoin('ek_sales_invoice', 'i', 't.serial = i.serial');
      $query->addField('i', 'id', 'invoice_id');
      $query->condition('t.notify', 0, '<>');
      $query->condition('t.completion_rate', 100, '<');
      
      $results = $query->execute();

      while ($row = $results->fetchObject()) {
        $taskStats['processed']++;
        try {
          $end = $this->parseTimestamp($row->end);
          $notificationTypes = $this->determineNotifications($row, $end);
          
          if (!empty($notificationTypes)) {
            $params = $this->buildSalesInvoiceParams($row, $end, $notificationTypes);
            $this->queueNotifications($row, $params);
            $this->updateLastNotified('ek_sales_invoice_tasks', $row->id);
            $taskStats['notified']++;
          } else {
            $taskStats['skipped']++;
          }
        }
        catch (\Exception $e) {
          $taskStats['errors']++;
          $this->logger->error('Error processing invoice task @id: @message', [
            '@id' => $row->id,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error querying invoice tasks: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $taskStats;
  }

  /**
   * Process sales purchase tasks.
   *
   * @return array
   *   Statistics for this task type.
   */
  protected function processSalesPurchaseTasks(): array {
    $taskStats = ['processed' => 0, 'notified' => 0, 'skipped' => 0, 'errors' => 0];

    try {
      $query = $this->database->select('ek_sales_purchase_tasks', 't');
      $query->fields('t');
      $query->leftJoin('ek_sales_purchase', 'p', 't.serial = p.serial');
      $query->addField('p', 'id', 'purchase_id');
      $query->condition('t.notify', 0, '<>');
      $query->condition('t.completion_rate', 100, '<');
      
      $results = $query->execute();

      while ($row = $results->fetchObject()) {
        $taskStats['processed']++;
        
        try {
          $end = $this->parseTimestamp($row->end);
          $notificationTypes = $this->determineNotifications($row, $end);
       
          if (!empty($notificationTypes)) {
            $params = $this->buildSalesPurchaseParams($row, $end, $notificationTypes);
            $this->queueNotifications($row, $params);
            $this->updateLastNotified('ek_sales_purchase_tasks', $row->id);
            $taskStats['notified']++;
          } else {
            $taskStats['skipped']++;
          }
        }
        catch (\Exception $e) {
          $taskStats['errors']++;
          $this->logger->error('Error processing purchase task @id: @message', [
            '@id' => $row->id,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error querying purchase tasks: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $taskStats;
  }

  /**
   * Process project tasks.
   *
   * @return array
   *   Statistics for this task type.
   */
  protected function processProjectTasks(): array {
    $taskStats = ['processed' => 0, 'notified' => 0, 'skipped' => 0, 'errors' => 0];

    try {
      $query = $this->database->select('ek_project_tasks', 'pt');
      $query->fields('pt');
      $query->condition('notify', 0, '>');
      $query->condition('completion_rate', 100, '<');
      
      $results = $query->execute();

      // Pre-load all project IDs to avoid N+1 queries
      $projectIds = $this->getProjectIdsByPcodes($results);
      
      // Reset and re-execute query
      $results = $query->execute();

      while ($row = $results->fetchObject()) {
        $taskStats['processed']++;
        
        try {
          $end = $this->parseTimestamp($row->end);
          
          // Skip if no valid end date for deadline-based notifications only
          if ($end === NULL && $this->onlyHasDeadlineFlags((int) $row->notify)) {
            $taskStats['skipped']++;
            continue;
          }

          $pid = $projectIds[$row->pcode] ?? NULL;
          if (!$pid) {
            $taskStats['skipped']++;
            continue;
          }

          $notificationTypes = $this->determineNotifications($row, $end);
          
          if (!empty($notificationTypes)) {
            $params = $this->buildProjectParams($row, $end, $pid, $notificationTypes);
            $this->queueNotifications($row, $params);
            $this->updateLastNotified('ek_project_tasks', $row->id);
            $taskStats['notified']++;
          } else {
            $taskStats['skipped']++;
          }
        }
        catch (\Exception $e) {
          $taskStats['errors']++;
          $this->logger->error('Error processing project task @id: @message', [
            '@id' => $row->id,
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error querying project tasks: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $taskStats;
  }

  /**
   * Determine which notifications should be sent based on bitmask.
   *
   * @param object $row
   *   The task row from database.
   * @param int|null $end
   *   The end timestamp or NULL.
   *
   * @return array
   *   Array of notification type constants that should be sent.
   */
  protected function determineNotifications(object $row, ?int $end): array {
    $notify = (int) $row->notify;
    $notifications = [];

    if ($notify === 0) {
      return [];
    }

    // Check if already notified today (prevent duplicates)
    if ($this->wasNotifiedToday($row)) { 
      return [];
    }

    $delta = $end ? (int) round(($end - $this->currentStamp) / 86400) : NULL;
    $isOverdue = $end !== NULL && $end < $this->currentStamp;

    // Daily notification
    if ($notify & self::NOTIFY_DAILY) {
      $notifications[] = self::NOTIFY_DAILY;
    }

    // Weekly notification (Mondays)
    if (($notify & self::NOTIFY_WEEKLY) && $this->weekday === 'Mon') {
      $notifications[] = self::NOTIFY_WEEKLY;
    }

    // Monthly notification (1st of month)
    if (($notify & self::NOTIFY_MONTHLY) && $this->dayOfMonth === 1) {
      $notifications[] = self::NOTIFY_MONTHLY;
    }

    // 5 days before deadline
    if (($notify & self::NOTIFY_5_DAYS_BEFORE) && $delta === 5) {
      $notifications[] = self::NOTIFY_5_DAYS_BEFORE;
    }

    // 3 days before deadline
    if (($notify & self::NOTIFY_3_DAYS_BEFORE) && $delta === 3) {
      $notifications[] = self::NOTIFY_3_DAYS_BEFORE;
    }

    // 1 day before deadline
    if (($notify & self::NOTIFY_1_DAY_BEFORE) && $delta === 1) {
      $notifications[] = self::NOTIFY_1_DAY_BEFORE;
    }

    // On overdue
    if (($notify & self::NOTIFY_ON_OVERDUE) && $isOverdue) {
      $notifications[] = self::NOTIFY_ON_OVERDUE;
    }

    return $notifications;
  }

  /**
   * Check if notification was already sent today.
   *
   * @param object $row
   *   The task row.
   *
   * @return bool
   *   TRUE if already notified today.
   */
  protected function wasNotifiedToday(object $row): bool {
    if (empty($row->last_notified)) {
      return FALSE;
    }
    
    $lastNotifiedDate = date('Y-m-d', (int) $row->last_notified);
    $todayDate = date('Y-m-d', $this->currentStamp);
    
    return $lastNotifiedDate === $todayDate;
  }

  /**
   * Check if the notify bitmask ONLY has deadline flags.
   *
   * @param int $notify
   *   The notification bitmask.
   *
   * @return bool
   *   TRUE if only deadline-based flags are set.
   */
  protected function onlyHasDeadlineFlags(int $notify): bool {
    $deadlineFlags = self::NOTIFY_5_DAYS_BEFORE | 
                     self::NOTIFY_3_DAYS_BEFORE | 
                     self::NOTIFY_1_DAY_BEFORE | 
                     self::NOTIFY_ON_OVERDUE;
    
    $recurringFlags = self::NOTIFY_DAILY | self::NOTIFY_WEEKLY | self::NOTIFY_MONTHLY;
    
    $hasDeadlineFlags = ($notify & $deadlineFlags) > 0;
    $hasRecurringFlags = ($notify & $recurringFlags) > 0;
    
    return $hasDeadlineFlags && !$hasRecurringFlags;
  }

  /**
   * Get notification subject based on type.
   *
   * @param int $notificationType
   *   The notification type constant.
   * @param string $taskType
   *   The type of task (invoice, purchase, project).
   *
   * @return string
   *   The subject line.
   */
  protected function getSubject(int $notificationType, string $taskType): string {
    $typeLabel = ucfirst($taskType);
    
    $subjects = [
      self::NOTIFY_DAILY => $this->t('Daily @type task reminder', ['@type' => $typeLabel]),
      self::NOTIFY_WEEKLY => $this->t('Weekly @type task reminder', ['@type' => $typeLabel]),
      self::NOTIFY_MONTHLY => $this->t('Monthly @type task reminder', ['@type' => $typeLabel]),
      self::NOTIFY_5_DAYS_BEFORE => $this->t('@type task deadline in 5 days', ['@type' => $typeLabel]),
      self::NOTIFY_3_DAYS_BEFORE => $this->t('@type task deadline in 3 days', ['@type' => $typeLabel]),
      self::NOTIFY_1_DAY_BEFORE => $this->t('@type task deadline tomorrow', ['@type' => $typeLabel]),
      self::NOTIFY_ON_OVERDUE => $this->t('OVERDUE: @type task past deadline', ['@type' => $typeLabel]),
      self::NOTIFY_ON_COMPLETE => $this->t('@type task completed', ['@type' => $typeLabel]),
    ];

    return (string) ($subjects[$notificationType] ?? $this->t('@type task notification', ['@type' => $typeLabel]));
  }

  /**
   * Get the most urgent notification type from array.
   *
   * @param array $types
   *   Array of notification types.
   *
   * @return int
   *   The most urgent type.
   */
  protected function getMostUrgentNotificationType(array $types): int {
    $priority = [
      self::NOTIFY_ON_OVERDUE,
      self::NOTIFY_1_DAY_BEFORE,
      self::NOTIFY_3_DAYS_BEFORE,
      self::NOTIFY_5_DAYS_BEFORE,
      self::NOTIFY_DAILY,
      self::NOTIFY_WEEKLY,
      self::NOTIFY_MONTHLY,
    ];

    foreach ($priority as $type) {
      if (in_array($type, $types)) {
        return $type;
      }
    }

    return reset($types) ?: self::NOTIFY_DAILY;
  }

  /**
   * Queue notifications for all recipients.
   *
   * @param object $row
   *   The task row.
   * @param array $params
   *   The notification parameters.
   */
  protected function queueNotifications(object $row, array $params): void {
    if (empty($row->notify_who)) {
      return;
    }

    $users = array_filter(array_map('trim', explode(',', $row->notify_who)));
    
    foreach ($users as $uid) {
      if (!empty($uid) && is_numeric($uid)) {
        $mailParams = $params;
        $mailParams['uid'] = (int) $uid;
        $this->mailQueue[] = $mailParams;
      }
    }
  }

  /**
   * Update last notified timestamp.
   *
   * @param string $table
   *   The table name.
   * @param int $id
   *   The task ID.
   */
  protected function updateLastNotified(string $table, int $id): void {
    try {
      $this->database->update($table)
        ->fields([
          'last_notified' => $this->currentStamp,
        ])
        ->expression('notify_count', 'notify_count + 1')
        ->condition('id', $id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update last_notified for @table ID @id: @message', [
        '@table' => $table,
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Parse a timestamp from various formats.
   *
   * @param mixed $value
   *   The value to parse.
   *
   * @return int|null
   *   The timestamp or NULL.
   */
  protected function parseTimestamp($value): ?int {
    if ($value === NULL || $value === '' || $value === '0000-00-00' || $value === 0 || $value === '0') {
      return NULL;
    }
    
    if (is_numeric($value)) {
      $intVal = (int) $value;
      if ($intVal > 0 && $intVal < 4102444800) {
        return $intVal;
      }
      return NULL;
    }
    
    $parsed = strtotime($value);
    return $parsed !== FALSE ? $parsed : NULL;
  }

  /**
   * Get project IDs indexed by pcode (batch query).
   *
   * @param \Drupal\Core\Database\StatementInterface $results
   *   The query results.
   *
   * @return array
   *   Array of project IDs keyed by pcode.
   */
  protected function getProjectIdsByPcodes($results): array {
    $pcodes = [];
    while ($row = $results->fetchObject()) {
      if (!empty($row->pcode)) {
        $pcodes[] = $row->pcode;
      }
    }

    if (empty($pcodes)) {
      return [];
    }

    $query = $this->database->select('ek_project', 'p');
    $query->fields('p', ['id', 'pcode']);
    $query->condition('pcode', array_unique($pcodes), 'IN');
    
    $projectIds = [];
    foreach ($query->execute() as $row) {
      $projectIds[$row->pcode] = $row->id;
    }

    return $projectIds;
  }

  /**
   * Build params for sales invoice notification.
   *
   * @param object $row
   *   The task row.
   * @param int|null $end
   *   The end timestamp.
   * @param array $notificationTypes
   *   The notification types being sent.
   *
   * @return array
   *   The notification parameters compatible with send_mail().
   */
  protected function buildSalesInvoiceParams(object $row, ?int $end, array $notificationTypes): array {
    $invoiceUrl = Url::fromRoute('ek_sales.invoices.print_html', ['id' => $row->invoice_id])->toString();
    $tasksListUrl = Url::fromRoute('ek_sales.invoices.tasks_list')->toString();
    
    $primaryType = $this->getMostUrgentNotificationType($notificationTypes);
    
    return [
      'type' => 'task',
      'subject' => $this->getSubject($primaryType, 'invoice'),
      'assign' => $row->uid,
      'serial' => $row->serial,
      'link' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $invoiceUrl],
      ])->toString(),
      'alert' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $tasksListUrl],
      ])->toString(),
      'id' => $row->invoice_id,
      'end' => $end ? date('Y-m-d', $end) : NULL,
      'task' => $row->task,
      'options' => [
        'event' => $row->event ?? '',
        'completion_rate' => $row->completion_rate ?? 0,
        'priority' => $row->priority ?? 2,
      ],
    ];
  }

  /**
   * Build params for sales purchase notification.
   *
   * @param object $row
   *   The task row.
   * @param int|null $end
   *   The end timestamp.
   * @param array $notificationTypes
   *   The notification types being sent.
   *
   * @return array
   *   The notification parameters compatible with send_mail().
   */
  protected function buildSalesPurchaseParams(object $row, ?int $end, array $notificationTypes): array {
    $purchaseUrl = Url::fromRoute('ek_sales.purchases.print_html', ['id' => $row->purchase_id])->toString();
    $tasksListUrl = Url::fromRoute('ek_sales.purchases.tasks_list')->toString();
    
    $primaryType = $this->getMostUrgentNotificationType($notificationTypes);
    
    return [
      'type' => 'task',
      'subject' => $this->getSubject($primaryType, 'purchase'),
      'assign' => $row->uid,
      'serial' => $row->serial,
      'link' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $purchaseUrl],
      ])->toString(),
      'alert' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $tasksListUrl],
      ])->toString(),
      'id' => $row->purchase_id,
      'end' => $end ? date('Y-m-d', $end) : NULL,
      'task' => $row->task,
      'options' => [
        'event' => $row->event ?? '',
        'completion_rate' => $row->completion_rate ?? 0,
        'priority' => $row->priority ?? 2,
      ],
    ];
  }

  /**
   * Build params for project notification.
   *
   * @param object $row
   *   The task row.
   * @param int|null $end
   *   The end timestamp.
   * @param int $pid
   *   The project ID.
   * @param array $notificationTypes
   *   The notification types being sent.
   *
   * @return array
   *   The notification parameters compatible with send_mail().
   */
  protected function buildProjectParams(object $row, ?int $end, int $pid, array $notificationTypes): array {
    $projectUrl = Url::fromRoute('ek_projects_view', ['id' => $pid], [
      'query' => ['s2' => TRUE],
      'fragment' => 'ps2',
    ])->toString();
    
    $primaryType = $this->getMostUrgentNotificationType($notificationTypes);
    
    return [
      'type' => 'task',
      'subject' => $this->getSubject($primaryType, 'project'),
      'assign' => $row->uid,
      'serial' => $row->pcode,
      'link' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $projectUrl],
      ])->toString(),
      'alert' => Url::fromRoute('user.login', [], [
        'absolute' => TRUE,
        'query' => ['destination' => $projectUrl],
      ])->toString(),
      'id' => $row->id,
      'end' => $end ? date('Y-m-d', $end) : NULL,
      'task' => $row->task,
      'options' => [
        'project_id' => $pid,
        'event' => $row->event ?? '',
        'completion_rate' => $row->completion_rate ?? 0,
        'priority' => $row->priority ?? 2,
      ],
    ];
  }

  /**
   * Send completion notification (called from form submit).
   *
   * @param string $table
   *   The table name.
   * @param int $taskId
   *   The task ID.
   * @param string $taskType
   *   The task type (invoice, purchase, project).
   *
   * @return array|null
   *   Mail parameters if notification should be sent, NULL otherwise.
   */
  public function buildCompletionNotification(string $table, int $taskId, string $taskType): ?array {
    try {
      $query = $this->database->select($table, 't');
      $query->fields('t');
      $query->condition('id', $taskId);
      $row = $query->execute()->fetchObject();

      if (!$row || !((int) $row->notify & self::NOTIFY_ON_COMPLETE)) {
        return NULL;
      }

      if (empty($row->notify_who)) {
        return NULL;
      }

      $this->currentStamp = time();
      $end = $this->parseTimestamp($row->end);
      $notificationTypes = [self::NOTIFY_ON_COMPLETE];
      
      // Build params based on task type
      switch ($taskType) {
        case 'invoice':
          // Get invoice_id
          $invoiceId = $this->database->select('ek_sales_invoice', 'i')
            ->fields('i', ['id'])
            ->condition('serial', $row->serial)
            ->execute()
            ->fetchField();
          $row->invoice_id = $invoiceId;
          $params = $this->buildSalesInvoiceParams($row, $end, $notificationTypes);
          break;
          
        case 'purchase':
          $purchaseId = $this->database->select('ek_sales_purchase', 'p')
            ->fields('p', ['id'])
            ->condition('serial', $row->serial)
            ->execute()
            ->fetchField();
          $row->purchase_id = $purchaseId;
          $params = $this->buildSalesPurchaseParams($row, $end, $notificationTypes);
          break;
          
        case 'project':
          $pid = $this->database->select('ek_project', 'p')
            ->fields('p', ['id'])
            ->condition('pcode', $row->pcode)
            ->execute()
            ->fetchField();
          if (!$pid) {
            return NULL;
          }
          $params = $this->buildProjectParams($row, $end, (int) $pid, $notificationTypes);
          break;
          
        default:
          return NULL;
      }

      // Return array of mail items for each recipient
      $mailItems = [];
      $users = array_filter(array_map('trim', explode(',', $row->notify_who)));
      foreach ($users as $uid) {
        if (!empty($uid) && is_numeric($uid)) {
          $mailParams = $params;
          $mailParams['uid'] = (int) $uid;
          $mailItems[] = $mailParams;
        }
      }

      return $mailItems;
    }
    catch (\Exception $e) {
      $this->logger->error('Error building completion notification: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}