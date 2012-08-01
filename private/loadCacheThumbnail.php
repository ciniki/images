<?php
//
// Description
// -----------
// This function will render an image and apply all actions to the image
// from the ciniki_image_actions table.
//
// Info
// ----
// Status: 			defined
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// Returns
// -------
//
function ciniki_images_loadCacheThumbnail($ciniki, $image_id, $maxlength) {

	
	$cache_filename = $ciniki['config']['core']['modules_dir'] . '/images/cache/t' . $maxlength . '/' . $image_id . '.jpg';

	//
	// Get the last updated timestamp
	//
	$strsql = "SELECT ciniki_images.title, "
		. "IF(ciniki_images.last_updated > ciniki_image_versions.last_updated, UNIX_TIMESTAMP(ciniki_images.last_updated), UNIX_TIMESTAMP(ciniki_image_versions.last_updated)) AS last_updated "
		. "FROM ciniki_images, ciniki_image_versions "
		. "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
		. "AND ciniki_images.id = ciniki_image_versions.image_id "
		. "AND ciniki_image_versions.version = 'thumbnail' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'661', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'662', 'msg'=>'Unable to render image'));
	}
	$img = $rc['image'];

	//
	// Check if cached version is there, and there hasn't been any updates
	//
	$utc_offset = date_offset_get(new DateTime);
	if( file_exists($cache_filename)
		&& (filemtime($cache_filename) - $utc_offset) > $img['last_updated'] ) {
		$imgblog = fread(fopen($cache_filename, 'r'), filesize($cache_filename));
		return array('stat'=>'ok', 'image'=>$imgblog);
	}

	//
	// If the file does not exist, then load information from database, and create cache file
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuery.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbFetchHashRow.php');

	//
	// Get the image data from the database for this version
	//
	$strsql = "SELECT ciniki_images.title, UNIX_TIMESTAMP(ciniki_image_versions.last_updated) AS last_updated, ciniki_images.image "
		. "FROM ciniki_images, ciniki_image_versions "
		. "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
		. "AND ciniki_images.id = ciniki_image_versions.image_id "
		. "AND ciniki_image_versions.version = 'thumbnail' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'624', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'625', 'msg'=>'Unable to render image'));
	}

	//
	// Load the image in Imagemagic
	//
	$image = new Imagick();
	$image->readImageBlob($rc['image']['image']);
	$last_updated = $rc['image']['last_updated'];
	$image->setImageFormat("jpeg");

	//
	// Get the actions to be applied
	//
	$strsql = "SELECT sequence, action, params FROM ciniki_image_actions "
		. "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
		. "AND version = 'thumbnail' "
		. "ORDER BY sequence ";
	$rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'626', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
	}
	$dh = $rc['handle'];

	$result = ciniki_core_dbFetchHashRow($ciniki, $dh);
	while( isset($result['row']) ) {
		// Crop
		if( $result['row']['action'] == 1 ) {
			$params = explode(',', $result['row']['params']);
			$image->cropImage($params[0], $params[1], $params[2], $params[3]);
		}

		// Grab the next row
		$result = ciniki_core_dbFetchHashRow($ciniki, $dh);
	}

	$image->thumbnailImage($maxlength, 0);

	//
	// Check directory exists
	//
	if( !file_exists(dirname($cache_filename)) ) {
		mkdir(dirname($cache_filename));
	}

	//
	// Write the image to the cache file
	//
	$h = fopen($cache_filename, 'w');
	if( $h ) {
		$image->setImageCompressionQuality(40);
		fwrite($h, $image->getImageBlob());
		fclose($h);
	}

	return array('stat'=>'ok', 'image'=>$image->getImageBlob());
}
?>
