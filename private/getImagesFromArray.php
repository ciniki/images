<?php
//
// Description
// -----------
// This function will return an array of image information based
// on the image id's in the 
//
// Info
// ----
// Status: 			beta
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// Returns
// -------
//
function ciniki_images_getImagesFromArray($ciniki, $business_id, $images) {

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuoteIDs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashIDQuery.php');
	$strsql = "SELECT id, perms, type, title, caption FROM images "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND id IN (" . ciniki_core_dbQuoteIDs($ciniki, $images) . ")";
	return ciniki_core_dbHashIDQuery($ciniki, $strsql, 'images', 'images', 'id');
}
?>
