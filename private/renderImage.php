<?php
//
// Description
// -----------
// This function will render an image and apply all actions to the image
// from the ciniki_image_actions table.
//
// Info
// ----
// Status:          defined
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// Returns
// -------
//
function ciniki_images_renderImage($ciniki, $image_id, $version, $maxwidth, $maxheight) {


    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

    //
    // Get the last updated
    //
    $strsql = "SELECT ciniki_images.uuid, ciniki_images.business_id, ciniki_images.title, "
        . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) as last_updated, "
        . "ciniki_images.original_filename "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, $version) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3356', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3289', 'msg'=>'Unable to render image'));
    }
    $last_updated = $rc['image']['last_updated'];
    $original_filename = $rc['image']['original_filename'];
    $title = $rc['image']['title'];
    $image = $rc['image'];

    //
    // Get the business storage directory
    //
    if( $image['business_id'] > 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'hooks', 'storageDir');
        $rc = ciniki_businesses_hooks_storageDir($ciniki, $image['business_id'], array());
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $business_storage_dir = $rc['storage_dir'];
    } else {
        $business_storage_dir = $ciniki['config']['ciniki.core']['storage_dir'] . '/0/0';
    }
    
    $storage_filename = $business_storage_dir . '/ciniki.images/'
        . $image['uuid'][0] . '/' . $image['uuid'];
    
    if( file_exists($storage_filename) ) {
        $image = new Imagick($storage_filename);
    } else {
        //
        // Get the image data from the database for this version
        //
        $strsql = "SELECT ciniki_images.image "
            . "FROM ciniki_images "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'339', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']['image']) ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'340', 'msg'=>'Unable to render image'));
        }
        if( $rc['image']['image'] == '' ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3379', 'msg'=>'Unable to render image'));
        }

        //
        // Load the image in Imagemagic
        //
        $image = new Imagick();
        $image->readImageBlob($rc['image']['image']);
    }

    $image->setImageFormat("jpeg");

    //
    // Get the actions to be applied
    //
    $strsql = "SELECT sequence, action, params FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND version = '" . ciniki_core_dbQuote($ciniki, $version) . "' "
        . "ORDER BY sequence ";
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');   
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'242', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
    }
    $dh = $rc['handle'];

    $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    while( isset($result['row']) ) {
        // Crop
        if( $result['row']['action'] == 1 ) {
            $params = explode(',', $result['row']['params']);
            $image->cropImage($params[0], $params[1], $params[2], $params[3]);
        }

        // Grab the next row
        $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    }


    // error_log(print_r($_SERVER, true));
    // error_log(print_r(apache_request_headers(), true));
    // file_put_contents('/tmp/rendered_' . $rc['image']['title'], $rc['image']['image']);
    // file_put_contents('/tmp/rendered_' . $rc['image']['title'] . ".png", $image);
    if( $version == 'thumbnail' ) {
        $image->thumbnailImage($maxwidth, $maxheight);
    } elseif( $maxwidth > 0 || $maxheight > 0 ) {
        $image->scaleImage($maxwidth, $maxheight);
    }

    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_updated) . ' GMT', true, 200);
    if( isset($ciniki['request']['args']['attachment']) && $ciniki['request']['args']['attachment'] == 'yes' ) {
        header('Content-Disposition: attachment; filename="' . $original_filename . '"');
    }
    header("Content-type: image/jpeg"); 

    echo $image->getImageBlob();
    exit();

    // header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModTime).' GMT', true, 200);
    //header("Content-Length: " . filesize('/tmp/rendered_' . $rc['image']['title'] . ".png"));
    //header('Content-Disposition: attachment; filename="'.$rc['image']['title'] .'.png"');
    //header("Content-Transfer-Encoding: binary\n");

    return array('stat'=>'ok', 'image'=>$image->getImageBlob());
}
?>
