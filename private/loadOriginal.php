<?php
//
// Description
// -----------
// This function will load an image and apply all actions to the image
// from the ciniki_image_actions table.
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// Returns
// -------
// returns an imageMagick image handle
//
function ciniki_images_loadOriginal($ciniki, $tnid, $image_id) {


    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

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
    // Get the last updated
    //
    $strsql = "SELECT ciniki_images.uuid, "
        . "ciniki_images.title, "
        . "ciniki_images.original_filename, "
        . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) as last_updated "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = 'original' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.165', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.166', 'msg'=>'Unable to render image'));
    }

    $img = $rc['image'];

    $storage_filename = $tenant_storage_dir . '/ciniki.images/'
        . $img['uuid'][0] . '/' . $img['uuid'];
//    $last_updated = $img['last_updated'];

    $dummy_image = 'no';
    if( file_exists($storage_filename) ) {
        $image = new Imagick($storage_filename);
        try {
            $image = new Imagick($storage_filename);
        } catch (Exception $e) {
            $image->newImage(500, 500, "#ffffff");
            $dummy_image = 'yes';
        }
    } else {
        //
        // Get the image data from the database for this version
        //
        $strsql = "SELECT ciniki_images.image "
            . "FROM ciniki_images "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.167', 'msg'=>'Unable to load image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.168', 'msg'=>'Unable to load image'));
        }

        //
        // Load the image in Imagemagic
        //
        $image = new Imagick();
        if( $rc['image']['image'] != '' ) {
            $image->readImageBlob($rc['image']['image']);
        } else {
            $image->newImage(500, 500, "#ffffff");
            $dummy_image = 'yes';
        }
    }

    //
    // Get the actions to be applied
    //
    $strsql = "SELECT sequence, action, params "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND version = 'original' "
        . "ORDER BY sequence ";
    $rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');   
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.169', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
    }
    $dh = $rc['handle'];

    $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    while( isset($result['row']) ) {
        // Crop
        if( $result['row']['action'] == 1 && $dummy_image == 'no' ) {
            $params = explode(',', $result['row']['params']);
            $image->cropImage($params[0], $params[1], $params[2], $params[3]);
        }

        // Grab the next row
        $result = ciniki_core_dbFetchHashRow($ciniki, $dh);
    }

    return array('stat'=>'ok', 'image'=>$image, 'last_updated'=>$img['last_updated'], 'original_filename'=>$img['original_filename']);
}
?>
