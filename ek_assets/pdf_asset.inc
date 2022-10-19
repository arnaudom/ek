<?php

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator('EK');
$pdf->SetAuthor('EK');
$title = t('Asset') . '_ID_' . $items['id'] ;
$pdf->SetTitle($title);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);


$pdf->SetFont('helvetica', '', 10);

// add a page
$pdf->AddPage();



$col1 = 50;
$col2 = 80;
$col3 = 80;

/*Logo*/
    if ($items['company_logo'] != '') {  
        $logo = \Drupal::service('file_system')->realpath($items['company_logo']);
        if(file_exists($logo)) {
            $info = getimagesize($logo);
            //original height / original width x new width = new height
            $h = $info[1] / $info[0] * 40;
            $pdf->Image($logo,20,20,40,$h,'','','C',false,72,'',false,false,0,'CM');
        }
    }

/*qr code*/
    if ($items['qr_text'] != '') {
        $style = array(
                'border' => true,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0,0,0),
                'bgcolor' => false, //array(255,255,255)
                'module_width' => 1, // width of a single module in points
                'module_height' => 1 // height of a single module in points
        );
        // QRCODE,H : QR-CODE Best error correction
        $pdf->write2DBarcode($items['qr_text'], 'QRCODE,H', 20, 50, 25, 25, $style, 'T');
        //$pdf->write2D
        
    }




$pdf->Ln(1);
//Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=0, $link='', $stretch=0, $ignore_min_height=false, $calign='T', $valign='M')
$pdf->Cell($col1,8,"",0,0);
$pdf->Cell($col1,8,t('ID') . ':',0,0);
$pdf->Cell($col3,8,$items['id'],0,1);
/**/
$pdf->Cell($col1, 8,"",0,0);
$pdf->Cell($col1, 8,t('Registered under') . ':',0,0);
$pdf->Cell($col3, 8, $items['company_name'],0,1);

$pdf->Cell($col1, 8,"",0,0);
$pdf->Cell($col1, 8,t('Asset name') . ':',0,0);
$pdf->Cell($col3, 8, $items['asset_name'],0,1);

$pdf->Ln(10);

$pdf->Cell($col1, 5,t('Asset brand') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['asset_brand'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Asset quantity') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['unit'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 6,t('Description') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->MultiCell($col2, 6,$items['asset_comment'],'','L',0);
$pdf->Cell($col3, 6,'',0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Asset category') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['aid'] . ' ' . $items['aname'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Asset value') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['asset_value'] . ' ' . $items['currency'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Purchase date') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['date_purchase'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Amortization terms') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['term'] . " " . $items['term_unit'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Amortization method') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['method'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);

$pdf->Cell($col1, 5,t('Amortization status') . ':',0,0);
$pdf->SetFont('helvetica','',8);
$pdf->Cell($col2, 5,$items['amort_status'],0,0);
$pdf->Cell($col3, 5,"",0,1);
$pdf->SetFont('helvetica','',10);




if($items['doc']){
$link = \Drupal::service('file_url_generator')->generateAbsoluteString($items['doc']);
$pdf->Write(5, t('Attachment') . ': ' . $items['doc_name'], $link);
}

if($items['eid']) {
    $link2 = \Drupal::service('file_url_generator')->generateAbsoluteString($items['eurl']);
    $pdf->Cell($col1, 5,t('Allocation') . ':',0,0);
    $pdf->SetFont('helvetica','',8);
    $pdf->Cell($col2, 5,$items['eid'] . ' | ' . $items['employee'],0,0,'L',FALSE);
    $pdf->Cell($col3, 5,"",0,1);
    $pdf->SetFont('helvetica','',10);    
}


/*item image*/
    if ($items['picture'] != '') {
        $picture = \Drupal::service('file_system')->realpath($items['picture']);
        if(file_exists($picture)) {
            $info = getimagesize($picture);
            //original height / original width x new width = new height
            $h = $info[1] / $info[0] * 30;
            $pdf->Image($picture,20,170,30,$h,'','','C',false,72,'',false,false,0,'CM');
        }
    }

header('Cache-Control: private');
header('Content-Type: application/pdf');
header("Content-Disposition: 'attachment'; filename=\"" . $title . "\" ");

echo $pdf->Output($title, 'I');