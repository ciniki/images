<?php
//
// Description
// -----------
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant the reference is for.
//
// args:            The arguments for adding the reference.
//
//                  image_id - The ID of the image being referenced.
//                  object - The object that is referring to the image.
//                  object_id - The ID of the object that is referrign to the image.
//
// Returns
// -------
// <rsp stat="ok" id="45" />
//
function ciniki_images_refDelete(&$ciniki, $tnid, $args) {

    if( !isset($args['image_id']) || $args['image_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.104', 'msg'=>'No image specified'));
    }
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.105', 'msg'=>'No reference object specified'));
    }
    if( !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.106', 'msg'=>'No reference object id specified'));
    }

    //
    // Grab the uuid of the reference
    //
    $strsql = "SELECT id, uuid FROM ciniki_image_refs "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ref_id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'ref');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['ref']) ) {
        return array('stat'=>'noexist', 'err'=>array('code'=>'ciniki.images.107', 'msg'=>'Reference does not exist'));
    }
    $ref_id = $rc['ref']['id'];
    $ref_uuid = $rc['ref']['uuid'];

    //
    // Remove the reference
    //
    $strsql = "DELETE FROM ciniki_image_refs "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND ref_id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.108', 'msg'=>'Unable to remove image reference', 'err'=>$rc['err']));   
    }
    ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
        $tnid, 3, 'ciniki_image_refs', $ref_id, '*', '');
    $ciniki['syncqueue'][] = array('push'=>'ciniki.images.ref',
        'args'=>array('delete_uuid'=>$ref_uuid, 'delete_id'=>$ref_id));

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $tnid, 'ciniki', 'images');

    return array('stat'=>'ok');
}
?>
