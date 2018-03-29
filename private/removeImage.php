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
function ciniki_images_removeImage(&$ciniki, $tnid, $user_id, $image_id) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    //
    // Get the tenant cache directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'cacheDir');
    $rc = ciniki_tenants_cacheDir($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tenant_cache_dir = $rc['cache_dir'];

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
        . "AND ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' ";
    if( $user_id > 0 ) {    
        $strsql .= "AND ciniki_images.user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' ";
    }
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.114', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }
    if( count($rc['rows']) == 0 ) {
        return array('stat'=>'ok');
    }
    if( !isset($rc['image']) && count($rc['rows']) == 0 ) {
        // If the image is not found, then it was already deleted.
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.115', 'msg'=>'Unable to remove image'));
    }
    $image = $rc['image'];
    $img_uuid = $rc['image']['uuid'];

    //
    // Check there are no references to the image before deleting
    //
    $strsql = "SELECT 'refs', COUNT(*) AS num "
        . "FROM ciniki_image_refs "
        . "WHERE ciniki_image_refs.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ciniki_image_refs.ref_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'refs');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['refs']) && $rc['refs']['num'] > 0 ) {
        return array('stat'=>'warn', 'err'=>array('code'=>'ciniki.images.116', 'msg'=>'Image still has references, will not be deleted'));
    }

    //
    // Remove all information about the image
    //
    $strsql = "DELETE FROM ciniki_images "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.117', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }
    $strsql = "DELETE FROM ciniki_image_details "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.118', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
    }

    //
    // Record delete in history and sync delete
    //
    ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
        $tnid, 3, 'ciniki_images', $image_id, '*', '');
    $ciniki['syncqueue'][] = array('push'=>'ciniki.images.image',
        'args'=>array('delete_uuid'=>$image['uuid'], 'delete_id'=>$image_id));

    
    // 
    // Remove the image versions
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_image_versions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $versions = $rc['rows'];
    foreach($versions as $row_id => $version) {
        $strsql = "DELETE FROM ciniki_image_versions "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $version['id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "";
        $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.119', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
        }
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
            $tnid, 3, 'ciniki_image_versions', $version['id'], '*', '');
        $ciniki['syncqueue'][] = array('push'=>'ciniki.images.version',
            'args'=>array('delete_uuid'=>$version['uuid'], 'delete_id'=>$version['id']));
    }

    // 
    // Remove the image actions
    //
    $strsql = "SELECT id, uuid "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $actions = $rc['rows'];
    foreach($actions as $row_id => $action) {
        $strsql = "DELETE FROM ciniki_image_actions "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $action['id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
            . "";
        $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.120', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
        }
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
            $tnid, 3, 'ciniki_image_actions', $action['id'], '*', '');
        $ciniki['syncqueue'][] = array('push'=>'ciniki.images.action',
            'args'=>array('delete_uuid'=>$action['uuid'], 'delete_id'=>$action['id']));
    }

    //
    // Remove any cache versions of the image
    //
    $cache_dir = $tenant_cache_dir . '/ciniki.images/'
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
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $tnid, 'ciniki', 'images');
    
    return array('stat'=>'ok');
}
?>
