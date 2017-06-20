<?php

use Drupal\Core\Database\Database;


$query = "SELECT * from {ek_address_book} where id=:id";
$book_data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $id))->fetchObject();
$query = "SELECT * from {ek_address_book_contacts} where id=:id";
$card_data = Database::getConnection('external_db', 'external_db')->query($query, array(':id' => $cid))->fetchObject();

$pdf = new FPDF();
$title = t('Contact card');
$pdf->SetTitle($title);
$pdf->AliasNbPages();
$pdf->AddPage(P, A5);
$pdf->SetMargins(10, 10);


$pdf->SetDrawColor(128, 128, 128);
$clientname = html_entity_decode($book_data->name, ENT_QUOTES);
$address = html_entity_decode($book_data->address, ENT_QUOTES);
$address2 = html_entity_decode($book_data->address2, ENT_QUOTES);

//DISPLAY DATA
//left right corners
$pdf->Cell(5, 3, "", "LT", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RT", 1);

    $pdf->SetFont('Arial', '', 10);

    $pdf->Cell(5, 6, "", 0, 0);
    $pdf->Cell(100, 6, $book_data->name, 0, 0);
    $pdf->Cell(5, 6, "", 0, 1);

    $pdf->Cell(5, 6, "", 0, 0);
    $pdf->Cell(100, 6, $card_data->salutation . ' ' . $card_data->contact_name, 0, 0);
    $pdf->Cell(5, 6, "", 0, 1);
    
    if($card_data->title){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(80, 6,  '(' . $card_data->title . ')', 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }

    $pdf->SetFont('Arial', '', 8);

    $pdf->Cell(10, 5, "", 0, 0);
    $pdf->Cell(100, 5, $book_data->address, 0, 0);
    $pdf->Cell(10, 5, "", 0, 1);

    $pdf->Cell(10, 5, "", 0, 0);
    $pdf->Cell(100, 5, $book_data->address2, 0, 0);
    $pdf->Cell(10, 5, "", 0, 1);

    $pdf->Cell(10, 5, "", 0, 0);
    $pdf->Cell(100, 5, $book_data->postcode . ' ' . $book_data->city, 0, 0);
    $pdf->Cell(10, 5, "", 0, 1);

    $pdf->Cell(10, 5, "", 0, 0);
    $pdf->Cell(100, 5, $book_data->country, 0, 1);
    $pdf->Cell(10, 5, "", 0, 1);

//left right corners
$pdf->Cell(5, 3, "", "LB", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RB", 1);

$pdf->Ln(5);
//DISPLAY contact
//left right corners
$pdf->Cell(5, 3, "", "LT", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RT", 1);

    $pdf->SetFont('Arial', '', 10);
    
    if($card_data->telephone){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Telephone') . ':' , 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 6, $card_data->telephone, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }
    if($card_data->mobilephone){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Mobile') . ':' , 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 6, $card_data->mobilephone, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }
    if($card_data->email){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Email') . ':' , 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 6, $card_data->email, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }    
    if($book_data->fax){
        $pdf->Cell(5, 6, "", 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(20, 6, (STRING)t('Fax') . ':' , 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(80, 6, $book_data->fax, 0, 0);
        $pdf->Cell(5, 6, "", 0, 1);
    }  
//left right corners
$pdf->Cell(5, 3, "", "LB", 0);
$pdf->Cell(100, 3, "", 0, 0);
$pdf->Cell(5, 3, "", "RB", 1);

/**/

$pdf->Ln(10);
if (file_exists($card_data->card)) {
        
        $info = getimagesize($card_data->card);
        $h = $info[1] ;
        $w = $info[0] ;
        $r = 150/$w;
        $parts = explode('/', $card_data->card);
        $parts = array_reverse($parts);
        $pdf->Image($card_data->card, 30, 130, 80);
    }

if (headers_sent()) {
    exit('Unable to stream pdf: headers already sent');
}
header('Cache-Control: private');
header('Content-Type: application/pdf');


header("Content-Disposition: 'attachment'; filename=\"" . $book_data->name . '_' . $card_data->contact_name . "\" ");
$f = str_replace(' ', '_', $card_data->contact_name) . '.pdf';
echo $pdf->Output($f, 'I');
flush();

return TRUE;


