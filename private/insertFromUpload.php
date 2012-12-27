<?php
//
// Description
// -----------
// This function will insert an image which has been uploaded and parsed into
// the $_FILES section of PHP.  This means the form must be submitted with
// "application/x-www-form-urlencoded".
//
// Arguments
// ---------
// ciniki:
// business_id:		The ID of the business the photo is attached to.
//
// user_id:			The user_id to attach the photo to.  This may be 
// 					different from the session user, as specified by
// 					the calling function.
//
// upload_file:		The array from $_FILES[upload_field_name].
//
// perms:			The bitmask for permissions for the photo. 
//					*future* default for now is 1 - public.
//
// name:			*optional* The name to give the photo in the database.  If blank
//					The $file['name'] is used as the name of the photo.
//
// caption:			*optional* The caption for the image, may be left blank.
//
// force_duplicate:	If this is set to 'yes' and the image crc32 checksum is found
//					already belonging to this business, the image will still be inserted 
//					into the database.
// 
// Returns
// -------
// The image ID that was added.
//
function ciniki_images_insertFromUpload($ciniki, $business_id, $user_id, $upload_file, $perms, $name, $caption, $force_duplicate) {
	//
	// Load the image into Imagick so it can be processed and uploaded
	//
	$image = new Imagick($upload_file['tmp_name']);
	if( $image == null || $image === false ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'306', 'msg'=>'Unable to upload image'));
	}

	$original_filename = $upload_file['name'];
	if( $name == null || $name == '' ) {
		$name = $original_filename;

		if( preg_match('/(IMG|DSC)_[0-9][0-9][0-9][0-9]\.(jpg|gif|tiff|bmp|png)/', $name, $matches) ) {
			// Switch to blank name
			$name = '';
		}

		$name = preg_replace('/(.jpg|.png|.gif|.tiff|.bmp)/i', '', $name);
	}

	$checksum = crc32('' . $image->getImageBlob());

	//
	// Get the type of photo (jpg, png, gif, tiff, bmp, etc)
	//
	$format = strtolower($image->getImageFormat());
	$exif = array();
	$type = 0;
	if( $format == 'jpeg' ) {
		$type = 1;
		$exif = read_exif_data($upload_file['tmp_name']);
	} elseif( $format == 'png' ) {
		$type = 2;
	} elseif( $format == 'gif' ) {
		$type = 3;
	} elseif( $format == 'tiff' ) {
		$type = 4;
		$exif = read_exif_data($upload_file['tmp_name']);
	} elseif( $format == 'bmp' ) {
		$type = 5;
	} else {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'307', 'msg'=>'Invalid format' . $format));
	}

	//
	// Load photo into blob
	//

	//
	// Add code to check for duplicate image
	//
	$strsql = "SELECT id, title, caption FROM ciniki_images "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' "
		. "AND checksum = '" . ciniki_core_dbQuote($ciniki, $checksum) . "' ";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'329', 'msg'=>'Unable to check for duplicates', 'err'=>$rc['err']));
	}

	//
	// Check if there is an image that exists, and that the force flag has not been set
	//
	if( isset($rc['images']) && $force_duplicate != 'yes' ) {
		// Return the ID incase the calling script wants to use the existing image
		return array('stat'=>'fail', 'id'=>$rc['images']['id'], 'err'=>array('pkg'=>'ciniki', 'code'=>'330', 'msg'=>'Duplicate image'));
	}

	//
	// Add to image table
	//
	$strsql = "INSERT INTO ciniki_images (business_id, user_id, perms, type, original_filename, "
		. "remote_id, title, caption, checksum, date_added, last_updated, image) VALUES ( "
		. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $user_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $perms) . "', " 
		. "'" . ciniki_core_dbQuote($ciniki, $type) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $original_filename) . "', "
		. "0, "
		. "'" . ciniki_core_dbQuote($ciniki, $name). "', "
		. "'" . ciniki_core_dbQuote($ciniki, $caption). "', "
		. "'" . ciniki_core_dbQuote($ciniki, $checksum) . "', "
		. "UTC_TIMESTAMP(), UTC_TIMESTAMP(), "
		. "'" . ciniki_core_dbQuote($ciniki, $image->getImageBlob()) . "')";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'308', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
	}
	if( !isset($rc['insert_id']) || $rc['insert_id'] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'309', 'msg'=>'Unable to upload image'));	
	}
	$image_id = $rc['insert_id'];

	//
	// Add all the fields to the change log
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'user_id', $user_id);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'perms', $perms);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'type', $type);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'original_filename', $original_filename);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'title', $name);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_images', $image_id, 'caption', $caption);

	//
	// Add EXIF information to ciniki_image_details
	//
	if( $exif !== false ) {
		foreach ($exif as $key => $section) {
			if( is_array($section) ) {
				foreach ($section as $name => $val) {
					$strsql = "INSERT INTO ciniki_image_details (image_id, detail_key, detail_value, date_added, last_updated"
						. ") VALUES ("
						. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
						. "'" . ciniki_core_dbQuote($ciniki, "exif.$key.$name") . "', "
						. "'" . ciniki_core_dbQuote($ciniki, $val) . "', "
						. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
					$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
					if( $rc['stat'] != 'ok' ) {
						return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'313', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
					}
					ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
						1, 'ciniki_image_details', $image_id, "exif.$key.$name", $val);
				}
			}
		}
	}
	
	//
	// There should always be two version added to the database, an original and thumbnail.
	//
	$thumb_crop_data = '';

	//
	// Determine the size or the original, and the crop area for a thumbnail
	//
	$width = $image->getimagewidth();
	$height = $image->getimageheight();
	if( $width < 1 || $height < 1 ) {
		// Check to make sure there is some size to the image
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'314', 'msg'=>'The image is empty'));
	}

	$flags = 0;
	if( $width < $height ) {
		$flags = 0x01;		// Portrait
		$offset = floor(($height-$width)/2);
		$thumb_crop_data = $width . ',' . $width . ',0,' . $offset;
	} elseif( $width > $height ) {
		$flags = 0x02;		// Landscape
		$offset = floor(($width-$height)/2);
		$thumb_crop_data = $height . ',' . $height . ',' . $offset . ',0';
	} else {
		$flags = 0x03;		// Square
	}

	//
	// Add the original version in the ciniki_image_versions table
	//
	$strsql = "INSERT INTO ciniki_image_versions (image_id, version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', 'original', "
		. ciniki_core_dbQuote($ciniki, $flags) . ", UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'315', 'msg'=>'Unable to store original image', 'err'=>$rc['err']));	
	}
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_image_versions', "$image_id-original", 'flags', $flags);

	//
	// Add the thumbnail version into the ciniki_image_versions table
	//
	$strsql = "INSERT INTO ciniki_image_versions (image_id, version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
		. "'thumbnail', 0x03, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'316', 'msg'=>'Unable to store thumbnail image', 'err'=>$rc['err']));	
	}
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
		1, 'ciniki_image_versions', "$image_id-thumbnail", 'flags', 3);

	//
	// Insert the crop action into the ciniki_image_actions table for the thumbnail, if the original was not square
	//
	if( $thumb_crop_data != '' ) {
		$strsql = "INSERT INTO ciniki_image_actions (image_id, version, sequence, action, params, date_added, last_updated"
			. ") VALUES ("
			. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
			. "'thumbnail', 1, 1, "
			. "'" . ciniki_core_dbQuote($ciniki, $thumb_crop_data) . "', "
			. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
		$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'317', 'msg'=>'Unable to crop thumbnail', 'err'=>$rc['err']));	
		}
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
			1, 'ciniki_image_actions', "$image_id-thumbnail-1", 'action', 1);
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', $business_id, 
			1, 'ciniki_image_actions', "$image_id-thumbnail-1", 'params', $thumb_crop_data);
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'images');

	return array('stat'=>'ok', 'id'=>$image_id);
}
?>
