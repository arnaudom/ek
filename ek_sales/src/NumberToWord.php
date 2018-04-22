<?php

/**
 * @file
 * Contains \Drupal\ek_sales\NumberToWord.
 * 
 */

namespace Drupal\ek_sales;

class NumberToWord {

    public function __construct() {
        
    }

    // the array and the file produce a new asociative array
    public function en($x) {
        
        $v = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 30, 40, 50, 60, 70, 80, 90, 100, 1000, 1000000);
        $w = [0 => t('zero'), 1 => t('one'), 2 => t('two'), 3 => t('three'), 4 => t('four'), 5 => t('five'), 6 => t('six'), 
            7 => t('seven'), 8 => t('eight'), 9 => t('nine'), 10 => t('ten'), 11 => t('eleven'),
            12 => t('twelve'), 13 => t('thirteen'), 14 => t('fourteen'), 15 => t('fifteen'), 16 => t('sixteen'), 17 => t('seventeen'),
            18 => t('eighteen'),19 => t('nineteen'), 20 => t('twenty'), 30 => t('thirty'), 40 => t('forty'), 50 => t('fifty'),
            60 => t('sixty'), 70 => t('seventy'), 80 => t('eighty'), 90 => t('ninety'),
            100 => t('hundred'), 1000 => t('thousand'), 1000000 => t('million')];
        $mi = '';
        $m = '';
        $hundred = '';
        $million = '';
        $thousand = ''; 
        $hunds = '';
        
        $quit_spaces = strlen($w[4]) - 4;   // determines the blank space in each line to quit
        $all = strlen($x);
        $search = strstr($x, ",");         // search for decimal point
        $dot = chr(46);
        $searchdot = strstr($x, $dot);
        $cosex = strlen($search);
        if (strlen($searchdot > 0)) {
            $search = $searchdot;         // have decimals
        }
        $numbersout = strlen($search);
        $newx = substr($x, 0, $all - $numbersout);
        $cents = substr($search, 1, 2);
        $d = substr($cents, 0, 1);
        $u = substr($cents, 1, 1);
        if ($cents > 0) {
            
            if($u > 0){
                $cents = " " .  t('point') . " " . $w[$d] . " " . $w[$u];
            } else {
                $cents = " " .  t('point') . " " . $w[$d*10] ;
            }
                    
        } else {
            $cents = " " .  t('point') . " " . t('zero') ;
        }
        $x = $newx;    // now, $x is an integer
        $e = strlen($x);
        if ($e > 9) {
            //it exceeds number that can be processed;
            return;
        }
        $e = strlen($x);
        $n = substr($x, $e - 2, 2);            // take the last two
        $d = substr($n, 0, 1);
        $u = substr($n, 1, 1);
        $d = $d * 10;
        if ($n < 21) {                         // 1 to 20
            $n = $n * 1;
            $units = $w[$n];
        } else {                              // 21 to 99
            $units = $w[$d];
            $minus = strlen($units);
            $units = substr($units, 0, $minus - $quit_spaces);
            $units = $units . " " . $w[$u];
        }
        if (strlen($x) > 2) {                  // 100 to 999
            $c = substr($x, $e - 3, 3);
            $ce = substr($c, 0, 1);
            $resto = substr($x, $e - 2, 2);
            if ($resto > 0) {
                $hunds = "and ";
            }
            if ($ce > 0) {
                $hunds = $w[$ce] . " " . t('hundred') . " " . $hunds;
            }
        }
        if (strlen($x) > 3) {               // 1000 to 99.999
            if (strlen($x) == 4) {
                $u = substr($x, 0, 1);
                if ($u > 0) {
                    $thousand = $w[$u] . " " . t('thousand') . " ";
                }
            }
            if (strlen($x) > 4) {              // 10.000 to 99.999
                $du = substr($x, $e - 5, 2);
                $du = $du * 1;
                if ($du < 21) {                 // 10.000 to 20.000
                    if ($du > 0) {
                        $thousand = $w[$du] . " " . t('thousand') . " ";
                    }
                } else {
                    $d = substr($du, 0, 1);     // 21.000 to 99.000
                    $d = $d * 10;
                    $u = substr($du, 1, 1);
                    $thousand = $w[$d]; //.
                    $minus = strlen($thousand);
                    $thousand = substr($thousand, 0, $minus - $quit_spaces);
                    if ($d > 0) {
                        $thousand = $thousand . "-" . $w[$u] . " " . t('thousand') . " ";
                    }
                    if (strlen($x) > 5) {
                        $thousand = " " . t('and') . " " . $thousand;
                    }
                }
            }
        }
        if (strlen($x) > 5) {                  // 100.000 to 999.999
            $c = substr($x, $e - 6, 3);
            $ce = substr($c, 0, 1);
            $ntury = substr($c, 1, 2);
            if ($ce > 0) {
                $hundred = $w[$ce] . " " . t('hundred') . " ";
                if ($ntury == "00") {
                    $hundred = $w[$ce] . " " . t('hundred thousand') . " ";
                }
            }
        }
        
        if ($e > 6) {                          // 1.000.000 to 9.000.000
            if ($e == 7) {
                $mi = substr($x, $e - 7, 1);
            }
            if ($e > 7) {                      // 10.000.000 to 99.000.000
                $mi = substr($x, $e - 8, 2);
            }
            //$m = substr($m, 0, 1);
            //$i = substr($m, 1, 1);
        }
        if ($mi < 21) {
            if ($mi > 0) {
                $million = $w[$mi] . " " . t('million') . " ";
            }
        }
        if ($mi > 20) {
            $m = substr($mi, 0, 1);
            $i = substr($mi, 1, 1);
            $m = $m * 10;
            $mill = $w[$m];
            $out = strlen($mill);
            $mill = substr($mill, 0, $out - $quit_spaces);
            $ones = $w[$i];
            $million = $mill . "-" . $ones . " " . t('million') . " ";
        }
        if ($e > 8) {
            $cofm = substr($x, $e - 9, 1);
            $dofm = substr($x, $e - 8, 2);
            $million = $w[$cofm] . " " . t('hundred and') . " " . $million;
            if ($dofm == "00") {
                $million = $w[$cofm] . " " . t('hundred million') . " "; //.
            }
        }
        $mynumber = $million . $hundred . $thousand . $hunds . $units . $cents;
        
        return $mynumber;
    }


}