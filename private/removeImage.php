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
function ciniki_images_removeImage(&$ciniki, $business_id, $user_id, $image_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    //
    // Get the business cache directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'cacheDir');
    $rc = ciniki_businesses_cacheDir($ciniki, $business_id);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $business_cache_dir = $rc['cache_dir'];

    //
    // No transactions in here, it's assumed that the calling function is dealing with any integrity
    //

    //
    // Double check information before deleting.
    //
    $strsql = "SELECT ciniki_images.uuid, "
        . "ciniki_images.date_added, ciniki_images.last_updated "
        . "FROM ciniki_images "
        . "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND ciniki_images.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' ";
    if( $user_id > 0 ) {    
        $strsql .= "AND ciniki_images.user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' ";
    }
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'427', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }
    if( count($rc['rows']) == 0 ) {
        return array('stat'=>'ok');
    }
    if( !isset($rc['image']) && count($rc['rows']) == 0 ) {
        // If the image is not found, then it was already deleted.
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'421', 'msg'=>'Unable to remove image'));
    }
    $image = $rc['image'];
    $img_uuid = $rc['image']['uuid'];

    //
    // Check there are no references to the image before deleting
    //
    $strsql = "SELECT 'refs', COUNT(*) AS num "
        . "FROM ciniki_image_refs "
        . "WHERE ciniki_image_refs.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND ciniki_image_refs.ref_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'refs');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['refs']) && $rc['refs']['num'] > 0 ) {
        return array('stat'=>'warn', 'err'=>array('pkg'=>'ciniki', 'code'=>'50', 'msg'=>'Image still has references, will not be deleted'));
    }

    //
    // Remove all information about the image
    //
    $strsql = "DELETE FROM ciniki_images "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'422', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }
    $strsql = "DELETE FROM ciniki_image_details "
        . "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'425', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }

    //
    // Record delete in history and sync delete
    //
    ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
        $business_id, 3, 'ciniki_images', $image_id, '*', '');
    $ciniki['syncqueue'][] = array('push'=>'ciniki.images.image',
        'args'=>array('delete_uuid'=>$image['uuid'], 'delete_id'=>$image_id));

    
    // 
    // Remove the image versions
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_image_versions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $versions = $rc['rows'];
    foreach($versions as $row_id => $version) {
        $strsql = "DELETE FROM ciniki_image_versions "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $version['id']) . "' "
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "";
        $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'423', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
        }
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
            $business_id, 3, 'ciniki_image_versions', $version['id'], '*', '');
        $ciniki['syncqueue'][] = array('push'=>'ciniki.images.version',
            'args'=>array('delete_uuid'=>$version['uuid'], 'delete_id'=>$version['id']));
    }

    // 
    // Remove the image actions
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $actions = $rc['rows'];
    foreach($actions as $row_id => $action) {
        $strsql = "DELETE FROM ciniki_image_actions "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $action['id']) . "' "
            . "AND business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
            . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "";
        $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'424', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
        }
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
            $business_id, 3, 'ciniki_image_actions', $action['id'], '*', '');
        $ciniki['syncqueue'][] = array('push'=>'ciniki.images.action',
            'args'=>array('delete_uuid'=>$action['uuid'], 'delete_id'=>$action['id']));
    }

    //
    // Remove any cache versions of the image
    //
    $cache_dir = $business_cache_dir . '/ciniki.images/'
        . $img_uuid[0] . '/' . $img_uuid;
    if( is_dir($cache_dir) ) {
        $files = array_diff(scandir($cache_dir), array('.','..')); 
        foreach ($files as $file) { 
            if( is_dir("$cache_dir/$file") ) {
                error_log("CACHE-ERR: Unable to remove cache files, directory exists: $cache_dir/$file");
            }
            unlink("$cache_dir/$file");
        } 
        rmdir($cache_dir);
    }

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'images');
    
    return array('stat'=>'ok');
}
?>
