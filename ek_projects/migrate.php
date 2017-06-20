<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';

try {
$query = "RENAME TABLE `project_documents` TO `ek_project_documents`";
Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>project_documents table renamed';
} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$query = "
ALTER TABLE `ek_project_documents` 
  COMMENT='documents attached to projects',
  CHANGE COLUMN `pcode` `pcode` VARCHAR(45) NULL DEFAULT NULL COMMENT 'project code reference' AFTER `id`,
  ADD COLUMN `fid` INT(10) NULL DEFAULT NULL COMMENT 'file managed ID, option' AFTER `pcode`,
  CHANGE COLUMN `doc_name` `filename` VARCHAR(255) NULL DEFAULT NULL COMMENT 'name of file' AFTER `fid`,
  ADD COLUMN `uri` VARCHAR(255) NULL DEFAULT NULL COMMENT 'the stream uri of file' AFTER `filename`,
  CHANGE COLUMN `doc_type` `folder` VARCHAR(20) NULL DEFAULT NULL COMMENT 'folder location' AFTER `uri`,
  CHANGE COLUMN `doc_comment` `comment` VARCHAR(255) NULL DEFAULT NULL AFTER `folder`,
  CHANGE COLUMN `doc_date` `date` VARCHAR(50) NULL DEFAULT NULL COMMENT 'a timestamp' AFTER `comment`,
  ADD COLUMN `size` VARCHAR(50) NULL DEFAULT NULL COMMENT 'file size' AFTER `date`,
  ADD UNIQUE INDEX `Index 2` (`uri`)
";

Database::getConnection('external_db', 'external_db')->query($query);
$markup .= '<br/>project_documents table altered';

} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


try {
$markup .= "<hr><br>documents<br/>";

$query = "select * from {ek_project_documents}";
$data = Database::getConnection('external_db', 'external_db')->query($query);
$output = '';
//$finfo = new finfo(FILEINFO_MIME_TYPE);

        while ($d = $data->fetchObject()) {

        //1 change pcode format
        $pcode = str_replace('-', '_' , $d->pcode);
        $pcode = str_replace('/', '-' , $pcode);


        $code = explode('-',$pcode);
        $folder = $code[4];


        $stamp = strtotime($d->date);
        //$uuid = Drupal\Component\Uuid\Php::generate();
        $uri = 'private://projects/documents/'.$folder . '/'. $d->filename;
        //$file = $finfo->file($uri);
        $size = filesize($uri);
        if ($size == '') $size = 0;
        if($d->comment == 'deleted' ) {
            $fid = '0';
            $uri = NULL;
        } else {
            $fid = NULL;
        }
        $fields = array(
          '`pcode`' => $pcode,
          '`uri`' => $uri ,
          '`fid`' => $fid,
          '`size`' => $size,
          '`date`' => $stamp,
        );

        //$fid = db_insert('file_managed')->fields($fields)->execute();

        Database::getConnection('external_db', 'external_db')
          ->update('ek_project_documents')->fields($fields)->condition('id', $d->id)->execute();

        $markup .= $d->id . ': ' .$d->pcode . '| ' . $folder . '|  ' .$stamp . '| ' .$uri . '| '.$size.'<br/>';


        }
        $markup .= '<br/>project_documents table updated';

} catch (Exception $e) {
  $markup .= '<br/>Caught exception: '.  $e->getMessage() . "\n";
}


