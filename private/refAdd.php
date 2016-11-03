<?php
//
// Description
// -----------
//
// Arguments
// ---------
// ciniki:
// business_id:     The ID of the business the reference is for.
//
// args:            The arguments for adding the reference.
//
//                  ref_id - The ID of the image being referenced.
//                  object - The object that is referring to the image.
//                  object_id - The ID of the object that is referrign to the image.
//                  object_field - The table field the image ID is stored in the reference.
//
// Returns
// -------
// <rsp stat="ok" id="45" />
//
function ciniki_images_refAdd(&$ciniki, $business_id, $args) {

    if( !isset($args['image_id']) || $args['image_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.94', 'msg'=>'No image specified'));
    }
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.95', 'msg'=>'No image specified'));
    }
    if( !isset($args['object_id']) || $args['object_id'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.96', 'msg'=>'No image specified'));
    }
    if( !isset($args['object_field']) || $args['object_field'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.97', 'msg'=>'No image specified'));
    }

    // 
    // Note: We don't need to worry about a transaction, that will be taken care
    //       of in the calling function
    //

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    //
    // Get a new UUID
    //
    $rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args['uuid'] = $rc['uuid'];

    //
    // Add the reference
    //
    $strsql = "INSERT INTO ciniki_image_refs (uuid, business_id, ref_id, "
        . "object, object_id, object_field, date_added, last_updated"
        . ") VALUES ("
        . "'" . ciniki_core_dbQuote($ciniki, $args['uuid']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['object']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['object_field']) . "', "
        . "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
    $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.98', 'msg'=>'Unable to save image reference', 'err'=>$rc['err'])); 
    }
    $ref_id = $rc['insert_id'];
    $changelog_fields = array(
        'uuid', 
        'object',
        'object_id',
        'object_field',
        );
    foreach($changelog_fields as $field) {
        if( isset($args[$field]) && $args[$field] != '' ) {
            ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
                $business_id, 1, 'ciniki_image_refs', $ref_id, $field, $args[$field]);
        }
    }
    if( isset($args['image_id']) && $args['image_id'] != '' ) {
        ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
            $business_id, 1, 'ciniki_image_refs', $ref_id, 'ref_id', $args['image_id']);
    }
    $ciniki['syncqueue'][] = array('push'=>'ciniki.images.ref',
        'args'=>array('id'=>$ref_id));

    //
    // Update the last_change date in the business modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
    ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'images');

    return array('stat'=>'ok', 'id'=>$ref_id);
}
?>
