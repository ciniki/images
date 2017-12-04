<?php
//
// Description
// -----------
// This function will return the title of the image.
//
// Arguments
// ---------
// user_id:         The user making the request
// 
// 
// Returns
// -------
//
function ciniki_images_getImageTitle($ciniki, $tnid, $image_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');

    //
    // Get the title of the image
    //
    $strsql = "SELECT ciniki_images.title FROM ciniki_images "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.29', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) || !isset($rc['image']['title']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.30', 'msg'=>'Unable to '));
    }

    return array('stat'=>'ok', 'title'=>$rc['image']['title']);
}
?>
