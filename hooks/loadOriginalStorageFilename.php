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
function ciniki_images_hooks_loadOriginalStorageFilename($ciniki, $tnid, $args) {

    if( !isset($args['image_id']) || $args['image_id'] == 0 || $args['image_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.159', 'msg'=>'No image specified'));
    }

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
        . "ciniki_images.checksum, "
        . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) as last_updated "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, 'original') . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.145', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.146', 'msg'=>'Unable to render image'));
    }

    $img = $rc['image'];

    $storage_filename = $tenant_storage_dir . '/ciniki.images/' . $img['uuid'][0] . '/' . $img['uuid'];

    return array('stat'=>'ok', 'filename'=>$storage_filename);
}
?>
