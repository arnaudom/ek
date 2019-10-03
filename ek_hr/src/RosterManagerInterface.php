<?php

namespace Drupal\ek_hr;

/**
 * Interface for RosterManager.
 */
interface RosterManagerInterface {
          
  /**
   * Calculate time from roster input.
   * @param string   roster time expressed decimal format separated by comma
   * @param bolean $raw
   * * @param int $format 1: H:i; 2: decimal
   * @return int or string $total
   *   
   */
  public function timed($roster, $raw = FALSE, $format = '1');
 
   /**
   * Convert roster string into shift.
   * @param string roster : hour shifts separated by comma
   * @return string $shift
   *   
   */
  public function shift($roster);
  
  /**
   * Get type of day per roster span
   * @param string $month_0 format 'Y-m' , month first half
   * @param string $month_1 format 'Y-m' , month second half
   * @param int $start_0 start day first half
   * @param int $start_1 start day second half
   * @param int $cut_0 cut day first half
   * @param int $cut_1 cut day second half
   * @param int $coid 
   * @return array of date with type: n (normal), ph (public holiday), s (Sunday)
   *   
   */
  public function dayType($month_0, $month_1, $start_0, $start_1, $cut_0, $cut_1, $coid);
  
  /**
   * Convert seconds in H:m
   * @param int $seconds
   * @param int $format default 'H:m'
   * 
   */
  public function to_hms($seconds, $format = 'H:m');
  
    
  /**
   * Convert h:m:s to seconds
   * @param string $hms time in H:m:s format
   * 
   */
  public function to_second($hms);
  
      
  /**
   * format roster string
   * @param string a.b,a.b,a.b,a.b,a.b,a.b,a.b,a.b,
   * @returned string
   * 
   */
  public function filter_shift($roster);
    
}