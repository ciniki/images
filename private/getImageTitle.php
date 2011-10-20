<?php
//
// Description
// -----------
// This function will return the title of the image.
//
// Info
// ----
// Status: 			alpha
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// 
// Returns
// -------
//
function moss_images_getImageTitle($moss, $business_id, $image_id) {

	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');

	//
	// Get the title of the image
	//
	$strsql = "SELECT images.title FROM images "
		. "WHERE images.id = '" . moss_core_dbQuote($moss, $image_id) . "' "
		. "AND images.business_id = '" . moss_core_dbQuote($moss, $business_id) . "' "
		. "";
	$rc = moss_core_dbHashQuery($moss, $strsql, 'images', 'image');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'348', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) || !isset($rc['image']['title']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'349', 'msg'=>'Unable to '));
	}

	return array('stat'=>'ok', 'title'=>$rc['image']['title']);
}
?>
