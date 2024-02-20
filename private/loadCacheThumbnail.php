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
// tnid:     The ID of the tenant the image is attached to.
// image_id:        The ID of the image to load.
// maxlength:       The maximum length of either side of the image.
// 
// Returns
// -------
//
function ciniki_images_loadCacheThumbnail(&$ciniki, $tnid, $image_id, $maxlength, $maxheight=0) {

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
    $strsql = "SELECT ciniki_images.uuid, ciniki_images.title, ciniki_images.original_filename, "
        . "IF(ciniki_images.last_updated > ciniki_image_versions.last_updated, UNIX_TIMESTAMP(ciniki_images.last_updated), UNIX_TIMESTAMP(ciniki_image_versions.last_updated)) AS last_updated "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = 'thumbnail' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.83', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.84', 'msg'=>'Unable to render image'));
    }
    $img = $rc['image'];
    $img_uuid = $rc['image']['uuid'];

    $storage_filename = $tenant_storage_dir . '/ciniki.images/'
        . $img_uuid[0] . '/' . $img_uuid;
    $cache_filename = $tenant_cache_dir . '/ciniki.images/'
        . $img_uuid[0] . '/' . $img_uuid . '/t' . $maxlength . '.jpg';

    //
    // Check if cached version is there, and there hasn't been any updates
    //
    $utc_offset = date_offset_get(new DateTime);
    if( file_exists($cache_filename) && filemtime($cache_filename) >= $img['last_updated'] ) {
        $imgblob = fread(fopen($cache_filename, 'r'), filesize($cache_filename));
        return array('stat'=>'ok', 'image'=>$imgblob, 'last_updated'=>$img['last_updated'], 'original_filename'=>$img['original_filename']);
    }

    //
    // If the file does not exist, then load information from database, and create cache file
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

    $image = null;
    if( file_exists($storage_filename) ) {
        try {
            $image = new Imagick($storage_filename);
        } catch(Exception $e) {
            unlink($storage_filename);
        }
    } 
    if( $image == null ) {
        //
        // Get the image data from the database for this version
        //
        $strsql = "SELECT ciniki_images.title, "
            . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) AS last_updated, "
            . "ciniki_images.image "
            . "FROM ciniki_images, ciniki_image_versions "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "AND ciniki_images.id = ciniki_image_versions.image_id "
            . "AND ciniki_image_versions.version = 'thumbnail' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.85', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']['image']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.86', 'msg'=>'Unable to render image'));
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
    }

    $image->setImageFormat("jpeg");

    //
    // Get the actions to be applied
    //
    $strsql = "SELECT sequence, action, params FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND version = 'thumbnail' "
        . "ORDER BY sequence ";
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');   
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.87', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
    }
    $dh = $rc['handle'];

    $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    while( isset($result['row']) ) {
        // Crop
        if( $result['row']['action'] == 1 ) {
            $params = explode(',', $result['row']['params']);
            try {
                $image->cropImage($params[0], $params[1], $params[2], $params[3]);
            } catch(Exception $e) {
                $image->newImage(1000, 1000, "#ffffff");
                $image->setImageFormat("jpeg");
                $draw = new ImagickDraw();
                $draw->setFillColor('black');
                $draw->setFontSize(275);
                $image->annotateImage($draw, 50, 575, 0, "ERROR");
            }
        }

        // Grab the next row
        $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    }

    $image->thumbnailImage($maxlength, $maxheight);

    //
    // Check directory exists
    //
    if( !file_exists(dirname($cache_filename)) ) {
        if( mkdir(dirname($cache_filename), 0755, true) === false ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.88', 'msg'=>'Unable to find image', 'pmsg'=>'Unable to create cache directory'));
        }
    }

    //
    // Write the image to the cache file
    //
    $h = fopen($cache_filename, 'w');
    if( $h ) {
        $image->setImageCompressionQuality(40);
        fwrite($h, $image->getImageBlob());
        fclose($h);
        //
        // Update the file time to UTC so checking cache timestamps works properly in any timezone
        //
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        touch($cache_filename, $dt->getTimestamp());
    }

    return array('stat'=>'ok', 'image'=>$image->getImageBlob(), 'last_updated'=>$img['last_updated'], 'original_filename'=>$img['original_filename']);
}
?>
