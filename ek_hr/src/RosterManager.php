<?php

namespace Drupal\ek_hr;

use Drupal\Core\Database\Database;

/**
 * Defines an RostertManager service.
 */
class RosterManager implements RosterManagerInterface
{

    /**
     * {@inheritdoc}
     */
    public function timed($roster, $raw = false, $format = '1')
    {
        $r = explode(",", $roster);
        $t0 = explode(".", $r[0]);
        $s = is_numeric($t0[0]) ? $t0[0] : 0;
        $m = is_numeric($t0[1]) ? $t0[1] : 0;
        $ta = $s * 3600 + $m * 60;
        $t1 = explode(".", $r[1]);
        $s = is_numeric($t1[0]) ? $t1[0] : 0;
        $m = is_numeric($t1[1]) ? $t1[1] : 0;
        $tb = $s * 3600 + $m * 60;
        $t2 = explode(".", $r[2]);
        $s = is_numeric($t2[0]) ? $t2[0] : 0;
        $m = is_numeric($t2[1]) ? $t2[1] : 0;
        $tc = $s * 3600 + $m * 60;
        $t3 = explode(".", $r[3]);
        $s = is_numeric($t3[0]) ? $t3[0] : 0;
        $m = is_numeric($t3[1]) ? $t3[1] : 0;
        $td = $s * 3600 + $m * 60;
        $t4 = explode(".", $r[4]);
        $s = is_numeric($t4[0]) ? $t4[0] : 0;
        $m = is_numeric($t4[1]) ? $t4[1] : 0;
        $te = $s * 3600 + $m * 60;
        $t5 = explode(".", $r[5]);
        $s = is_numeric($t5[0]) ? $t5[0] : 0;
        $m = is_numeric($t5[1]) ? $t5[1] : 0;
        $tf = $s * 3600 + $m * 60;

        $total = ($tb - $ta) + ($td - $tc) + ($tf - $te);

        if ($raw == true) {
            return $total;
        } else {
            if ($format == '1') {
                return gmdate('H:i', $total);
            } else {
                return round($total / 3600, 2);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function shift($roster)
    {
        $r = explode(",", $roster);
        $shift = '';
        if ($r[1] - $r[0] == 8 && $r[3] - $r[2] == 8 && $r[5] - $r[4] == 8) {
            $shift .= "S1=from $r[0] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[3] - $r[2] > 0 && $r[5] - $r[4] == 0 && $r[1] == $r[2]) {
            $shift .= "S1=from $r[0] to $r[3] \r\n";
        } elseif ($r[1] - $r[0] == 0 && $r[3] - $r[2] > 0 && $r[5] - $r[4] > 0 && $r[3] == $r[4]) {
            $shift .= "S1=from $r[2] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[3] - $r[2] > 0 && $r[3] - $r[2] < 8 && $r[5] - $r[4] > 0 && $r[1] == $r[2]) {
            $shift .= "S1=from $r[0] to $r[3] \r\n";
            $shift .= "S2=from $r[4] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[1] - $r[0] < 8 && $r[5] - $r[4] && $r[1] == $r[2] && $r[3] == $r[4]) {
            $shift .= "S1=from $r[0] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[1] - $r[0] < 8 && $r[3] - $r[2] > 0 && $r[5] - $r[4] > 0 && $r[3] == $r[4]) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
            $shift .= "S2=from $r[2] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[1] - $r[0] < 8 && $r[3] - $r[2] > 0 && $r[3] - $r[2] < 8 && $r[5] - $r[4] > 0) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
            $shift .= "S2=from $r[2] to $r[3] \r\n";
            $shift .= "S3=from $r[4] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[3] - $r[2] > 0 && $r[5] - $r[4] == 0) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
            $shift .= "S2=from $r[2] to $r[3] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[1] - $r[0] < 8 && $r[5] - $r[4] > 0 && $r[3] == $r[4]) {
            $shift .= "S1=from $r[2] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] == 0 && $r[1] - $r[0] < 8 && $r[5] - $r[4] > 0 && $r[3] == $r[4]) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
            $shift .= "S2=from $r[2] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[3] - $r[2] == 0 && $r[5] - $r[4] == 0) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
        } elseif ($r[1] - $r[0] == 0 && $r[3] - $r[2] > 0 && $r[5] - $r[4] == 0) {
            $shift .= "S1=from $r[2] to $r[3] \r\n";
        } elseif ($r[1] - $r[0] > 0 && $r[3] - $r[2] == 0 && $r[5] - $r[4] > 0) {
            $shift .= "S1=from $r[0] to $r[1] \r\n";
            $shift .= "S2=from $r[4] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] == 0 && $r[3] - $r[2] == 0 && $r[5] - $r[4] > 0) {
            $shift .= "S1=from $r[4] to $r[5] \r\n";
        } elseif ($r[1] - $r[0] == 0 && $r[3] - $r[2] > 0 && $r[5] - $r[4] > 0) {
            $shift .= "S1=from $r[2] to $r[3] \r\n";
            $shift .= "S2=from $r[4] to $r[5] \r\n";
        }

        /*
          if ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[3] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] == $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[3] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
          $shift .= "S1=from $r[2] to $r[3] \r\n";
          } elseif ($r[5] == $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          $shift .= "S2=from $r[2] to $r[3] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] == $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
          $shift .= "S1=from $r[2] to $r[5] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
          $shift .= "S1=from $r[4] to $r[5] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          $shift .= "S2=from $r[4] to $r[5] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] == $r[2] && $r[2] = $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          $shift .= "S2=from $r[4] to $r[5] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] >= $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] == $r[0]) {
          $shift .= "S1=from $r[2] to $r[3] \r\n";
          $shift .= "S2=from $r[4] to $r[5] \r\n";
          } elseif ($r[5] > $r[4] && $r[4] > $r[3] && $r[3] > $r[2] && $r[2] > $r[1] && $r[1] > $r[0]) {
          $shift .= "S1=from $r[0] to $r[1] \r\n";
          $shift .= "S2=from $r[2] to $r[3] \r\n";
          $shift .= "S3=from $r[4] to $r[5] \r\n";
          }
         */
        return $shift;
    }

    /**
     * {@inheritdoc}
     */
    public function dayType($month_0, $month_1, $start_0, $start_1, $cut_0, $cut_1, $coid)
    {
        $dayType = [];
        $query = "SELECT * FROM {ek_hr_workforce_ph} WHERE date=:d AND coid=:coid";
        $roster = new \Drupal\ek_hr\HrSettings($coid);
        $settings = $roster->HrRoster[$coid];
        $last_day = isset($settings['last_day']) ? $settings['last_day'] : 7;

        for ($i = $start_0; $i <= $cut_0; $i++) {
            $date = $month_0 . '-' . $i;
            $a = [':d' => $date, ':coid' => $coid];
            $ph = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
            $day = date('j', strtotime($date));
            $dayType[$day . '_'] = date('D', strtotime($date));
            if ($ph) {
                $day = date('j', strtotime($ph->date));
                $dayType[$day] = $ph->description;
                $dayType[$day . '_'] = date('D', strtotime($date));
            } elseif (date('N', strtotime($date)) == $last_day) {
                //Sunday
                $dayType[$day] = 's';
            } else {
                //normal
                $dayType[$day] = 'n';
            }
        }

        for ($i = $start_1; $i <= $cut_1; $i++) {
            $date = $month_1 . '-' . $i;
            $a = [':d' => $date, ':coid' => $coid];
            $ph = Database::getConnection('external_db', 'external_db')->query($query, $a)->fetchObject();
            $day = date('j', strtotime($date));
            $dayType[$day . '_'] = date('D', strtotime($date));
            if ($ph) {
                $day = date('j', strtotime($ph->date));
                $dayType[$day] = $ph->description;
                $dayType[$day . '_'] = date('D', strtotime($date));
            } elseif (date('N', strtotime($date)) == $last_day) {
                //Sunday
                $dayType[$day] = 's';
            } else {
                //normal
                $dayType[$day] = 'n';
            }
        }

        return $dayType;
    }

    /**
     * {@inheritdoc}
     */
    public function to_hms($seconds, $format = 'H:m')
    {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours * 3600)) / 60);
        $secs = floor($seconds % 60);
        $mins = ($mins == 0) ? '00' : $mins;
        $secs = ($secs == 0) ? '00' : $secs;
        return $hours . ':' . $mins . ':' . $secs;
    }

    /**
     * {@inheritdoc}
     */
    public function to_second($hms)
    {
        $t_ = explode(":", $hms);
        $s = 0;
        $m = 0;
        if (is_array($t_)) {
            $s = is_numeric($t_[0]) ? $t_[0] : 0;
            if (isset($t_[1])) {
                $m = is_numeric($t_[1]) ? $t_[1] : 0;
            }
        }
        return ($s * 3600 + $m * 60);
    }
    
    /**
     * {@inheritdoc}
     */
    public function filter_shift($roster)
    {
        if ($roster == '') {
            return $roster;
        }
        $r = explode(",", $roster);
        
        if ($r[0] == '8.00' && $r[1] == '8.00') {
            $r[0] = '0.00' ;
            $r[1] = '0.00';
        }
        if ($r[2] == '16.00' && $r[3] == '16.00') {
            $r[2] = '8.00' ;
            $r[3] = '8.00';
        }
        if ($r[4] == '24.00' && $r[5] == '24.00') {
            $r[4] = '16.00' ;
            $r[5] = '16.00';
        }
        
        return implode(',', $r);
    }
}
