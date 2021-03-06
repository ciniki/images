<?php
//
// Description
// ===========
// This method will rotate an image in the database.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant the image is attached to.
// image_id:        The ID of the image to be rotated.
// direction:       The direction to rotate the image.
//
//                  default - rotate the image clockwise
//                  right - rotate the image clockwise
//                  left - rotate the image counter clockwise
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_images_rotate(&$ciniki) {
    //  
    // Find all the required and optional arguments
    // FIXME: Allow rotate to apply to only a certain version, currently applies to all versions
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'image_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Image'), 
        'direction'=>array('required'=>'no', 'default'=>'right', 'blank'=>'no', 'name'=>'Direction'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['tnid'], 'ciniki.images.rotate'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    //  
    // Turn off autocommit
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }

    //
    // Load the image
    //
    $strsql = "SELECT id, uuid, image "
        . "FROM ciniki_images "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.138', 'msg'=>'Unable to find the image requested'));
    }
    $img = $rc['image'];

    //
    // Get the tenant storage directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'storageDir');
    $rc = ciniki_tenants_hooks_storageDir($ciniki, $args['tnid'], array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tenant_storage_dir = $rc['storage_dir'];
    
    $storage_filename = $tenant_storage_dir . '/ciniki.images/'
        . $img['uuid'][0] . '/' . $img['uuid'];
    
    if( file_exists($storage_filename) ) {
        $image = new Imagick($storage_filename);
    } else {
        $image = new Imagick();
        $image->readImageBlob($img['image']);
    }

    //
    // Rotate the image
    //
    if( isset($args['direction']) && $args['direction'] == 'left' ) {
        $image->rotateImage(new ImagickPixel('none'), -90);
    } else {
        $image->rotateImage(new ImagickPixel('none'), 90);
    }

    //
    // Save the image
    //
    $h = fopen($storage_filename, 'w');
    if( $h ) {
        fwrite($h, $image->getImageBlob());
        fclose($h);
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.139', 'msg'=>'Unable to add image'));
    }

    //
    // Save the updated version
    //
    $strsql = "UPDATE ciniki_images "
//      . "SET image = '" . ciniki_core_dbQuote($ciniki, $image->getImageBlob()) . "' "
        . "SET last_updated = UTC_TIMESTAMP() "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "";
    $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $ciniki['syncqueue'][] = array('push'=>'ciniki.images.image',
        'args'=>array('id'=>$args['image_id']));
        
    //
    // Update the crop parameters for each version
    //
    $strsql = "SELECT id, image_id, version, sequence, action, params "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'actions');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['rows']) ) {
        $actions = $rc['rows'];
        foreach($actions as $rnum => $action) {
            if( $action['action'] == 1 ) {
                // Crop
                $params = preg_split('/,/', $action['params']);
                $new_params = $params[1] . ',' . $params[0] . ',' . $params[3] . ',' . $params[2];
                $strsql = "UPDATE ciniki_image_actions "
                    . "SET params = '" . ciniki_core_dbQuote($ciniki, $new_params) . "', "
                    . "last_updated = UTC_TIMESTAMP() "
                    . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $action['image_id']) . "' "
                    . "AND version = '" . ciniki_core_dbQuote($ciniki, $action['version']) . "' "
                    . "AND sequence = '" . ciniki_core_dbQuote($ciniki, $action['sequence']) . "' "
                    . "";
                $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.images');
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                // FIXME: Add history log for this action
                $ciniki['syncqueue'][] = array('push'=>'ciniki.images.action',
                    'args'=>array('id'=>$action['id']));
            }
        }
    }
    
    //
    // Commit the database changes
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'images');
    
    return array('stat'=>'ok');
}
?>
