<?php
//
// Description
// -----------
// This function will render an image and apply all actions to the image
// from the ciniki_image_actions table.
//
// Arguments
// ---------
// ciniki:
// image_id:        The ID of the image to load.
// maxlength:       The maximum length of either side of the image.
// 
// Returns
// -------
//
function ciniki_images_hooks_loadThumbnail(&$ciniki, $tnid, $args) {

    if( !isset($args['image_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.18', 'msg'=>'Image not specified'));
    }
    if( !isset($args['maxlength']) ) {
        $args['maxlength'] = 150;
    }

    //
    // Get the tenant cache directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'cacheDir');
    $rc = ciniki_tenants_hooks_cacheDir($ciniki, $tnid, array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tenant_cache_dir = $rc['cache_dir'];
    
    //
    // Get the tenant storage directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'storageDir');
    $rc = ciniki_tenants_hooks_storageDir($ciniki, $tnid, array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tenant_storage_dir = $rc['storage_dir'];
    
    //
    // Get the last updated timestamp
    //
    $strsql = "SELECT ciniki_images.uuid, ciniki_images.title, "
        . "IF(ciniki_images.last_updated > ciniki_image_versions.last_updated, ciniki_images.last_updated, ciniki_image_versions.last_updated) AS last_updated "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = 'thumbnail' "
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_image_versions.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.19', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.20', 'msg'=>'Unable to render image'));
    }
    // Convert last_updated to timestamp
    $date = new DateTime($rc['image']['last_updated'], new DateTimeZone('UTC'));
    $rc['image']['last_updated'] = $date->format('U');
    $img = $rc['image'];
    $img_uuid = $rc['image']['uuid'];

    $storage_filename = $tenant_storage_dir . '/ciniki.images/'
        . $img_uuid[0] . '/' . $img_uuid;
    $cache_filename = $tenant_cache_dir . '/ciniki.images/'
        . $img_uuid[0] . '/' . $img_uuid . '/t' . $args['maxlength'] . '.jpg';

    //
    // Check if cached version is there, and there hasn't been any updates
    //
    $utc_offset = date_offset_get(new DateTime);
    if( file_exists($cache_filename) ) {
        clearstatcache(TRUE, $cache_filename);
    }
    if( file_exists($cache_filename)
        && (filemtime($cache_filename)) >= $img['last_updated'] 
        && (!isset($args['last_updated']) || (filemtime($cache_filename)) >= $args['last_updated'])
//      && (filemtime($cache_filename) - $utc_offset) > $img['last_updated'] 
//      && (!isset($args['last_updated']) || (filemtime($cache_filename) - $utc_offset) > $args['last_updated'])
        ) {
        $imgblog = fread(fopen($cache_filename, 'r'), filesize($cache_filename));
        return array('stat'=>'ok', 'image'=>$imgblog);
    }

    //
    // If the file does not exist, then load information from database, and create cache file
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

    if( file_exists($storage_filename) ) {
        try {
            $image = new Imagick($storage_filename);
        } catch (Exception $e) {
            $image->newImage(500, 500, "#ffffff");
        }
    } else {
        //
        // Get the image data from the database for this version
        //
        $strsql = "SELECT ciniki_images.title, "
            . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) AS last_updated, "
            . "ciniki_images.image "
            . "FROM ciniki_images, ciniki_image_versions "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
            . "AND ciniki_images.id = ciniki_image_versions.image_id "
            . "AND ciniki_image_versions.version = 'thumbnail' "
            . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_image_versions.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.21', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.22', 'msg'=>'Unable to render image'));
        }

        //
        // Load the image in Imagemagic
        //
        $image = new Imagick();
        if( $rc['image']['image'] != '' ) {
            $image->readImageBlob($rc['image']['image']);
        } else {
            $image->newImage(500, 500, "#ffffff");
        }
//      $last_updated = $rc['image']['last_updated'];
    }

    $image->setFormat("jpeg");
    $image->setImageFormat("jpeg");

    //
    // Get the actions to be applied
    //
    $strsql = "SELECT sequence, action, params "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND version = 'thumbnail' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY sequence ";
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');   
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.23', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
    }
    $dh = $rc['handle'];

    $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    while( isset($result['row']) ) {
        // Crop
        if( $result['row']['action'] == 1 ) {
            $params = explode(',', $result['row']['params']);
            try {
                $image->cropImage($params[0], $params[1], $params[2], $params[3]);
            } catch (Exception $e) {
                error_log(print_r($e, true));
            }
        }

        // Grab the next row
        $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    }

    $image->thumbnailImage($args['maxlength'], 0);

    //
    // Check if they image is marked as sold, and add red dot
    //
    if( isset($args['reddot']) && $args['reddot'] == 'yes' ) {
        $draw = new ImagickDraw();
        $draw->setFillColor('red');
        $draw->setStrokeColor(new ImagickPixel('white') );
        $size = $args['maxlength']/20;
        $draw->circle($args['maxlength']-($size*2), $args['maxlength']-($size*2), $args['maxlength']-$size, $args['maxlength']-$size);
        $image->drawImage($draw);
    }

    //
    // Check directory exists
    //
    if( !file_exists(dirname($cache_filename)) ) {
        if( mkdir(dirname($cache_filename), 0755, true) === false ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.24', 'msg'=>'Unable to find image', 'pmsg'=>'Unable to create cache directory'));
        }
    }

    //
    // Write the image to the cache file
    //
    $h = fopen($cache_filename, 'w');
    if( $h ) {
        $image->setImageCompressionQuality(50);
        fwrite($h, $image->getImageBlob());
        fclose($h);
        // Set the filemtime to the proper UTC timestamp, don't rely on the filesystem to be correct
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        touch($cache_filename, $dt->getTimestamp());
    }

    return array('stat'=>'ok', 'image'=>$image->getImageBlob());
}
?>
