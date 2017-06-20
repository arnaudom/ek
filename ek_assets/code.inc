<?php

/*
 * Generate QR code based on TCPDF library
 * @return image or html
 */

function qr_code($text = NULL, $format = NULL, $size = 6, $color = NULL , $type = 'html' ) {

    $barcodeobj = new TCPDF2DBarcode($text, $format);
    
    if($type == 'html') { 
        return $barcodeobj->getBarcodeHTML($size, $size , $color);
    }
    
    if($type == 'png') {
        return $barcodeobj->getBarcodePNGdata($size, $size, array($color,$color,$color));
    }
    
}