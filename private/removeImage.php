<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Info
// ----
// Status: 			defined
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// 
// Returns
// -------
//
function ciniki_images_removeImage(&$ciniki, $business_id, $user_id, $image_id) {

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');

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
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'421', 'msg'=>'Unable to remove image'));
	}
	$image = $rc['image'];

	//
	// Check there are no references to the image before deleting
	//
	$strsql = "SELECT 'refs', COUNT(*) AS num "
		. "FROM ciniki_image_refs "
		. "WHERE ciniki_image_refs.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_image_refs.image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
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
	$strsql = "DELETE FROM ciniki_images WHERE id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'422', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	// FIXME: Add history
	$ciniki['syncqueue'][] = array('push'=>'ciniki.images.image',
		'args'=>array('delete_uuid'=>$image['uuid'], 'delete_id'=>$image_id));

	
	// FIXME: Select uuid's removed for sync push
	$strsql = "DELETE FROM ciniki_image_versions WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'423', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	// FIXME: Select uuid's removed for sync push
	$strsql = "DELETE FROM ciniki_image_actions WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'424', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	$strsql = "DELETE FROM ciniki_image_details WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'425', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

//	$strsql = "DELETE FROM ciniki_image_tags WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' ";
//	$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
//	if( $rc['stat'] != 'ok' ) {
//		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'426', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
//	}

	//
	// FIXME: Remove any cache versions of the image
	//

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'images');
	
	return array('stat'=>'ok');
}
?>
