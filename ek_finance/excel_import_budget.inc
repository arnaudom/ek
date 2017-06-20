<?php
use Drupal\Core\Database\Database;


$cond = '%-' . $coid . '-' . $year . '-%';
$del = Database::getConnection('external_db', 'external_db')
            ->delete('ek_yearly_budget')
            ->condition('reference', $cond, 'like')
            ->execute();

$Reader = PHPExcel_IOFactory::createReaderForFile($uri);
// set this, to not read all excel properties, just data
$Reader->setReadDataOnly(true); 
$objXLS = $Reader->load($uri);

$row = 1;
$cols = [1 => 'B', 2 => 'C', 3 => 'D', 4 => 'E', 5 => 'F', 6 => 'G', 7 => 'H', 8 => 'I', 9 => 'J', 10 => 'K', 11 => 'L', 12 => 'M'];

WHILE ($objXLS->getSheet(0)->getCell("A$row")->getValue() != '') {
    
    $lineA = $objXLS->getSheet(0)->getCell("A$row")->getValue();
    $lineB = $objXLS->getSheet(0)->getCell("B$row")->getValue();
    $acc = substr($lineA, 0, 5);
        
    if( is_numeric(substr($lineA, 0, 5)) && is_numeric($lineB)) {
        //input line identified
        for ($i = 1; $i < 13; $i++) {
            $reference = substr($lineA, 0, 5) . '-' . $coid . '-' . $year . '-' . $i;
            $col = $cols[$i];
            $value = round($objXLS->getSheet(0)->getCell($col . $row)->getValue(), 2);
            
            $insert = Database::getConnection('external_db', 'external_db')
                        ->insert('ek_yearly_budget')
                        ->fields(['reference' => $reference , 'value_base' => $value])
                        ->execute();
                
        }
    }
    
    $row++;
    
}



        
            
            
            
            
            
            
            
            
            
            