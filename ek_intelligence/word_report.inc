<?php
/*
 * output a word format extraction of report
 * using library Class VsWord.
 * @version 1.0.1
 * @author v.raskin
 * 
 * @data
 *  $data: report data object
 *  $assign: user name
 *  $owner: user name
 *  $fileName: string
 *  $temp: temporary path to save file
 *  $path: final path where file is saved
 * 
 */
if (LIBRARY_PATH) {
    require LIBRARY_PATH . '/vsword/VsWord.php';
} else {
    $error = t('Word library not available, please contact administrator.');
    drupal_set_message(t('Word library not available, please contact administrator.'), 'error');
    $markup = array('#markup' => $error);
   
}

VsWord::autoLoad();
$doc = new VsWord();


//build document
$head = "<table>
            <thead>
            <tbody>  
                <tr>
                    <td>" . t('Reference') . ":</td><td>" . $data->serial . "</td>
                </tr>            
                <tr>
                    <td>" . t('Created') . ":</td><td>" . $data->date . "</td>
                </tr>
                <tr>
                    <td>" . t('Last edit') . ":</td><td>" . date('Y-m-d h:i', $data->edit) . "</td>
                </tr> 
                <tr>
                    <td>" . t('Report writer') . ":</td><td>" . $assign . "</td>
                </tr>";

if ($permission == 1) {
    $head .= "<tr>
                <td>" . t('Report editor') . ":</td><td>" . $owner . "</td>
              </tr>";
}
$head .= "<tr>
            <td>" . t('Description') . ":</td><td>" . $data->description . "</td>
         </tr>";
if ($permission == 1) {
    $head .= "<tr>
                <td>" . t('Project') . ":</td><td>" . $data->pcode . "</td>
             </tr>";
}
$head .= "</tbody></table><br/>";

$report = unserialize($data->report);
//todo
//image are included from url only ex http://path/image.jpg
//not from rel path ex /path/image.jpg
$str = 'src="' . $GLOBALS['base_url'];
$report = str_replace('src="', $str , $report);

$parser = new HtmlParser($doc);
$parser->parse($head);
$parser->parse($report);

$doc->saveAs($temp . '/report.docx');
if(copy($temp . '/report.docx' , $path . '/' . $fileName )) {
unlink($temp . '/report.docx');
        header('Content-Description: File Transfer');
        //header('Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Type: application/octet-stream');
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Content-Length: ' . filesize($path . '/' . $fileName));
        readfile($path . '/' . $fileName);
        exit;
} else {
    $markup = array('#markup' => t('Error copying or reading file. Please try again.'));
}