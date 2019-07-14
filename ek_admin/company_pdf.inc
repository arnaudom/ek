<?php
use Drupal\Core\Database\Database;



$query = "SELECT * from {ek_company} where id=:id";
$line = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchAssoc();
  

$pdf=new FPDF();
$title = t('Company contact card');
$pdf->SetTitle($title);
$pdf->AliasNbPages();
$pdf->AddPage("P","A4");
$pdf->SetMargins(10,10);


$pdf->SetDrawColor(128, 128, 128);     
$name = html_entity_decode($line['contact'], ENT_QUOTES);


//DISPLAY DATA simple

//left right corners
    $pdf->Cell(5,3,"","LT",0);
    $pdf->Cell(100,3,"",0,0); 
    $pdf->Cell(5,3,"","RT",1);
    
$pdf->SetFont('Arial','',10);   
    
    $pdf->Cell(5,5,"",0,0);    
    $pdf->Cell(100,5, $line['name'],0,0);
    $pdf->Cell(5,5,"",0,1);

if ($line["reg_number"] ) {
$pdf->SetFont('Arial','',8);
    $pdf->Cell(5,3,"",0,0);    
    $pdf->Cell(100,3,"(" . $line['reg_number'] . ")",0,0);
    $pdf->Cell(5,3,"",0,1); 
$pdf->SetFont('Arial','',10);

}       
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address1"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address2"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($line['postcode']  != '') {
        $text .= $line['postcode'] . " ";
    }
    $text .= $line['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$line["country"],0,0); 
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->ln(2);  
/*
if ($line["telephone"] || $line["fax"]) {
$pdf->SetFont('Arial','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,"tel. " . $line['telephone2'] . " fax. " . $line['fax2'] ,0,0); 
    $pdf->Cell(1,3,"",0,1);
$pdf->SetFont('Arial','',10);  
}*/
    
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
     
$pdf->SetFont('Arial','',10);

    $pdf->Cell(5,6,"",0,0);    
    $pdf->Cell(100,6,"$name",0,0);
    $pdf->Cell(5,6,"",0,1);    
    
    $pdf->Cell(5,5,"",0,0);    
    $pdf->Cell(100,5, $line['name'],0,0);
    $pdf->Cell(5,5,"",0,1);

if ($line["reg_number"] ) {
$pdf->SetFont('Arial','',8);
    $pdf->Cell(5,3,"",0,0);    
    $pdf->Cell(100,3,"(" . $line['reg_number'] . ")",0,0);
    $pdf->Cell(5,3,"",0,1); 
$pdf->SetFont('Arial','',10);

}       
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address1"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address2"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($line['postcode']  != '') {
        $text .= $line['postcode'] . " ";
    }
    $text .= $line['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$line["country"],0,0); 
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->ln(2);  

if ($line["telephone"] || $line["fax"]) {
$pdf->SetFont('Arial','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,"tel. " . $line['telephone2'] . " fax. " . $line['fax2'] ,0,0); 
    $pdf->Cell(1,3,"",0,1);
$pdf->SetFont('Arial','',10);  
}
$pdf->SetFont('Arial','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,$line["email"],0,0); 
    $pdf->Cell(1,3,"",0,1); 
$pdf->SetFont('Arial','',10); 

if ($line["address3"] ) {
    $pdf->ln(3); 
$pdf->SetFont('Arial','UI',8);    
    $pdf->Cell(5,5,"",0,0);       
    $pdf->Cell(100,5,"correspondance address:",0,0); 
    $pdf->Cell(5,5,"",0,1);  
$pdf->SetFont('Arial','',10);
      
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address3"],0,0);
    $pdf->Cell(1,5,"",0,1);   
    
    $pdf->Cell(9,5,"",0,0);    
    $pdf->Cell(100,5,$line["address4"],0,0);
    $pdf->Cell(1,5,"",0,1); 
    
    $text = '';
    if($line['postcode']  != '') {
        $text .= $line['postcode'] . " ";
    }
    $text .= $line['city'];
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$text,0,0);         
    $pdf->Cell(1,5,"",0,1);   
        
    $pdf->Cell(9,5,"",0,0);       
    $pdf->Cell(100,5,$line["country2"],0,0); 
    $pdf->Cell(1,5,"",0,1);     

if ($line["telephone2"] || $line["fax2"]) {
$pdf->SetFont('Arial','',8);
    $pdf->Cell(9,3,"",0,0);       
    $pdf->Cell(100,3,"tel. " . $line['telephone2'] . " fax. " . $line['fax2'] ,0,0); 
    $pdf->Cell(1,3,"",0,1);
$pdf->SetFont('Arial','',10);  
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

  header("Content-Disposition: 'attachment'; filename=\"$line[name]\" ");
  $f = str_replace(' ', '_', $line['name']) . '.pdf';
  echo $pdf->Output($f , 'I');

  exit ;
  
  ?>


