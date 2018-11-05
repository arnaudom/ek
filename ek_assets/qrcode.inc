<?php

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator('EK');
$pdf->SetAuthor('EK');
$title = t('Assets');
$pdf->SetTitle($title);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
//$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
//$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);


$pdf->SetFont('helvetica', '', 8);
$pdf->setMargins(25,10);
// add a page
$pdf->AddPage('P','A4');
$pdf->SetAutoPageBreak(1,40);

$pdf->SetLineStyle(array('width' => 0.1, 'color' => array(120,120,120)));
$w = 35;
$h = 35;
$cut = 5;

$col1 = 40;
$col2 = 40;
$col3 = 40;
$col4 = 40;
$pdf->Ln(10);

//$pdf->Cell($col1,3,'','LTR',0);
//$pdf->Cell($col2,3,'','TR',0);
//$pdf->Cell($col3,3,'','TR',0);
//$pdf->Cell($col4,3,'','TR',1);

$style = array(
                'border' => true,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0,0,0),
                'bgcolor' => false, //array(255,255,255)
                'module_width' => 1, // width of a single module in points
                'module_height' => 1, // height of a single module in points
                //'position'=>'S',
    
        );



$y = 0;

for($i=0;$i <= count($print);$i+=4) {
    $y++;
    if($y == $cut) {
        $pdf->AddPage('P','A4');
        $y = 1;
    }
    $pdf->write2DBarcode($print[$i]['qrcode'], 'QRCODE,H', $col1-($w/2), ($y*($h+11)),$w,$h,$style,'T');
    $pdf->write2DBarcode($print[$i+1]['qrcode'], 'QRCODE,H', ($col1+$col2-($w/2)), ($y*($h+11)),$w,$h,$style,'T');
    $pdf->write2DBarcode($print[$i+2]['qrcode'], 'QRCODE,H', ($col1+$col2+$col3-($w/2)), ($y*($h+11)),$w,$h,$style,'T');
    $pdf->write2DBarcode($print[$i+3]['qrcode'], 'QRCODE,H', ($col1+$col2+$col3+$col4-($w/2)), ($y*($h+11)),$w,$h,$style,'T');    
    $pdf->Ln($h);
    $pdf->Cell($col1,5,$print[$i]['id'],0,0);
    $pdf->Cell($col2,5,$print[$i+1]['id'],0,0);
    $pdf->Cell($col3,5,$print[$i+2]['id'],0,0);
    $pdf->Cell($col4,5,$print[$i+3]['id'],0,1);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->Cell($col1,6,(strlen($print[$i]['name']) > 30) ? substr($print[$i]['name'],0,30) . "... " : $print[$i]['name'],0,0);
    $pdf->Cell($col2,6,$print[$i+1]['name'],0,0);
    $pdf->Cell($col3,6,$print[$i+2]['name'],0,0);
    $pdf->Cell($col4,6,$print[$i+3]['name'],0,1);
    
    

}





header('Cache-Control: private');
header('Content-Type: application/pdf');
header("Content-Disposition: 'attachment'; filename=\"" . $title . "\" ");

echo $pdf->Output($title, 'I');