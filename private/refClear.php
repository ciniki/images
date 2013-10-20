<?php
//
// Description
// -----------
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
function ciniki_images_refClear(&$ciniki, $business_id, $args) {

	if( !isset($args['object']) || $args['object'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'488', 'msg'=>'No reference object specified'));
	}
	if( !isset($args['object_id']) || $args['object_id'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'489', 'msg'=>'No reference object id specified'));
	}

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'removeImage');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');

	//
	// Grab the uuid of the reference
	//
	$strsql = "SELECT id, uuid, ref_id FROM ciniki_image_refs "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
		. "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'ref');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['rows']) || count($rc['rows']) == 0 ) {
		return array('stat'=>'noexist', 'err'=>array('pkg'=>'ciniki', 'code'=>'906', 'msg'=>'Reference does not exist'));
	}
	$refs = $rc['rows'];

	foreach($refs as $rowid => $ref) {
		//
		// Remove the reference
		//
		$strsql = "DELETE FROM ciniki_image_refs "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND id = '" . ciniki_core_dbQuote($ciniki, $ref['id']) . "' "
			. "";
		$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'908', 'msg'=>'Unable to remove image reference', 'err'=>$rc['err']));	
		}
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 3, 'ciniki_image_refs', $ref['id'], '*', '');
		$ciniki['syncqueue'][] = array('push'=>'ciniki.images.ref',
			'args'=>array('delete_uuid'=>$ref['uuid'], 'delete_id'=>$ref['id']));

		//
		// Remove the image if no more references, and image was not 0.
		//
		if( $ref['ref_id'] > 0 ) {
			$rc = ciniki_images_removeImage($ciniki, $business_id, 0, $ref['ref_id']);
			if( $rc['stat'] != 'ok' && $rc['stat'] != 'warn' ) {
				return $rc;
			}
		}
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
