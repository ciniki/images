<?php
//
// Description
// -----------
// This function will remove the missing refs into the ciniki_image_refs table.
//
// Arguments
// ---------
// ciniki:
// business_id:		The ID of the business the reference is for.
//
// args:			The arguments for adding the reference.
//
// 					image_id - The ID of the image being referenced.
// 					object - The object that is referring to the image.
// 					object_id - The ID of the object that is referrign to the image.
//
// Returns
// -------
// <rsp stat="ok" id="45" />
//
function ciniki_images_refDeleteMissing(&$ciniki, $module, $business_id, $args) {

	if( !isset($args['object']) || $args['object'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'904', 'msg'=>'No reference object specified'));
	}
	if( !isset($args['object_table']) || $args['object_table'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'905', 'msg'=>'No reference object id specified'));
	}
	if( !isset($args['object_id_field']) || $args['object_id_field'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'909', 'msg'=>'No reference object id specified'));
	}
	if( !isset($args['object_field']) || $args['object_field'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'910', 'msg'=>'No reference object id specified'));
	}

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');


	$strsql = "SELECT ciniki_image_refs.id, ciniki_image_refs.uuid "
		. "FROM ciniki_image_refs "
		. "WHERE ciniki_image_refs.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_image_refs.object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
		. "AND NOT EXISTS (SELECT " . $args['object_table'] . "." . $args['object_id_field'] . " "
			. "FROM " . $args['object_table'] . " "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND ciniki_image_refs.object_id = " . $args['object_table'] . "." . $args['object_id_field'] . " "
			. ") "
		. "";
	error_log($strsql);
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'ref');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['rows']) || count($rc['rows']) == 0 ) {
		return array('stat'=>'ok');
	}

	$missing_refs = $rc['rows'];
	$db_updated = 0;
	foreach($missing_refs as $rid => $ref) {
		$ref_id = $ref['id'];
		$ref_uuid = $ref['uuid'];

		//
		// Remove the reference
		//
		$strsql = "DELETE FROM ciniki_image_refs "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND id = '" . ciniki_core_dbQuote($ciniki, $ref_id) . "' "
			. "";
		error_log($strsql);
		ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
		$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'947', 'msg'=>'Unable to remove image reference', 'err'=>$rc['err']));	
		}
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 3, 'ciniki_image_refs', $ref_id, '*', '');
		$ciniki['syncqueue'][] = array('push'=>'ciniki.images.ref',
			'args'=>array('delete_uuid'=>$ref_uuid, 'delete_id'=>$ref_id));
		$db_updated = 1;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	if( $db_updated == 1 ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
		ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'images');
	}

	return array('stat'=>'ok');
}
?>
