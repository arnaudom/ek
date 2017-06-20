<?php

/*
 * Generate barcode code based on TCPDF library
 * @return image or html
 */

function barcode($text = NULL, $format = NULL, $size = [2, 3], $color = NULL, $type = 'html') {

            Switch ($format) {
            Case 'EAN-13':
            Case 'EAN-8':
            Case 'UPC-E':
                $format = str_replace('-', '', $format);
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
    $barcodeobj = new TCPDFBarcode($text, $format);

    if ($type == 'html') {
        return $barcodeobj->getBarcodeHTML($size[0], $size[1], $color);
    }

    if ($type == 'png') {
        return $barcodeobj->getBarcodePNG($size[0], $size[1], array($color, $color, $color));
    }
    
    
/*
 * convert barcode format string
 * @return string
 */    

function encode_string($format = NULL) {
        Switch ($format) {
            Case 'EAN-13':
            Case 'EAN-8':
            Case 'UPC-E':
                $format = str_replace('-', '', $format);
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
        
        return $format;
    }

}
