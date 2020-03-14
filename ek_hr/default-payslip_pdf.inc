<?php

class EKPDF extends TCPDF {

    //Page header
    function Header() {
        $data = $this->getHeaderData();
        $this->company = $data['title'];
        //Logo
        if ($this->company->logo != '') {

            $logo = \Drupal::service('file_system')->realpath($this->company->logo);
            if (file_exists($logo)) {
                $info = getimagesize($logo);
                $h = $info[1] * 15 / $info[0];
                $this->Image($logo, 170, 8, 15, $h);
            }
        }
        
        $this->SetDrawColor(214, 214, 214);
        $this->SetLineWidth(0, 0);
        $this->SetFillColor(214, 214, 214);
        $this->RoundedRect(81, 8, 70, 12, '3.5', '0101', 'FD', '1');

        $this->SetFillColor(156, 164, 254);
        $this->RoundedRect(80, 7, 70, 12, '3.5', '0101', 'DF', '1');
        $this->SetDrawColor(192, 192, 192);
        $this->Ln(1);
        $this->SetFont('helvetica','',10);
        $this->Cell(5);
        $this->Cell(50,3,$this->company->name,0,1);
        $this->SetFont('helvetica','',6);
        if ($this->company->reg_number) {
            $this->Cell(5);
            $this->Cell(60,3,"(".$this->company->reg_number .")",0,0);
            $this->SetTextColor(255,255,255);
            $this->SetFont('helvetica','',10);
            $this->Cell(70,3,t("PAYSLIP"),'0',1,"C");
            $this->SetTextColor(0,0,0);
            $this->SetFont('helvetica','',6);
        } else {
            $this->Cell(65);
            $this->SetTextColor(255,255,255);
            $this->SetFont('helvetica','',10);
            $this->Cell(70,3,t("PAYSLIP"),'0',1,"C");
            $this->SetTextColor(0,0,0);
            $this->SetFont('helvetica','',6);
        }
        if ($this->company->address1) {
            $this->Cell(5);    
            $this->Cell(60,3, $this->company->address1,0,1);
        } 
        if ($this->company->address2) {
             $this->Cell(5);    
            $this->Cell(60,3, $this->company->address2,0,1);
        }
        $next = '';
        if ($this->company->postcode) {
            $next .= $this->company->postcode;
        }
        if($this->company->city){
            $next .= " " . $this->company->city;
        }
        if($this->company->country){
            $next .= ", " . $this->company->country;
        }
        $this->Cell(5);
        $this->Cell(50,3, $next ,0,1);
            
        if ($this->company->telephone) {
            $this->Cell(5);
            $this->Cell(8,3,t("tel:"). $this->company->telephone,0,1);
        }
        if ($this->company->fax) {
            $this->Cell(5);
            $this->Cell(8,3,t("fax:"). $this->company->telephone,0,1);
        }
    }

    //Page footer
    function Footer() {
        //Position at 1.5 cm from bottom
        $this->SetY(-10);
        //helvetica italic 8
        $this->SetFont('helvetica', 'I', 8);
        //Page number
        $this->Cell(0,5, t('Page') . ' ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'R');
    }

}

// create new PDF document
$pdf = new EKPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator($company->name);
$pdf->SetAuthor(\Drupal::currentUser()->getAccountName());
$pdf->SetTitle(t('Payslip'));
$pdf->SetSubject(t('Payslip'));
$pdf->SetKeywords(t('Payslip'));

// set default header data
$pdf->SetHeaderData('', '',$company, '');

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


$pdf->SetAutoPageBreak(1, 0);

$col1_width = 100;
$col2_width = 30;
$col3_width = 50;
$col4_width = 0;


while ($line = $data->fetchAssoc()) {
$pdf->AddPage("P", "A4");
    $c = $line['origin'];

    $pdf->Ln(10);
    $margin = 10;
    $col1_width = 25;
    $col2_width = 55;
    $col3_width = 25;
    $col4_width = 55;
    $pdf->SetLineStyle(['width' => 0.1, 'cap' => 'square', 'join' => 'miter', 'dash' => 0, 'color' => [128, 128, 128]]);

//INSERT employee DATA
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 4, t('Payroll month') . ':', 0, 0);
    $pdf->Cell($col2_width, 4, $line['month'], 1, 0);
    $pdf->Cell($col3_width, 4, t('Employee No') . ':', 0, 0);
    $pdf->Cell($col4_width, 4, ($line['custom_id'] != '') ? $line['custom_id'] : $line['id'], 1, 1);

    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 4, t('Location') .':', 0, 0);
    $pdf->Cell($col2_width, 4, $line['location'], 1, 0);
    $pdf->Cell($col3_width, 4, t('Employee Name') .':', 0, 0);
    $pdf->Cell($col4_width, 4, utf8_encode($line['name']), 1, 1);

    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 4, t('Bank ref') .':', 0, 0);
    $pdf->Cell($col2_width, 4, "$line[bank] - $line[bank_account]", 1, 0);
    $pdf->Cell($col3_width, 4, t('IC/Passport No.') .':', 0, 0);
    $pdf->Cell($col4_width, 4, $line['ic_no'], 1, 1);



    $col1_width = 20;
    $col2_width = 50;
    $col3_width = 25;
    $col4_width = 25;
    $col5_width = 40;
    $col6_width = 0;
    $i = 0;
    $pdf->SetFillColor(214, 214, 214);
//dividing line
    $pdf->Ln(2);
    $pdf->Line($pdf->Getx(), $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);

//coulmn 1
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width+$col2_width+$col3_width+$col4_width);
    $pdf->Cell($col5_width, 3, t('EARNINGS DETAILS'), 1, 1, 'C');

    $pdf->Ln(2);

    if ($line['basic']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0);
        $pdf->Cell($col2_width, 3, t('Basic salary'), 0, $i);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell($col3_width, 3, '', 0, $i);
        $pdf->Cell($col4_width, 3, "", 0, $i);
        $pdf->Cell($col5_width, 3, number_format($line['basic'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
        
    }
    if ($line['no_payday']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, t('No pay day(s)'), 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $line['no_payday'], 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, '', 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    $pdf->SetFillColor(214, 214, 214);
    if ($line['n_ot_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF1'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, $line['n_ot_days'], 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $settings[$c]['LAF1_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['n_ot_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['r_day_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF2'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, $line['r_day'], 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $settings[$c]['LAF2_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['r_day_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['ph_day_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF3'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, $line['ph_day'], 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $settings[$c]['LAF3_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['ph_day_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['mc_day_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF4'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, $line['mc_day'], 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $settings[$c]['LAF4_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['mc_day_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['xr_hours_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF5'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, $line['xr_hours'], 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $settings[$c]['LAF5_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['xr_hours_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw1']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][1], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw1'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw2']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][2], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw2'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw3']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][3], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw3'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw4']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][4], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw4'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw5']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][5], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw5'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw6']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][6], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw6'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw7']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][7], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw7'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw8']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][8], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw8'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw9']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][9], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw9'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw10']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][10], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw10'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw11']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][11], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw11'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw12']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][12], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw12'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_aw13']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_a'][13], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_aw13'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['commission'] != "0") {
        //LAF6 is commission
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['LAF6'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $line['turnover'] . ' x ' . $settings[$c]['LAF6_val'], 0, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['commission'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    $pdf->Ln($i);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 5, "", 0, 0);
    $pdf->Cell($col2_width, 5, t('GROSS SALARY') . ' (' . $line['currency'] . ')', 0, 0, 'L', 0);
    $pdf->Cell($col3_width, 5, '', 0, 0, 'L', 0);
    $pdf->Cell($col4_width, 5, "", 1, 0, 'L', 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col5_width, 5, number_format($line['gross'], 2), 1, 1, 'R', 0);

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width+$col2_width+$col3_width+$col4_width);
    $pdf->Cell($col5_width, 3, t('DEDUCTIONS DETAILS'), 1, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 7);
    if ($line['advance']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, t('Advance'), 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['advance'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d1']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][1], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d1'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d2']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][2], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d2'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d3']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][3], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d3'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d4']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][4], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d4'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d5']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][5], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d5'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d6']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][6], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d6'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['custom_d7']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['custom_d'][7], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, "", 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['custom_d7'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['less_hours_val']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, t('Non worked hours'), 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, $line['less_hours'], 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['less_hours_val'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['epf_yee'] || $line['socso_yee'] || $line['with_yee']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, '', 0, 0, 'L');
        $pdf->Cell($col3_width, 3, '', 0, 0, 'C');
        $pdf->Cell($col4_width, 3, t('Employer'), 1, 0, 'C');
        $pdf->Cell($col5_width, 3, t('Employee'), 1, 1, 'C');
    }
    if ($line['epf_yee']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['fund1_name'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, number_format($line['epf_er'], 2), 1, 0, 'R', $i);
        $pdf->Cell($col5_width, 3, number_format($line['epf_yee'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['socso_yee']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['fund2_name'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, number_format($line['socso_er'], 2), 1, 0, 'R', $i);
        $pdf->Cell($col5_width, 3, number_format($line['socso_yee'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['with_yee']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, $settings[$c]['fund3_name'], 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, number_format($line['with_yer'], 2), 1, 0, 'R', $i);
        $pdf->Cell($col5_width, 3, number_format($line['with_yee'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    if ($line['incometax']) {
        $pdf->Cell($margin);
        $pdf->Cell($col1_width, 3, "", 0, 0, 'L', 0);
        $pdf->Cell($col2_width, 3, t('Employee Income tax'), 0, 0, 'L', $i);
        $pdf->Cell($col3_width, 3, '', 0, 0, 'L', $i);
        $pdf->Cell($col4_width, 3, '', 1, 0, 'L', $i);
        $pdf->Cell($col5_width, 3, number_format($line['incometax'], 2), 1, 1, 'R', $i);
        $i = ($i == '1') ? 0:1;
    }
    $pdf->Ln($i);

    $totaldeduc = $line['advance'] + $line['custom_d1'] + $line['custom_d2'] + $line['custom_d3'] + $line['custom_d4'];
    $totaldeduc = $totaldeduc + $line['custom_d5'] + $line['custom_d6'] + $line['custom_d7'];
    $totaldeduc = $totaldeduc + $line['less_hours_val'] + $line['epf_yee'] + $line['socso_yee'] + $line['with_yee'] + $line['incometax'];

    $pdf->Ln(1);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 5, "", 0, 0);
    $pdf->Cell($col2_width, 5, t('TOTAL DEDUCTIONS') . ' (' . $line['currency'] . ')', 0, 0, 'L', 0);
    $pdf->Cell($col3_width, 5, '', 0, 0, 'L', 0);
    $pdf->Cell($col4_width, 5, "", 1, 0, 'L', 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col5_width, 5, number_format($totaldeduc, 2), 1, 1, 'R', 0);

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 5, "", 0, 0);
    $pdf->Cell($col2_width, 5, t('NET PAY') . ' (' . $line['currency'] . ')', 0, 0, 'L', 0);
    $pdf->Cell($col3_width, 5, '', 0, 0, 'L', 0);
    $pdf->Cell($col4_width, 5, "", 1, 0, 'L', 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col5_width, 5, "$line[currency] " . number_format($line['nett'], 2), 1, 1, 'R', 0);

    $pdf->Ln(20);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 5, t('NOTE'), "B", 0);
    $pdf->Cell($col2_width+$col3_width+$col4_width, 5, $line['comment'], "B", 1, 'L');
    $pdf->Cell($margin);
    $pdf->Cell($col1_width, 5, t('signature'), 0, 1, 'L');


}

