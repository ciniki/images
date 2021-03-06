<?php
//
// Description
// -----------
// This function will add the missing refs into the ciniki_image_refs table.
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
function ciniki_images_refAddMissing(&$ciniki, $module, $tnid, $args) {

    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.99', 'msg'=>'No reference object specified'));
    }
    if( !isset($args['object_table']) || $args['object_table'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.100', 'msg'=>'No reference object id specified'));
    }
    if( !isset($args['object_id_field']) || $args['object_id_field'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.101', 'msg'=>'No reference object id specified'));
    }
    if( !isset($args['object_field']) || $args['object_field'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.102', 'msg'=>'No reference object id specified'));
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

    $strsql = "SELECT " . $args['object_id_field'] . " "
        . ", " . $args['object_field'] . " "
        . "FROM " . $args['object_table'] . " "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND " . $args['object_field'] . " > 0 "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, $module, 'item');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( !isset($rc['rows']) || count($rc['rows']) == 0 ) {
        return array('stat'=>'ok');
    }
    $items = $rc['rows'];

    //
    // Load existing refs
    //
    $strsql = "SELECT CONCAT_WS('-', object_id, ref_id) AS refid "
        . "FROM ciniki_image_refs "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "";
    $rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, 'ciniki.images', 'refs', 'refid');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['refs']) ) {
        $refs = $rc['refs'];
    } else {
        $refs = array();
    }

    $db_updated = 0;
    foreach($items as $iid => $item) {
        // Check if ref already exists
        if( !isset($refs[$item[$args['object_id_field']] . '-' . $item[$args['object_field']]]) ) {
            $new_item = array(
                'ref_id'=>$item[$args['object_field']],
                'object'=>$args['object'],
                'object_id'=>$item[$args['object_id_field']],
                'object_field'=>$args['object_field'],
                );

            //
            // Get a new UUID
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
            $rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $new_item['uuid'] = $rc['uuid'];

            //
            // Add the reference
            //
            $strsql = "INSERT INTO ciniki_image_refs (uuid, tnid, ref_id, "
                . "object, object_id, object_field, date_added, last_updated"
                . ") VALUES ("
                . "'" . ciniki_core_dbQuote($ciniki, $new_item['uuid']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $tnid) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $new_item['ref_id']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $new_item['object']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $new_item['object_id']) . "', "
                . "'" . ciniki_core_dbQuote($ciniki, $new_item['object_field']) . "', "
                . "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
            $rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.103', 'msg'=>'Unable to save image reference', 'err'=>$rc['err'])); 
            }
            $ref_id = $rc['insert_id'];
            $changelog_fields = array(
                'uuid', 
                'ref_id',
                'object',
                'object_id',
                'object_field',
                );
            foreach($changelog_fields as $field) {
                ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
                    $tnid, 1, 'ciniki_image_refs', $ref_id, $field, $new_item[$field]);
            }
            $ciniki['syncqueue'][] = array('push'=>'ciniki.images.ref',
                'args'=>array('id'=>$ref_id));
            $db_updated = 1;
        }
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    if( $db_updated == 1 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
        ciniki_tenants_updateModuleChangeDate($ciniki, $tnid, 'ciniki', 'images');
    }

    return array('stat'=>'ok');
}
?>
