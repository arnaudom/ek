<?php
use Drupal\Core\Database\Database;

// Create new PHPExcel object
$objPHPExcel = new PHPExcel(); 

$objPHPExcel->getProperties()->setCreator("Ek")
                            ->setLastModifiedBy('')
                            ->setTitle("Address book export")
                            ->setSubject("computer generated")
                            ->setDescription("Entities or contacts export")
                            ->setKeywords("office 2007 openxml php")
                            ->setCategory("file");
$objPHPExcel->getActiveSheet()->setTitle($source);


if($source == 'Address_book') {
   $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'id') 
            ->setCellValue('B1', 'name')    
            ->setCellValue('C1', 'shortname')                     
            ->setCellValue('D1', 'address')           
            ->setCellValue('E1', 'address2')           
            ->setCellValue('F1', 'postcode') 
            ->setCellValue('G1', 'city') 
            ->setCellValue('H1', 'country') 
            ->setCellValue('I1', "telephone") 
            ->setCellValue('J1', 'fax') 
            ->setCellValue('K1', 'website') 
            ->setCellValue('L1', 'type') 
            ->setCellValue('M1', 'category') 
            ->setCellValue('N1', 'status') 
            ->setCellValue('O1', 'stamp') 
            ->setCellValue('P1', 'activity') ; 
   
        $l=1; 
        WHILE ($r = $data->fetchObject()) {
            $l=$l+1; // line index
            if ($l & 1) {
            $row="A".$l.":P".$l;
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
            } 

            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->name);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $r->shortname);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $r->address);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $r->address2);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $r->postcode);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $r->city);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $r->country);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $r->telephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $r->fax);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $r->website);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $r->type);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $r->category);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $r->status);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", date('Y-m-d', $r->stamp));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $r->activity);

        }    
   
   $l=$l+1;
   $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", '1=client, 2=supplier, 3=other');
   $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", '1=active, 0=inactive');
   
} else {
   $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'id') 
            ->setCellValue('B1', 'abid')    
            ->setCellValue('C1', 'contact_name')                     
            ->setCellValue('D1', 'salutation')           
            ->setCellValue('E1', 'title')           
            ->setCellValue('F1', 'telephone') 
            ->setCellValue('G1', 'mobilephone') 
            ->setCellValue('H1', 'email') 
            ->setCellValue('I1', 'card') 
            ->setCellValue('J1', "department") 
            ->setCellValue('K1', 'link') 
            ->setCellValue('L1', 'comment') 
            ->setCellValue('M1', 'main') 
            ->setCellValue('N1', 'stamp') 
            ->setCellValue('O1', 'name')  ;   

        $l=1; 
        WHILE ($r = $data->fetchObject()) {
            $l=$l+1; // line index
            if ($l & 1) {
            $row="A".$l.":O".$l;
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
            } 

            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->abid);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $r->contact_name);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $r->salutation);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $r->title);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $r->telephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $r->mobilephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $r->email);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $r->card);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $r->department);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $r->link);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $r->comment);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $r->main);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", date('Y-m-d', $r->stamp));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $r->name . '(id: ' . $r->abid . ')');


        }    
}

                                                                                    
            
           
   
   
 

$date = date('Y-m-d h:i:s');
$l+=2;
$objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $date);

$fileName = 'export_' . $source . '.xlsx';      

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: max-age=0');
        //header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$fileName");
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');    
        exit;

  
     // end generate report





      
            
            
            
            
            
            
            
            
            
            