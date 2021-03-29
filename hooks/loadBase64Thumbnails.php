<?php
//
// Description
// -----------
// This hook will take an image ID or list of image IDs and generate 
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
function ciniki_images_hooks_loadBase64Thumbnails(&$ciniki, $tnid, $args) {

    if( !isset($args['image_ids']) && !isset($args['image_id']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.160', 'msg'=>'Image(s) not specified'));
    }
    if( !isset($args['maxlength']) ) {
        $args['maxlength'] = 150;
    }
    $version = 'thumbnail';
    $padding_color = '#ffffff';
    if( isset($args['padding']) && $args['padding'] == 'yes' ) {
        $version = 'original';
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
    // If the file does not exist, then load information from database, and create cache file
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

    //
    // Get the last updated timestamp
    //
    if( isset($args['image_ids']) ) {
        $strsql = "SELECT ciniki_images.id, "
            . "ciniki_images.uuid, "
            . "ciniki_images.title, "
            . "ciniki_images.original_filename, "
            . "IF(ciniki_images.last_updated > ciniki_image_versions.last_updated, ciniki_images.last_updated, ciniki_image_versions.last_updated) AS last_updated "
            . "FROM ciniki_images, ciniki_image_versions "
            . "WHERE ciniki_images.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['image_ids']) . ") "
            . "AND ciniki_images.id = ciniki_image_versions.image_id "
            . "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, $version) . "' "
            . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_image_versions.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
    } elseif( isset($args['image_id']) ) {
        $strsql = "SELECT ciniki_images.uuid, "
            . "ciniki_images.title, "
            . "ciniki_images.original_filename, "
            . "IF(ciniki_images.last_updated > ciniki_image_versions.last_updated, ciniki_images.last_updated, ciniki_image_versions.last_updated) AS last_updated "
            . "FROM ciniki_images, ciniki_image_versions "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
            . "AND ciniki_images.id = ciniki_image_versions.image_id "
            . "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, $version) . "' "
            . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_image_versions.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
    }
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.images', array(
        array('container'=>'images', 'fname'=>'id', 
            'fields'=>array('id', 'image_id'=>'id', 'uuid', 'title', 'original_filename', 'last_updated', 'last_updated'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.161', 'msg'=>'Unable to load images', 'err'=>$rc['err']));
    }
    $images = isset($rc['images']) ? $rc['images'] : array();

    //
    // Load each image and setup base64 encoded version
    //
    foreach($images as $iid => $image) {
        // Convert last_updated to timestamp
        $date = new DateTime($image['last_updated'], new DateTimeZone('UTC'));
        $image['last_updated'] = $date->format('U');
        $img_uuid = $image['uuid'];

        $storage_filename = $tenant_storage_dir . '/ciniki.images/' . $img_uuid[0] . '/' . $img_uuid;
        $cache_filename = $tenant_cache_dir . '/ciniki.images/' . $img_uuid[0] . '/' . $img_uuid . '/t' . $args['maxlength'] . '.jpg';

        //
        // Check if cached version is there, and there hasn't been any updates
        //
        $utc_offset = date_offset_get(new DateTime);
        if( file_exists($cache_filename) ) {
            clearstatcache(TRUE, $cache_filename);
        }
        if( file_exists($cache_filename)
            && (filemtime($cache_filename)) >= $image['last_updated'] 
            && (!isset($args['last_updated']) || (filemtime($cache_filename)) >= $args['last_updated'])
            ) {
            $imgblob = fread(fopen($cache_filename, 'r'), filesize($cache_filename));
            $images[$iid]['image_data'] = 'data:image/jpg;base64,' . base64_encode($imgblob);
            continue;
        }

        if( !file_exists($storage_filename) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.162', 'msg'=>'Image missing'));
        }

        try {
            $imagek = new Imagick($storage_filename);
        } catch (Exception $e) {
            $imagek->newImage($args['maxlength'], $args['maxlength'], "#ffffff");
        }
        
        $imagek->setBackgroundColor("#ffffff");
        $imagek->setImageFormat("jpeg");

        //
        // Padded to square image
        //
        if( isset($args['padding']) && $args['padding'] == 'yes' ) {
            if( $imagek->getImageWidth() > $imagek->getImageHeight() ) {
                $imagek->borderImage($padding_color, 0, ($imagek->getImageWidth() - $imagek->getImageHeight())/2);
            } elseif( $imagek->getImageHeight() > $imagek->getImageWidth() ) {
                $imagek->borderImage($padding_color, ($imagek->getImageHeight() - $imagek->getImageWidth())/2, 0);
            }
        }

        //
        // Get the actions to be applied
        //
        $strsql = "SELECT sequence, action, params "
            . "FROM ciniki_image_actions "
            . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image['image_id']) . "' "
            . "AND version = '" . ciniki_core_dbQuote($ciniki, $version) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "ORDER BY sequence ";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'item');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.163', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
        }
        $actions = isset($rc['rows']) ? $rc['rows'] : array();
        foreach($actions as $action) {
            // Crop
            if( $action['action'] == 1 ) {
                $params = explode(',', $action['params']);
                $imagek->cropImage($params[0], $params[1], $params[2], $params[3]);
            }
        }

        $imagek->thumbnailImage($args['maxlength'], $args['maxlength'], true);

        //
        // Check directory exists
        //
        if( !file_exists(dirname($cache_filename)) ) {
            if( mkdir(dirname($cache_filename), 0755, true) === false ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.164', 'msg'=>'Unable to find image', 'pmsg'=>'Unable to create cache directory'));
            }
        }

        //
        // Write the image to the cache file
        //
        $h = fopen($cache_filename, 'w');
        if( $h ) {
            $imagek->setImageCompressionQuality(50);
            fwrite($h, $imagek->getImageBlob());
            fclose($h);
            // Set the filemtime to the proper UTC timestamp, don't rely on the filesystem to be correct
            $dt = new DateTime('now', new DateTimeZone('UTC'));
            touch($cache_filename, $dt->getTimestamp());
        }

        //
        // Add the image_data to the images_array
        //
        error_log('encode');
        $images[$iid]['image_data'] = 'data:image/jpg;base64,' . base64_encode($imagek->getImageBlob());
    }

    return array('stat'=>'ok', 'images'=>$images);
}
?>
