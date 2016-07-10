<?php
//
// Description
// -----------
// This script will move images from the database to ciniki-storage
//

//
// Initialize Ciniki by including the ciniki_api.php
//
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
    $ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}
// loadMethod is required by all function to ensure the functions are dynamically loaded
require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
    error_log("unable to initialize core");
    exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];

//
// Check if there was an override to the storage_dir.  This is used when updating a mounted storage tree
//
if( isset($argv[2]) && $argv[2] != '' ) {
    $ciniki['config']['ciniki.core']['storage_dir'] = $argv[2];
}

//
// Check the cache directory exists
//
if( !isset($ciniki['config']['ciniki.core']['storage_dir']) ) {
    error_log('CACHE-ERR[storageUpdate.php]: config error, storage_dir not set.');
    exit(0);
}

if( !is_dir($ciniki['config']['ciniki.core']['storage_dir']) ) {
    error_log('CACHE-ERR[storageUpdate.php]: storage_dir does not exist.');
    exit(0);
}

ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheOriginal');

//
// Get the list of images
//
$strsql = "SELECT "
    . "ciniki_businesses.id AS business_id, "
    . "IFNULL(ciniki_businesses.uuid, '0') AS business_uuid, "
    . "ciniki_images.id AS image_id, "
    . "ciniki_images.uuid AS image_uuid, "
    . "UNIX_TIMESTAMP(ciniki_images.last_updated) AS last_updated "
    . "FROM ciniki_images "
    . "LEFT JOIN ciniki_businesses ON (ciniki_images.business_id = ciniki_businesses.id) "
    . "";
/*
if( !isset($argv[1]) ) {
    // Default to last 24 hours of images
    $strsql .= "WHERE (UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(ciniki_images.last_updated)) < 86400 ";
} elseif( $argv[1] > 0 ) {
    $strsql .= "WHERE (UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(ciniki_images.last_updated)) < '" . ciniki_core_dbQuote($ciniki, $argv[1]) . "' ";
}
*/

$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image'); 
if( $rc['stat'] != 'ok' ) {
    error_log('CACHE-ERR[storageUpdate.php]: Unable to get list of images');
    exit(0);
}
if( isset($rc['rows']) ) {
    $images = $rc['rows'];
} else {
    $images = array();
}

$utc_offset = date_offset_get(new DateTime);
foreach($images as $iid => $image) {
    $storage_filename = $ciniki['config']['ciniki.core']['storage_dir'] 
        . '/'
        . $image['business_uuid'][0] . '/' . $image['business_uuid']
        . '/ciniki.images/' 
        . $image['image_uuid'][0] . '/' . $image['image_uuid'];

    if( !file_exists(dirname($storage_filename)) ) {
        if( mkdir(dirname($storage_filename), 0755, true) === false ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3359', 'msg'=>'Unable to find image', 'pmsg'=>'Unable to create storage directory'));
        }
    }

    //
    // Check if it already exists
    //
    if( !file_exists($storage_filename)
        || (filemtime($storage_filename) - $utc_offset) < $image['last_updated'] ) {
        
        //
        // Load the image into a blob
        //
        $strsql = "SELECT ciniki_images.title, "
            . "ciniki_images.image "
            . "FROM ciniki_images "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image['image_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3358', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']) ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3357', 'msg'=>'Unable to render image'));
        }

        //
        // Load the image in Imagemagic
        //
        $image = new Imagick();
        $image->readImageBlob($rc['image']['image']);

        //
        // Write to disk
        //
        $h = fopen($storage_filename, 'w');
        if( $h ) {
            fwrite($h, $image->getImageBlob());
            fclose($h);
        } else {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3346', 'msg'=>'Unable to add image'));
        }
    }
}

exit(0);
?>
