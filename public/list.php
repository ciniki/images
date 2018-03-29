<?php
//
// Description
// -----------
// This method will return the list of images for the tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:                The ID of the tenant to get the image from.
//
// Returns
// -------
//
function ciniki_images_list($ciniki) {
    //
    // Check args
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'imagedata'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Image Encoded Data'), 
//        'version'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Version'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    //  
    // Make sure this module is activated, and 
    // check session user permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['tnid'], 'ciniki.images.list', array()); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
   
    $strsql = "SELECT id, title "
        . "FROM ciniki_images "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "LIMIT 100 "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.images', array(
        array('container'=>'images', 'fname'=>'id', 'fields'=>array('id', 'image_id'=>'id', 'title')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.144', 'msg'=>'Unable to load images', 'err'=>$rc['err']));
    }
    $images = isset($rc['images']) ? $rc['images'] : array();
    foreach($images as $iid => $image) {
        if( isset($image['image_id']) && $image['image_id'] > 0 ) {
            $rc = ciniki_images_loadCacheThumbnail($ciniki, $args['tnid'], $image['image_id'], 75);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $images[$iid]['image_data'] = 'data:image/jpg;base64,' . base64_encode($rc['image']);
        }
    }
    
    return array('stat'=>'ok', 'images'=>$images);
}
?>
