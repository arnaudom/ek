<?php

use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Xss;

$del = Database::getConnection('external_db', 'external_db')
            ->delete('ek_accounts')
            ->condition('coid', $coid, '=')
            ->execute();

$inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($uri);
$Reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
// set this, to not read all excel properties, just data
$Reader->setReadDataOnly(true); 
$objXLS = $Reader->load($uri);
$error = [];
$row = 1;
$cols = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G'];
$type = ['header','class','detail'];

WHILE ($objXLS->getSheet(0)->getCell("A$row")->getValue() != '') {
    
    $colA = $objXLS->getSheet(0)->getCell("A$row")->getValue();
    $colB = $objXLS->getSheet(0)->getCell("B$row")->getValue();
    $colC = $objXLS->getSheet(0)->getCell("C$row")->getValue();
    $colD = $objXLS->getSheet(0)->getCell("D$row")->getValue();
    $colE = $objXLS->getSheet(0)->getCell("E$row")->getValue(); 
    $colG = $objXLS->getSheet(0)->getCell("G$row")->getValue();
        
    if( is_numeric($colA) && $colA > 9999) {
        //input line identified
        
        if(!in_array($colC, $type)) {
            $error[] = $colA;
        } else {
            
            if(!in_array($colG, ['0','1'])) {
                $colG = 0;
            }
            if(!is_numeric($colD)) {
                $colD = 0;
            }
            if(!is_numeric($colE)) {
                $colE = 0;
            }

            //filter date
            $cellF = $objXLS->getActiveSheet()->getCell("F$row");
            $colF = $cellF->getValue();
            if(\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cellF) || is_numeric($colF)) {
                $colF = date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::ExcelToPHP($colF)); 
            } else {
                $colF = Xss::filter($colF);
            }

            $fields = [
                'aid' => $colA,
                'aname' => Xss::filter($colB),
                'atype' => $colC,
                'astatus' => $colG,
                'coid' => $coid,
                'link' => NULL,
                'balance' => $colD,
                'balance_base' => $colE,
                'balance_date' => $colF

            ];
            
            $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_accounts')
                        ->fields($fields)
                        ->execute();
        
        }
        
    } 
    
    $row++;
    
}


if(!empty($error)) {
    $errors = implode(",", $error);
    drupal_set_message(t('Error with following line(s): @l', ['@l' => $errors]), 'warning');
}



        
            
            
            
            
            
            
            
            
            
            