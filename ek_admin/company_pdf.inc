<?php

use Drupal\Core\Database\Database;

class EKPDF extends TCPDF {
    
    //Page header
    public function Header() {
        $data = $this->getHeaderData();
        $this->company = $data['title'];
        if ($this->company['logo']!= '') {
               $logo = \Drupal::service('file_system')->realpath($this->company['logo']);
               if(file_exists($logo)) {
                   //$this->Image($logo,160,10,20);
               }
        }
    }

    // Page footer
    public function Footer() {
        
    }
        
}

$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c');
$query->fields('c');
$query->condition('id', $id);
$company = $query->execute()->fetchAssoc();

$pdf = $pdf = new EKPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information

$pdf->SetCreator($company['name']);
$pdf->SetAuthor(\Drupal::currentUser()->getUsername());
$pdf->SetTitle(t('Company') . ' ' . $id);
$pdf->SetSubject(t('Company'));
$pdf->SetKeywords(t('Company'));

// set default header data
$pdf->SetHeaderData('','',$company,'');

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);


$title = t('Company contact card');
$pdf->SetTitle($title);
//$pdf->AliasNbPages();
$pdf->AddPage("P","A4");
//$pdf->SetMargins(10,10);


$pdf->SetDrawColor(128, 128, 128);     
$name = html_entity_decode($company['contact'], ENT_QUOTES);


//DISPLAY DATA simple
$pdf->SetLineStyle(['width' => 0.1, 'cap' => 'square', 'join' => 'miter', 'dash' => 1, 'color' => [128, 128, 128]]);
//left right corners
    $pdf->Cell(5,3,"","LT",0);
    $pdf->Cell(100,3,"",0,0); 
    $pdf->Cell(5,3,"","RT",1);
    
$pdf->SetFont('helvetica','',10);   
    
    $pdf->Cell(5,5,"",0,0);    
    $pdf->Cell(100,5, $company['name'],0,0);
    $pdf->Cell(5,5,"",0,1);

if ($company["reg_number"] ) {
$pdf->SetFont('helvetica','',8);
    $pdf->Cell(5,3,"",0,0);    
    $pdf->Cell(100,3,"(" . $company['reg_number'] . ")",0,0);
    $pdf->Cell(5,3,"",0,1); 
$pdf->SetFont('helvetica','',10);

}       
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address1"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address2"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($company['postcode']  != '') {
        $text .= $company['postcode'] . " ";
    }
    $text .= $company['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$company["country"],0,0); 
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->ln(2);  

    
//left right corners
    $pdf->Cell(5,3,"","LB",0);
    $pdf->Cell(100,3,"",0,0); 
    $pdf->Cell(5,3,"","RB",1);


$pdf->AddPage('P','A4');
//DISPLAY DATA extended

//left right corners
    $pdf->Cell(5,3,"","LT",0);
    $pdf->Cell(100,3,"",0,0); 
    $pdf->Cell(5,3,"","RT",1);
     
$pdf->SetFont('helvetica','',10);

    $pdf->Cell(5,6,"",0,0);    
    $pdf->Cell(100,6,"$name",0,0);
    $pdf->Cell(5,6,"",0,1);    
    
    $pdf->Cell(5,5,"",0,0);    
    $pdf->Cell(100,5, $company['name'],0,0);
    $pdf->Cell(5,5,"",0,1);

if ($company["reg_number"] ) {
$pdf->SetFont('helvetica','',8);
    $pdf->Cell(5,3,"",0,0);    
    $pdf->Cell(100,3,"(" . $company['reg_number'] . ")",0,0);
    $pdf->Cell(5,3,"",0,1); 
$pdf->SetFont('helvetica','',10);

}       
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address1"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address2"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($company['postcode']  != '') {
        $text .= $company['postcode'] . " ";
    }
    $text .= $company['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$company["country"],0,0); 
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->ln(2);  

if ($company["telephone"] || $company["fax"]) {
$pdf->SetFont('helvetica','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,"tel. " . $company['telephone2'] . " fax. " . $company['fax2'] ,0,0); 
    $pdf->Cell(1,3,"",0,1);
$pdf->SetFont('helvetica','',10);  
}
$pdf->SetFont('helvetica','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,$company["email"],0,0); 
    $pdf->Cell(1,3,"",0,1); 
$pdf->SetFont('helvetica','',10); 

if ($company["address3"] ) {
    $pdf->ln(3); 
$pdf->SetFont('helvetica','UI',8);    
    $pdf->Cell(5,5,"",0,0);       
    $pdf->Cell(100,5,"correspondance address:",0,0); 
    $pdf->Cell(5,5,"",0,1);  
$pdf->SetFont('helvetica','',10);
      
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address3"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$company["address4"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($company['postcode']  != '') {
        $text .= $company['postcode'] . " ";
    }
    $text .= $company['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$company["country2"],0,0); 
    $pdf->Cell(1,5,"",0,1);     

if ($company["telephone2"] || $company["fax2"]) {
$pdf->SetFont('helvetica','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,"tel. " . $company['telephone2'] . " fax. " . $company['fax2'] ,0,0); 
    $pdf->Cell(1,3,"",0,1);
$pdf->SetFont('helvetica','',10);  
}  
    

} // end correspondance address
    
//left right corners
    $pdf->Cell(5,3,"","LB",0);
    $pdf->Cell(100,3,"",0,0); 
    $pdf->Cell(5,3,"","RB",1);



  if (headers_sent()) {
    exit('Unable to stream pdf: headers already sent');
  }
  header('Cache-Control: private');
  header('Content-Type: application/pdf');

  //$content_disposition = variable_get('print_pdf_content_disposition', PRINT_PDF_CONTENT_DISPOSITION_DEFAULT);
  //$attachment =  ($content_disposition == 2) ?  'attachment' :  'inline';

  header("Content-Disposition: 'attachment'; filename=\"$company[name]\" ");
  $f = str_replace(' ', '_', $company['name']) . '.pdf';
  echo $pdf->Output($f , 'I');

  exit ;
  
  ?>


