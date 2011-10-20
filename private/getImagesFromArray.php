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
function moss_images_getImagesFromArray($moss, $business_id, $images) {

	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuoteIDs.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashIDQuery.php');
	$strsql = "SELECT id, perms, type, title, caption FROM images "
		. "WHERE business_id = '" . moss_core_dbQuote($moss, $business_id) . "' "
		. "AND id IN (" . moss_core_dbQuoteIDs($moss, $images) . ")";
	return moss_core_dbHashIDQuery($moss, $strsql, 'images', 'images', 'id');
}
?>
