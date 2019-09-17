<?php

/**
 * @file
 *  module . ek admin
 *  start tasks alerts per modules
 * 
 */
namespace Drupal\ek_admin\cron;


 if($this->moduleHandler->moduleExists('ek_sales')) {    
   include 'sales_tasks.inc'; 
   include 'purchases_tasks.inc'; 
   include 'sales_status.inc'; 
    
}
if($this->moduleHandler->moduleExists('ek_projects'))  {
    
   include 'projects_tasks.inc'; 
   include 'projects_status.inc'; 
    
}

if($this->moduleHandler->moduleExists('ek_hr'))  {
    
   //include 'hr_date_status.inc'; 
    
}

