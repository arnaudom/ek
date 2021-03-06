<?php

use Drupal\Core\Database\Database;


class EKPDF extends TCPDF {
    
    //Page header
    public function Header() {
        $data = $this->getHeaderData();
        $this->company = $data['title'];
        if ($this->company->logo != '') {
               $logo = \Drupal::service('file_system')->realpath($this->company->logo);
               if(file_exists($logo)) {
                   //$this->Image($logo,20,10,43);
               }
        }
    }

    // Page footer
    public function Footer() {
        
    }
        
}

$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book', 'ab');
$query->fields('ab');
$query->condition('id', $abid);
$book_data = $query->execute()->fetchObject();
            
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_address_book_contacts', 'abc');
$query->fields('abc');
$query->condition('id', $cid);
$card_data = $query->execute()->fetchObject();

//Default company ID
$query = Database::getConnection('external_db', 'external_db')
                    ->select('ek_company', 'c');
$query->fields('c');
$query->condition('id', 1);
$company = $query->execute()->fetchObject();

$pdf = $pdf = new EKPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
// set document information

$pdf->SetCreator($company->name);
$pdf->SetAuthor(\Drupal::currentUser()->getAccountName());
$pdf->SetTitle(t('Address book card') . ' ' . $cid);
$pdf->SetSubject(t('Address book'));
$pdf->SetKeywords(t('Address book'));

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

$title = t('Contact card');
$pdf->SetTitle($title);
$pdf->AddPage("P", "A4");
//$pdf->SetMargins(10, 10);


$pdf->SetDrawColor(128, 128, 128);
$clientname = html_entity_decode($book_data->name, ENT_QUOTES);
$address = html_entity_decode($book_data->address, ENT_QUOTES);
$address2 = html_entity_decode($book_data->address2, ENT_QUOTES);

//DISPLAY DATA
//left right corners
$pdf->SetLineStyle(['width' => 0.1, 'cap' => 'square', 'join' => 'miter', 'dash' => 1, 'color' => [128, 128, 128]]);
$pdf->Cell(5, 3, "", "LT", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RT", 1);

    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 6, $book_data->name, 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 3, '(' . $book_data->reg . ')', 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 6, $card_data->salutation . ' ' . $card_data->contact_name, 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    
    if($card_data->title){
        $pdf->Cell(5, 6, "", "0", 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(100, 6,  '(' . $card_data->title . ')', 0, 0);
        $pdf->Cell(5, 6, "", "0", 1);
    }

    $pdf->SetFont('helvetica', '', 8);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->address, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->address2, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->state, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->postcode . ' ' . $book_data->city, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->country, 0, 1);
    $pdf->Cell(10, 5, "", "0", 1);

//left right corners

$pdf->Cell(5, 3, "", "LB", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RB", 1);

$pdf->AddPage("P", "A4");
//$pdf->Ln(5);
//DISPLAY contact
//left right corners
$pdf->SetLineStyle(['width' => 0.1, 'cap' => 'square', 'join' => 'miter', 'dash' => 1, 'color' => [128, 128, 128]]);
$pdf->Cell(5, 3, "", "LT", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RT", 1);

    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 6, $book_data->name, 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 3, '(' . $book_data->reg . ')', 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(5, 6, "", "0", 0);
    $pdf->Cell(100, 6, $card_data->salutation . ' ' . $card_data->contact_name, 0, 0);
    $pdf->Cell(5, 6, "", "0", 1);
    
    if($card_data->title){
        $pdf->Cell(5, 6, "", "0", 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(100, 6,  '(' . $card_data->title . ')', 0, 0);
        $pdf->Cell(5, 6, "", "0", 1);
    }

    $pdf->SetFont('helvetica', '', 8);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->address, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->address2, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);
    
    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->state, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);
    
    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->postcode . ' ' . $book_data->city, 0, 0);
    $pdf->Cell(10, 5, "", "0", 1);

    $pdf->Cell(10, 5, "", "0", 0);
    $pdf->Cell(90, 5, $book_data->country, 0, 1);
    $pdf->Cell(10, 5, "", "0", 1);


    $pdf->SetFont('helvetica', '', 10);
    
    if($card_data->telephone){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Telephone') . ':' , 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 6, $card_data->telephone, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }
    if($card_data->mobilephone){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Mobile') . ':' , 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 6, $card_data->mobilephone, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }
    if($card_data->email){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Email') . ':' , 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 6, $card_data->email, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }    
    if($book_data->fax){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Fax') . ':' , 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(80, 6, $book_data->fax, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }  
//left right corners
$pdf->Cell(5, 3, "", "LB", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RB", 1);

/**/

$pdf->Ln(3);
if (file_exists($card_data->card)) {
        
        $info = getimagesize($card_data->card);
        $h = $info[1] ;
        $w = $info[0] ;
        $r = 80/$w;
        $parts = explode('/', $card_data->card);
        $parts = array_reverse($parts);
        $pdf->Image(\Drupal::service('file_system')->realpath($card_data->card), 15, 120, 80, $h*$r);
    }

if (headers_sent()) {
    exit('Unable to stream pdf: headers already sent');
}
header('Cache-Control: private');
header('Content-Type: application/pdf');
header("Content-Disposition: 'attachment'; filename=\"" . $book_data->name . '_' . $card_data->contact_name . "\" ");
$f = str_replace(' ', '_', $card_data->contact_name) . '.pdf';
echo $pdf->Output($f, 'I');
exit;