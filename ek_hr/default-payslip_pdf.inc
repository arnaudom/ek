<?php

class PDF extends FPDF
{
//multicell maxline------------------------------------------------------
    function MultiCell($w,$h,$txt,$border=0,$align='J',$fill=0,$maxline=0)
    {
        //Output text with automatic or explicit line breaks, maximum of $maxlines
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $b=0;
        if($border)
        {
            if($border==1)
            {
                $border='LTRB';
                $b='LRT';
                $b2='LR';
            }
            else
            {
                $b2='';
                if(is_int(strpos($border,'L')))
                    $b2.='L';
                if(is_int(strpos($border,'R')))
                    $b2.='R';
                $b=is_int(strpos($border,'T')) ? $b2.'T' : $b2;
            }
        }
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $ns=0;
        $nl=1;
        while($i<$nb)
        {
            //Get next character
            $c=$s[$i];
            if($c=="\n")
            {
                //Explicit line break
                if($this->ws>0)
                {
                    $this->ws=0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $ns=0;
                $nl++;
                if($border and $nl==2)
                    $b=$b2;
                if ( $maxline  && $nl > $maxline ) 
                    return substr($s,$i);
                continue;
            }
            if($c==' ')
            {
                $sep=$i;
                $ls=$l;
                $ns++;
            }
            $l+=$cw[$c];
            if($l>$wmax)
            {
                //Automatic line break
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                    if($this->ws>0)
                    {
                        $this->ws=0;
                        $this->_out('0 Tw');
                    }
                    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                }
                else
                {
                    if($align=='J')
                    {
                        $this->ws=($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
                        $this->_out(sprintf('%.3f Tw',$this->ws*$this->k));
                    }
                    $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                    $i=$sep+1;
                }
                $sep=-1;
                $j=$i;
                $l=0;
                $ns=0;
                $nl++;
                if($border and $nl==2)
                    $b=$b2;
                if ( $maxline  && $nl > $maxline ) 
                    return substr($s,$i);
            }
            else
                $i++;
        }
        //Last chunk
        if($this->ws>0)
        {
            $this->ws=0;
            $this->_out('0 Tw');
        }
        if($border and is_int(strpos($border,'B')))
            $b.='B';
        $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
        $this->x=$this->lMargin;
        return '';
    }
    function RoundedRect($x, $y, $w, $h, $r, $corners = '1234', $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));

        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        if (strpos($corners, '2')===false)
            $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k,($hp-$y)*$k ));
        else
            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);

        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        if (strpos($corners, '3')===false)
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        if (strpos($corners, '4')===false)
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);

        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        if (strpos($corners, '1')===false)
        {
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y)*$k ));
            $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y)*$k ));
        }
        else
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

//===============================================================================
//Page header
function Header()
{

   
    
}

//Page footer
function Footer()
{
    
    
    //Position at 1.5 cm from bottom
    $this->SetY(-10);
    //Arial italic 8
    $this->SetFont('Arial','I',8);
    //Page number
    //$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    
  }
}
///////////////////////////////////////////////////////////////////////
$pdf=new PDF();
$pdf->SetTitle( t('Payslip') ) ;
$pdf->AliasNbPages();
$pdf->AddPage("P","A4");
$pdf->SetAutoPageBreak(1,0);

$col1_width=100;
$col2_width=30;
$col3_width=50;
$col4_width=0;
// header details


while ($line = $data->fetchAssoc()) {


$c = $line['origin'];


$pdf->SetDrawColor(214, 214, 214);
$pdf->SetLineWidth(0,0);
$pdf->SetFillColor(214, 214, 214);
$pdf->RoundedRect(81, 11, 70, 12, '3.5','13','FD','1');

$pdf->SetFillColor(56, 71, 252);
$pdf->RoundedRect(80, 10, 70, 12, '3.5','13','FD','1');
$pdf->SetDrawColor(192, 192, 192);
//HEADER =================

    //Logo
    if ($company->logo != '') {
    
    $logo = drupal_realpath($company->logo);
      if(file_exists($logo)) {
        $info = getimagesize($logo);
        $h = $info[1]*15/$info[0];
        $pdf->Image($logo,170,12,15,$h);
      }
    
    }
    
    
    $pdf->Ln(1);
    $pdf->SetFont('Arial','B',11);
    //Move to the right
    //$pdf->Cell(110);
    $pdf->Cell(70,4,$company->name,"0",0);
    
    
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(70,4,"",'0',0,"C","0");
    $pdf->Cell(50,4,"",'0',1,"C","0");
    
    $pdf->SetFont('Arial','',6);
    $pdf->Cell(70,3,$company->address1,'0',0);
    $pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(255,255,255);
    $pdf->Cell(70,3,"PAYSLIP",'0',0,"C","0");
$pdf->SetTextColor(0,0,0);    
    $pdf->Cell(50,3,"",'0',1,"C","0");
    
    $pdf->SetFont('Arial','',6);
    $pdf->Cell(70,2,$company->address2,'0','0');
    $pdf->Cell(70,2,"",'0',0,"C","0");
    $pdf->Cell(50,2,"",'0',1,"C","0");    

    $pdf->Cell(70,2,$company->postcode . ', ' . $company->country,'0',0);
    $pdf->Cell(70,2,"",'0',0,"C","0");
    $pdf->Cell(50,2,"",'0',1,"C","0");    

    $pdf->Cell(70,2,"tel: " . $company->tel .',' . 'fax: '. $company->fax,'0',0);
    $pdf->Cell(70,2,"",'0',0,"C","0");
    $pdf->Cell(50,2,"",'0',1,"C","0");

    $pdf->Cell(70,2,"Reg number: ". $company->reg_number,'0',0);
    $pdf->Cell(70,2,"",'0',0,"C","0");
    $pdf->Cell(50,2,"",'0',1,"C","0");    



$col1_width=40;
$col2_width=40;
$col3_width=40;
$col4_width=55;


//INSERT employee DATA
$pdf->Ln(3);
$pdf->SetFont('Arial','',7);
    $pdf->Cell($col1_width,4,'Payroll month:',0,0);
    $pdf->Cell($col2_width,4,$line['month'],1,0);
    $pdf->Cell($col3_width,4,'Employee No:',0,0);
    $pdf->Cell($col4_width,4,$line['id'],1,1);

 
    $pdf->Cell($col1_width,4,'Location:',0,0);
    $pdf->Cell($col2_width,4,$line['location'],1,0);
    $pdf->Cell($col3_width,4,'Employee Name:',0,0);
    $pdf->Cell($col4_width,4,utf8_encode($line['name']),1,1);


    $pdf->Cell($col1_width,4,'Bank ref:',0,0);
    $pdf->Cell($col2_width,4,"$line[bank] - $line[bank_account]",1,0);
    $pdf->Cell($col3_width,4,'IC/Passport No:',0,0);
    $pdf->Cell($col4_width,4,$line['ic_no'],1,1);



$col1_width=25;
$col2_width=75;
$col3_width=25;
$col4_width=25;
$col5_width=40;
$col6_width=0;    
$i=0;
    



//dividing line
$pdf->Ln(2);    
$pdf->Line($pdf->Getx(),$pdf->GetY(),200,$pdf->GetY()) ;  
$pdf->Ln(5);

//coulmn 1
$pdf->SetFont('Arial','B',7);

    $pdf->Cell($col1_width,3,"",0,0);
    $pdf->Cell($col2_width,3,"",0,0);
    $pdf->Cell($col3_width,3,"",0,0);
    $pdf->Cell($col4_width,3,"",0,0);
    $pdf->Cell($col5_width,3,t('EARNINGS DETAILS'),1,1,'C');

$pdf->Ln(2);

if ($line['basic']) { 
    $pdf->Cell($col1_width,3,"",0,0);
    $pdf->Cell($col2_width,3, t('Basic salary') ,0,$i);
$pdf->SetFont('Arial','',7);    
    $pdf->Cell($col3_width,3,'',0,$i);
    $pdf->Cell($col4_width,3,"",0,$i);
    $pdf->Cell($col5_width,3,number_format($line['basic'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['no_payday']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,t('No pay day(s)') ,0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,$line['no_payday'],1,0,'L',$i);
    $pdf->Cell($col5_width,3,'',1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}  
$pdf->SetFillColor(214, 214, 214);
if ($line['n_ot_val']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['LAF1'] ,0,0,'L',$i);
    $pdf->Cell($col3_width,3, $line['n_ot_days'] ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $settings[$c]['LAF1_val'] ,0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['n_ot_val'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['r_day_val']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['LAF2'] ,0,0,'L',$i);
    $pdf->Cell($col3_width,3, $line['r_day'] ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $settings[$c]['LAF2_val'] ,0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['r_day_val'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['ph_day_val']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['LAF3'] ,0,0,'L',$i);
    $pdf->Cell($col3_width,3, $line['ph_day'] ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $settings[$c]['LAF3_val'] ,0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['ph_day_val'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['mc_day_val']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['LAF4'],0,0,'L',$i);
    $pdf->Cell($col3_width,3, $line['mc_day'] ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $settings[$c]['LAF4_val'],0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['mc_day_val'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['xr_hours_val']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);////////////////
    $pdf->Cell($col2_width,3, $settings[$c]['LAF5'], 0,0,'L',$i);
    $pdf->Cell($col3_width,3, $line['xr_hours'] ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $settings[$c]['LAF5_val'],0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['xr_hours_val'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_aw1']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['custom_a'][1] ,0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw1'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_aw2']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][2],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw2'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['custom_aw3']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][3],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw3'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_aw4']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][4],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw4'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}   
}
if ($line['custom_aw5']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][5],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw5'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['custom_aw6']) {  
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][6],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw6'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}  
} 
if ($line['custom_aw7']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][7],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw7'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_aw8']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][8],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw8'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}      
}
if ($line['custom_aw9']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][9],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw9'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}   
}
if ($line['custom_aw10']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][10],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw10'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['custom_aw11']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][11],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw11'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['custom_aw12']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][12],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw12'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_aw13']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_a'][13],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_aw13'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}   
}    
if ($line['commission'] != "0") {
    //LAF6 is commission
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['LAF6'], 0,0,'L',$i);
    $pdf->Cell($col3_width,3, '' ,0,0,'L',$i);
    $pdf->Cell($col4_width,3, $line['turnover'] . ' x ' . $settings[$c]['LAF6_val'],0,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['commission'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
  
}
$pdf->Ln($i);
$pdf->SetFont('Arial','B',7);    
    $pdf->Cell($col1_width,5,"",0,0);
    $pdf->Cell($col2_width,5,t('GROSS SALARY') . ' (' . $line['currency'] . ')',0,0,'L',0);
    $pdf->Cell($col3_width,5,'',0,0,'L',0);
    $pdf->Cell($col4_width,5,"",1,0,'L',0);
$pdf->SetFont('Arial','B',9);    
    $pdf->Cell($col5_width,5,number_format($line['gross'],2),1,1,'R',0);     
    
$pdf->Ln(2); 
$pdf->SetFont('Arial','B',7);
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,"",0,0);
    $pdf->Cell($col3_width,3,"",0,0);
    $pdf->Cell($col4_width,3,"",0,0);
    $pdf->Cell($col5_width,3,t('DEDUCTIONS DETAILS'),1,1,'C');
$pdf->Ln(2); 
 $pdf->SetFont('Arial','',7);
if ($line['advance']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, t('Advance') ,0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['advance'],2),1,1,'R',$i); 
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['custom_d1']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][1],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d1'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_d2']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][2],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d2'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_d3']) {
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][3],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d3'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_d4']) {  
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][4],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d4'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
if ($line['custom_d5']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][5],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d5'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}  
}
if ($line['custom_d6']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][6],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d6'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}   
}
if ($line['custom_d7']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['custom_d'][7],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,"",1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['custom_d7'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['less_hours_val']) {   
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,t('Non worked hours'),0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3, $line['less_hours'] ,1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['less_hours_val'],2),1,1,'R',$i); 
    if ($i=='1') {$i=0;} else {$i=1;}  
}
if($line['epf_yee'] || $line['socso_yee'] || $line['with_yee']) {
 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,'' ,0,0,'L');
    $pdf->Cell($col3_width,3,'',0,0,'C');
    $pdf->Cell($col4_width,3,t('Employer'),1,0,'C');
    $pdf->Cell($col5_width,3,t('Employee'),1,1,'C');
    
    
}
if ($line['epf_yee']) {     
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, $settings[$c]['fund1_name'],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,number_format($line['epf_er'],2) ,1,0,'R',$i);
    $pdf->Cell($col5_width,3,number_format($line['epf_yee'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;} 
}
if ($line['socso_yee']) { 
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['fund2_name'],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,number_format($line['socso_er'],2) ,1,0,'R',$i);
    $pdf->Cell($col5_width,3,number_format($line['socso_yee'],2) ,1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}  
}
if ($line['with_yee']) {   
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3,$settings[$c]['fund3_name'],0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3,number_format($line['with_yer'],2),1,0,'R',$i);
    $pdf->Cell($col5_width,3,number_format($line['with_yee'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}  
}
if ($line['incometax']) {    
    $pdf->Cell($col1_width,3,"",0,0,'L',0);
    $pdf->Cell($col2_width,3, t('Employee Income tax') ,0,0,'L',$i);
    $pdf->Cell($col3_width,3,'',0,0,'L',$i);
    $pdf->Cell($col4_width,3, '' ,1,0,'L',$i);
    $pdf->Cell($col5_width,3,number_format($line['incometax'],2),1,1,'R',$i);
    if ($i=='1') {$i=0;} else {$i=1;}
}
$pdf->Ln($i);
  
$totaldeduc = $line['advance']+$line['custom_d1']+$line['custom_d2']+$line['custom_d3']+$line['custom_d4'];
$totaldeduc =  $totaldeduc+$line['custom_d5']+$line['custom_d6']+$line['custom_d7'];
$totaldeduc =  $totaldeduc+$line['less_hours_val']+$line['epf_yee']+$line['socso_yee']+$line['with_yee']+$line['incometax'];  

$pdf->Ln(1);
$pdf->SetFont('Arial','B',7);    
    $pdf->Cell($col1_width,5,"",0,0);
    $pdf->Cell($col2_width,5,t('TOTAL DEDUCTIONS') . ' ('. $line['currency'] .')',0,0,'L',0);
    $pdf->Cell($col3_width,5,'',0,0,'L',0);
    $pdf->Cell($col4_width,5,"",1,0,'L',0);
$pdf->SetFont('Arial','B',9);    
    $pdf->Cell($col5_width,5,number_format($totaldeduc,2),1,1,'R',0);                              

$pdf->Ln(2);
$pdf->SetFont('Arial','B',7);    
    $pdf->Cell($col1_width,5,"",0,0);
    $pdf->Cell($col2_width,5,t('NET PAY') . ' ('. $line['currency'] .')',0,0,'L',0);
    $pdf->Cell($col3_width,5,'',0,0,'L',0);
    $pdf->Cell($col4_width,5,"",1,0,'L',0);
$pdf->SetFont('Arial','B',9);    
    $pdf->Cell($col5_width,5,"$line[currency] ".number_format($line['nett'],2),1,1,'R',0); 

$pdf->Ln(20);
    $pdf->Cell(30,5,t('NOTE'),1,0); 
    $pdf->Cell(100,5,$line['comment'],1,0,'C');    
    $pdf->Cell(30,5, t('signature') ,0,1,'C');


$pdf->AddPage("P","A4");




}

