<?php
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

$markup = '';



try {
 
  // update the documents uri in table
  // location private://finance/receipt
  
  
  $query = "select id,coid, asset_doc, asset_pic from {ek_assets} ";
  $data = Database::getConnection('external_db', 'external_db')->query($query);

  while ($d = $data->fetchObject()) {
    $dir = 'private://assets/'. $d->coid . '/';
    if($d->asset_doc) {
    $uri1 = 'private://assets/'. $d->coid . '/' . addslashes($d->asset_doc) ;
    file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    file_unmanaged_copy('private://assets/' . $d->asset_doc, $uri1);
    $markup .= '<br/>' . $uri1;
    } else {
        $uri1 = '';
    }
    if($d->asset_pic) {
    $uri2 = 'private://assets/'. $d->coid . '/' . addslashes($d->asset_pic) ;
    file_prepare_directory($dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    file_unmanaged_copy('private://assets/' . $d->asset_pic, $uri2);
    } else {
        $uri2 = '';
    }

    $query = 'update ek_assets set asset_doc = :a WHERE id = :id';
    $a = [':a' => $uri1, ':id' => $d->id];
    Database::getConnection('external_db', 'external_db')->query($query, $a);
    $query = 'update ek_assets set asset_pic = :a WHERE id = :id';
    $a = [':a' => $uri2, ':id' => $d->id];
    Database::getConnection('external_db', 'external_db')->query($query, $a);
  }

  $markup .= "<br/>Assets attachments updated.";
  
} catch (Exception $e) {
    $markup .= '<br/><b>Caught exception: '.  $e->getMessage() . "</b>\n";
}