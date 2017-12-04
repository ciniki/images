<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Arguments
// ---------
// ciniki:
// user_id:         The ID of the user making the request
// image_id:        The ID of the image to return.
// version:         The version of the image to return.
// maxlength;       The maximum length for one edge of the image.
// 
// Returns
// -------
//
function ciniki_images_getUserImage($ciniki, $user_id, $image_id, $version, $maxlength) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');

    //
    // Get the modification information for this image
    // The tnid is required to ensure a bug doesn't allow an image from another tenant.
    //
    $strsql = "SELECT ciniki_images.date_added, ciniki_images.last_updated, "
        . "UNIX_TIMESTAMP(ciniki_image_versions.last_updated) as last_updated "
        . "FROM ciniki_images, ciniki_image_versions "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.tnid = 0 "
        . "AND ciniki_images.user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' "
        . "AND ciniki_images.id = ciniki_image_versions.image_id "
        . "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, $version) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.31', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.32', 'msg'=>'Unable to render image'));
    }

    //
    // Check headers and to see if browser has cached version.  
    //
    if( isset($ciniki['request']['If-Modified-Since']) != '' 
        && strtotime($ciniki['request']['If-Modified-Since']) >= $rc['image']['last_updated'] ) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $rc['image']['last_updated']) . ' GMT', true, 304);
        return array('stat'=>'ok');
    }

    //
    // FIXME: Check the cache for a current copy
    //


    //
    // Pull the image from the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'renderImage');
    return ciniki_images_renderImage($ciniki, $image_id, $version, $maxlength, 0);
}
?>
