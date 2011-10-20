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
function moss_images_removeImage($moss, $business_id, $user_id, $image_id) {

	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbDelete.php');

	//
	// No transactions in here, it's assumed that the calling function is dealing with any integrity
	//

	//
	// Double check information before deleting.
	//
	$strsql = "SELECT images.date_added, images.last_updated "
		. "FROM images "
		. "WHERE images.id = '" . moss_core_dbQuote($moss, $image_id) . "' "
		. "AND images.business_id = '" . moss_core_dbQuote($moss, $business_id) . "' "
		. "AND images.user_id = '" . moss_core_dbQuote($moss, $user_id) . "' "
		. "";
	$rc = moss_core_dbHashQuery($moss, $strsql, 'images', 'image');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'427', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'421', 'msg'=>'Unable to remove image'));
	}

	//
	// Remove all information about the image
	//
	$strsql = "DELETE FROM images WHERE id = '" . moss_core_dbQuote($moss, $image_id) . "' ";
	$rc = moss_core_dbDelete($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'422', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	$strsql = "DELETE FROM image_versions WHERE image_id = '" . moss_core_dbQuote($moss, $image_id) . "' ";
	$rc = moss_core_dbDelete($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'423', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	$strsql = "DELETE FROM image_actions WHERE image_id = '" . moss_core_dbQuote($moss, $image_id) . "' ";
	$rc = moss_core_dbDelete($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'424', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	$strsql = "DELETE FROM image_details WHERE image_id = '" . moss_core_dbQuote($moss, $image_id) . "' ";
	$rc = moss_core_dbDelete($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'425', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	$strsql = "DELETE FROM image_tags WHERE image_id = '" . moss_core_dbQuote($moss, $image_id) . "' ";
	$rc = moss_core_dbDelete($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'426', 'msg'=>'Unable to remove image', 'err'=>$rc['err']));
	}

	//
	// FIXME: Remove any cache versions of the image
	//

	
	return array('stat'=>'ok');
}
?>
