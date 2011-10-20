<?php
//
// Description
// -----------
// This function will render an image and apply all actions to the image
// from the image_actions table.
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
function moss_images_renderImage($moss, $image_id, $version, $maxlength) {


	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuery.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbFetchHashRow.php');

	//
	// Get the image data from the database for this version
	//
	$strsql = "SELECT images.title, UNIX_TIMESTAMP(image_versions.last_updated) as last_updated, images.image "
		. "FROM images, image_versions "
		. "WHERE images.id = '" . moss_core_dbQuote($moss, $image_id) . "' "
		. "AND images.id = image_versions.image_id "
		. "AND image_versions.version = '" . moss_core_dbQuote($moss, $version) . "' ";
	$rc = moss_core_dbHashQuery($moss, $strsql, 'images', 'image');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'339', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'340', 'msg'=>'Unable to render image'));
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
	$strsql = "SELECT sequence, action, params FROM image_actions "
		. "WHERE image_id = '" . moss_core_dbQuote($moss, $image_id) . "' "
		. "AND version = '" . moss_core_dbQuote($moss, $version) . "' "
		. "ORDER BY sequence ";
	$rc = moss_core_dbQuery($moss, $strsql, 'images');	
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'242', 'msg'=>'Unable to apply image actions', 'err'=>$rc['err']));
	}
	$dh = $rc['handle'];

	$result = moss_core_dbFetchHashRow($moss, $dh);
	while( isset($result['row']) ) {
		// Crop
		if( $result['row']['action'] == 1 ) {
			$params = explode(',', $result['row']['params']);
			$image->cropImage($params[0], $params[1], $params[2], $params[3]);
		}

		// Grab the next row
		$result = moss_core_dbFetchHashRow($moss, $dh);
	}


	// error_log(print_r($_SERVER, true));
	// error_log(print_r(apache_request_headers(), true));
	// file_put_contents('/tmp/rendered_' . $rc['image']['title'], $rc['image']['image']);
	//file_put_contents('/tmp/rendered_' . $rc['image']['title'] . ".png", $image);
	$image->thumbnailImage($maxlength, 0);
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_updated) . ' GMT', true, 200);
	header("Content-type: image/jpeg");	

	echo $image->getImageBlob();
	exit();

	// header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModTime).' GMT', true, 200);
	//header("Content-Length: " . filesize('/tmp/rendered_' . $rc['image']['title'] . ".png"));
	//header('Content-Disposition: attachment; filename="'.$rc['image']['title'] .'.png"');
	//header("Content-Transfer-Encoding: binary\n");

	return array('stat'=>'ok', 'image'=>$image->getImageBlob());
}
?>
