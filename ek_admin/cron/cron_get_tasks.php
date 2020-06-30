<?php

/**
 * @file
 *  module . ek admin
 *  start tasks alerts per modules
 *
 */

namespace Drupal\ek_admin\cron;

use Drupal\ek_admin\GlobalSettings;

$settings = new GlobalSettings(0);

if ($this->moduleHandler->moduleExists('ek_sales')) {
    if ($settings->get('sale_tasks') == 1) {
        include 'sales_tasks.inc';
    }
    if ($settings->get('purchase_tasks') == 1) {
        include 'purchases_tasks.inc';
    }
    if ($settings->get('sale_status') == 1) {
        include 'sales_status.inc';
    }
}
if ($this->moduleHandler->moduleExists('ek_projects')) {
    if ($settings->get('project_tasks') == 1) {
        include 'projects_tasks.inc';
    }
    if ($settings->get('project_status') == 1) {
        include 'projects_status.inc';
    }
}

if ($this->moduleHandler->moduleExists('ek_hr')) {
    if ($settings->get('hr_tasks') == 1) {
        include 'hr_date_status.inc';
    }
}
