<?php
//
// Description
// -----------
// This function will load an image and apply all actions to the image
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
// returns an imageMagick image handle
//
function ciniki_images_loadImage($ciniki, $image_id, $version) {


	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');

	//
	// Get the image data from the database for this version
	//
	$strsql = "SELECT ciniki_images.title, UNIX_TIMESTAMP(ciniki_image_versions.last_updated) as last_updated, ciniki_images.image "
		. "FROM ciniki_images, ciniki_image_versions "
		. "WHERE ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $image_id) . "' "
		. "AND ciniki_images.id = ciniki_image_versions.image_id "
		. "AND ciniki_image_versions.version = '" . ciniki_core_dbQuote($ciniki, $version) . "' ";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'637', 'msg'=>'Unable to load image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'638', 'msg'=>'Unable to load image'));
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
		. "AND version = '" . ciniki_core_dbQuote($ciniki, $version) . "' "
		. "ORDER BY sequence ";
	$rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.images');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'639', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
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

	return array('stat'=>'ok', 'image'=>$image);
}
?>
