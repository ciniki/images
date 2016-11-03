<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Info
// ----
// Status:          defined
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// 
// Returns
// -------
//
function ciniki_images_hooks_imageDetails($ciniki, $business_id, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');

    if( isset($args['image_id']) && $args['image_id'] != '' ) {
        $strsql = "SELECT ciniki_images.id, "
            . "ciniki_images.type, "
            . "ciniki_images.original_filename, "
            . "ciniki_images.title, "
            . "ciniki_images.caption "
            . "FROM ciniki_images "
            . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
            . "AND ciniki_images.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.1', 'msg'=>'Unable to find image', 'err'=>$rc['err']));
        }
        if( !isset($rc['image']) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.2', 'msg'=>'Unable to find image'));
        }
        $image = $rc['image'];
        return array('stat'=>'ok', 'image'=>$image);    
    }

    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.3', 'msg'=>'No image specified'));
}
