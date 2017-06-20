<?php

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator('EK');
$pdf->SetAuthor('EK');
$title = t('item code') . '_' . $items['itemcode'] ;
$pdf->SetTitle($title);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

// set auto page breaks
//$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetAutoPageBreak(TRUE, 10);
// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);


$pdf->SetFont('dejavusans', '', 10, '', true);

// add a page
$pdf->AddPage();

$col1 = 30;
$col2 = 70;
$col3 = 30;
$col4 = 70;
/*Logo*/
    if ($items['company_logo'] != '') {  
        $logo = drupal_realpath($items['company_logo']);
        if(file_exists($logo)) {
            $info = getimagesize($logo);
            $h = $info[1]*40/$info[0];
            $pdf->Image($logo,160,10,$h);
        }
    }

    
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Last edit') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['stamp'],0,0);
$pdf->Cell($col3,5,'',0,1);    

$pdf->SetFont('dejavusans', '',8, '', true);
$pdf->Cell($col1,5,t('Company') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['company'],0,0);
$pdf->Cell($col3,5,'',0,1);  

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Item code') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['itemcode'],0,0);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col3,5,t('Item type') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col4,5,$items['type'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Supplier') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['supplier'],0,0);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col3,5,t('Supplier code') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col4,5,$items['supplier_code'] ,0,1); 
$pdf->ln(2);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1, 6,t('Main description') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->MultiCell($col2+$col3+$col4, 6,$items['description1'],'','L',0);
$pdf->ln(2);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1, 6,t('Other description') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->MultiCell($col2+$col3+$col4, 6,$items['description2'],'','L',0);
$pdf->ln(2);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Department') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['department'],0,0);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col3,5,t('Size tag') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col4,5,$items['size'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Family') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['family'],0,0);
$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col3,5,t('Color') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col4,5,$items['color'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col1,5,t('Collection') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['collection'],0,1);

$pdf->ln(2);
$pdf->Cell($col1+$col2,5,t('Logistics') . ':',1,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Stock') . ':',0,0);
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['units'] . ' ' . $items['measure'],0,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Size') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['item_size'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Packing size') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['pack_size'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Quantity per pack') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['qty_pack'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Quantity per 20 feet container') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['c20'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Quantity per 40 feet container') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['c40'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Minimum order') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col3,5,$items['min_order'] ,0,1); 

$pdf->ln(2);
$pdf->Cell($col1+$col2,5,t('Prices') . ':',1,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,t('Purchase') . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['purchase_price'] . ' ' . $items['currency'] . " (" . $items['date_purchase'] . ")" ,0,0); 
$pdf->Cell($col3,5, '',0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['selling_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['selling_price'] . ' ' . $items['loc_currency'] ,0,1); 

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['promo_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['promo_price'] . ' ' . $items['loc_currency'] ,0,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['discount_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['discount_price'] . ' ' . $items['loc_currency'] ,0,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['exp_selling_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['exp_selling_price'] . ' ' . $items['exp_currency'] ,0,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['exp_promo_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['exp_promo_price'] . ' ' . $items['exp_currency'] ,0,1);

$pdf->SetFont('dejavusans', '', 8, '', true);
$pdf->Cell($col2,5,$items['exp_discount_price_label'] . ':',0,0); 
$pdf->SetFont('dejavusans', '', 10, '', true);
$pdf->Cell($col2,5,$items['exp_discount_price'] . ' ' . $items['exp_currency'] ,0,1);

$pdf->ln(2);
$pdf->Cell($col1+$col2,5,t('Images') . ':',1,1);

$count = 0;
$row = 180;
foreach($items['pictures'] as $key => $picture) {
       
        $image = drupal_realpath($picture['uri']);
        if(file_exists($image)) {
            $x++;            
            $info = getimagesize($image);
            $h = $info[1]*50/$info[0];
            if($x % 2 != 0) {
                $pdf->Image($image,20,$row,$h);
            } else {
                $pdf->Image($image,120,$row,$h);
                $row += 55;
            }
        }
    
    
}


$pdf->AddPage();


$pdf->Cell($col1+$col2,5,t('Barcodes') . ':',1,1);
$pdf->ln(3);
$row = 20;
$pdf->SetFont('dejavusans', '', 8, '', true);
$style = array(
	'position' => '',
	'align' => 'C',
	'stretch' => false,
	'fitwidth' => false,
	'cellfitalign' => '',
	'border' => true,
	'hpadding' => 'auto',
	'vpadding' => 'auto',
	'fgcolor' => array(0,0,0),
	'bgcolor' => false, //array(255,255,255),
	'text' => true,
	'font' => 'helvetica',
	'fontsize' => 7,
	'stretchtext' => 2
);
foreach($items['barcodes'] as $key => $barcode) {
   if($barcode['barcode'])  {         
       
           Switch ($barcode['encode']) {
            Case 'EAN-13':
            Case 'EAN-8':
            Case 'UPC-E':
                $format = str_replace('-', '', $barcode['encode']);
                break;
            Case 'UPC-A':
                $format = 'UPCA';
            Case 'CODE39':
                $format = 'C39';
                break;
            Case 'CODE39 EXTENDED':
                $format = 'C39E';
                break;
            Case 'CODE39 with checksum':
                $format = 'C39+';
                break;
            Case 'CODE39 EXTENDED + CHECKSUM':
                $format = 'C39E+';
                break;
            Case 'CODE 93':
            Case 'CODE93':
                $format = 'C93';
                break;
            Case 'CODE128':
                $format = 'C128';
                break;
            Case 'CODE128 A':
                $format = 'C128A';
                break;
            Case 'CODE128 B':
                $format = 'C128B';
                break;
            Case 'CODE128 C':
                $format = 'C128C';
                break;
            Case 'I25 with checksum':
                $format = 'I25+';
                break;
            Case 'MSI with checksum':
                $format = 'MSI+';
                break;
            Case 'PHARMACODE':
                $format = 'PHARMA';
                break;
            Case 'PHARMACODE TWO-TRACKS':
                $format = 'PHARMA2T';
                break;
            Case 'IMB - Onecode - USPS-B-3200':
                $format = 'IMB';
                break;
            Case 'CBC':
                $format = 'RMS4CC';
                break;
        }
        
        //$pdf->write1DBarcode($code, $type, $x, $y, $w, $h, $xres, $style, $align)
        
        
        
        $pdf->Cell($col1,5,$items['itemcode'],0,1);
        $pdf->Cell($col1,5,$items['description1'],0,1);
   
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        $pdf->write1DBarcode($barcode['barcode'], $format, $x+50, $y-10,30, 10, 1, $style, 'N');
        $pdf->write1DBarcode($barcode['barcode'], $format, $x+100, $y-10,20, 8, 1, $style, 'N');
        $pdf->ln(3);
   }
    
}


































header('Cache-Control: private');
header('Content-Type: application/pdf');


header("Content-Disposition: 'attachment'; filename=\"" . $title . "\" ");

echo $pdf->Output($title, 'I');