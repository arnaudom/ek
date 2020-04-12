<?php
use Drupal\Core\Database\Database;

// Create new Excel object
$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

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
            ->setCellValue('C1', 'reg.')
            ->setCellValue('D1', 'shortname')                     
            ->setCellValue('E1', 'address')           
            ->setCellValue('F1', 'address2')
            ->setCellValue('G1', 'state')           
            ->setCellValue('H1', 'postcode') 
            ->setCellValue('I1', 'city') 
            ->setCellValue('J1', 'country') 
            ->setCellValue('K1', "telephone") 
            ->setCellValue('L1', 'fax') 
            ->setCellValue('M1', 'website') 
            ->setCellValue('N1', 'type') 
            ->setCellValue('O1', 'category') 
            ->setCellValue('P1', 'status') 
            ->setCellValue('Q1', 'stamp') 
            ->setCellValue('R1', 'activity') ; 
   
        $l=1; 
        WHILE ($r = $data->fetchObject()) {
            $l=$l+1; // line index
            if ($l & 1) {
            $row="A".$l.":P".$l;
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
            } 
            
            //force default cell input to avoid error if reused for import
            $address = (NULL == $r->address) ? "-" : $r->address;
            $address2 = (NULL == $r->address2) ? "-" : $r->address2;
            $reg = (NULL == $r->reg) ? "-" : $r->reg;
            $postcode = (NULL == $r->postcode) ? "-" : $r->postcode;
            $city = (NULL == $r->city) ? "-" : $r->city;
            $state = (NULL == $r->state) ? "-" : $r->state;
            $country = (NULL == $r->country) ? "-" : $r->country;
            $telephone = (NULL == $r->telephone) ? "-" : $r->telephone;
            $fax = (NULL == $r->fax) ? "-" : $r->fax;
            $website = (NULL == $r->website) ? "-" : $r->website;
            $website = (NULL == $r->website) ? "-" : $r->website;
            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->name);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $reg);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $r->shortname);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $address);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $address2);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $state);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $postcode);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $city);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $country);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $telephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $fax);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("M$l", $website);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("N$l", $r->type);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("O$l", $r->category);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("P$l", $r->status);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("Q$l", date('Y-m-d', $r->stamp));
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("R$l", $r->activity);

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
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $objPHPExcel->getActiveSheet()->getStyle("$row")->getFill()->getStartColor()->setARGB('e6e6fa');
            } 
            
            //force default cell input to avoid error if reused for import
            $salutation = (NULL == $r->salutation) ? "-" : $r->salutation;
            $title = (NULL == $r->title) ? "-" : $r->title;
            $title = (NULL == $r->title) ? "-" : $r->title;
            $telephone = (NULL == $r->telephone) ? "-" : $r->telephone;
            $mobilephone = (NULL == $r->mobilephone) ? "-" : $r->mobilephone;
            $email = (NULL == $r->email) ? "-" : $r->email;
            $card = (NULL == $r->card) ? "-" : $r->card;
            $department = (NULL == $r->department) ? "-" : $r->department;
            $link = (NULL == $r->link) ? "-" : $r->link;
            $comment = (NULL == $r->comment) ? "-" : $r->comment;
            
            
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A$l", $r->id);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("B$l", $r->abid);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C$l", $r->contact_name);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("D$l", $salutation);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("E$l", $title);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("F$l", $telephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("G$l", $mobilephone);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("H$l", $email);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("I$l", $card);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("J$l", $department);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("K$l", $link);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue("L$l", $comment);
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
        
        header("Content-Disposition: attachment;filename=$fileName");
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
        
        $objWriter->save('php://output');    
        exit;