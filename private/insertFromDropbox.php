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
function ciniki_images_insertFromDropbox(&$ciniki, $business_id, $user_id, $client, $path, $perms, $name, $caption, $force_duplicate) {
	//
	// Get the file from dropbox
	//
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_SSLVERSION, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $client->getAccessToken()));
	if( $path[0] != '/' ) { $path = '/' . $path; }
	curl_setopt($ch, CURLOPT_URL, "https://api-content.dropbox.com/1/files/auto" . curl_escape($ch, $path));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
	$image_data = curl_exec($ch);
	if( $image_data === false ) {
        // 
        // Try again
        //
        $image_data = curl_exec($ch);
        if( $image_data === false ) {
            return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2281', 'msg'=>'Unable to get image', 'pmsg'=>curl_error($ch)));
        }
	}
    if( curl_getinfo($ch, CURLINFO_HTTP_CODE) != '200' ) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3078', 'msg'=>'Unable to get image.', 'msg'=>'HTTP CODE: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE)));
    }
	curl_close($ch);

	//
	// Load the image into Imagick so it can be processed and uploaded
	//
	$image = new Imagick();
	if( $image == null || $image === false ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2270', 'msg'=>'Unable to upload image'));
	}

    try {
        $image->readImageBlob($image_data);
    } catch (Exception $e) {
        return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3079', 'msg'=>'Unable to understand image file: ' . $path));
    }

    //
    // Reduce image larger than 16M
    //
    if( strlen($image_data) > 16000000 ) {
        if( $image->getImageWidth() > 3000 ) {
            $image->resizeImage(3000, 3000, imagick::FILTER_LANCZOS, 1, true);
        }
        if( $image->getImageCompressionQuality() > 75 ) {
            $image->setImageCompressionQuality(75);
        }
    }

	$original_filename = basename($path);
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
		$exif = $image->getImageProperties("exif:*");
	} elseif( $format == 'png' ) {
		$type = 2;
	} elseif( $format == 'gif' ) {
		$type = 3;
	} elseif( $format == 'tiff' ) {
		$type = 4;
		$exif = $image->getImageProperties("exif:*");
	} elseif( $format == 'bmp' ) {
		$type = 5;
	} else {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2271', 'msg'=>'Invalid format' . $format));
	}

	//
	// Correct the orientation.  This is a problem with images from iPhones being rotated on some
	// displays and not other.  Not everything detects the orientation properly.
	//
	$orientation = $image->getImageOrientation();
	if( $orientation != imagick::ORIENTATION_TOPLEFT ) {
		switch($orientation) {
			case imagick::ORIENTATION_BOTTOMRIGHT: 
				$image->rotateimage("#000", 180);
				break;
			case imagick::ORIENTATION_RIGHTTOP: 
				$image->rotateimage("#000", 90);
				break;
			case imagick::ORIENTATION_LEFTBOTTOM: 
				$image->rotateimage("#000", -90);
				break;
		}
		$image->setImageOrientation(imagick::ORIENTATION_TOPLEFT);
	}

	//
	// Load photo into blob
	//

	//
	// check for duplicate image
	//
	$strsql = "SELECT id, title, caption FROM ciniki_images "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND user_id = '" . ciniki_core_dbQuote($ciniki, $user_id) . "' "
		. "AND checksum = '" . ciniki_core_dbQuote($ciniki, $checksum) . "' ";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2272', 'msg'=>'Unable to check for duplicates', 'err'=>$rc['err']));
	}

	//
	// Check if there is an image that exists, and that the force flag has not been set
	//
	if( isset($rc['images']) && $force_duplicate != 'yes' ) {
		// Return the ID incase the calling script wants to use the existing image
		return array('stat'=>'exists', 'id'=>$rc['images']['id'], 'err'=>array('pkg'=>'ciniki', 'code'=>'2273', 'msg'=>'Duplicate image'));
	}

    //
    // Get the business UUID
    //
	$strsql = "SELECT uuid "
        . "FROM ciniki_businesses "
		. "WHERE id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' ";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.businesses', 'business');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	if( !isset($rc['business']) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3349', 'msg'=>'Unable to get business details'));
	}
	$business_uuid = $rc['business']['uuid'];

	//
	// Get a new UUID
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$uuid = $rc['uuid'];

	//
	// Move the file to ciniki-storage
	//
	$storage_dirname = $ciniki['config']['ciniki.core']['storage_dir'] . '/'
		. $business_uuid[0] . '/' . $business_uuid
		. '/ciniki.images/'
		. $uuid[0];
	$storage_filename = $storage_dirname . '/' . $uuid;
	if( !is_dir($storage_dirname) ) {
		if( !mkdir($storage_dirname, 0700, true) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'3348', 'msg'=>'Unable to add file'));
		}
	}

    //
    // Write the image to storage
    //
	$h = fopen($storage_filename, 'w');
	if( $h ) {
		fwrite($h, $image->getImageBlob());
		fclose($h);
	}

	//
	// Add to image table
	//
	$strsql = "INSERT INTO ciniki_images (uuid, business_id, user_id, perms, type, original_filename, "
		. "remote_id, title, caption, checksum, date_added, last_updated, image) VALUES ( "
		. "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
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
        . "'')";
//		. "'" . ciniki_core_dbQuote($ciniki, $image->getImageBlob()) . "')";
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2274', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
	}
	if( !isset($rc['insert_id']) || $rc['insert_id'] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2275', 'msg'=>'Unable to upload image'));	
	}
	$image_id = $rc['insert_id'];

	//
	// Add all the fields to the change log
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'uuid', $uuid);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'user_id', $user_id);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'perms', $perms);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'type', $type);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'original_filename', $original_filename);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'title', $name);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_images', $image_id, 'caption', $caption);

	//
	// Add EXIF information to ciniki_image_details
	//
	if( $exif !== false ) {
		foreach ($exif as $key => $section) {
			if( is_array($section) ) {
				foreach ($section as $name => $val) {
					$strsql = "INSERT INTO ciniki_image_details (business_id, image_id, detail_key, detail_value, date_added, last_updated"
						. ") VALUES ("
						. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
						. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
						. "'" . ciniki_core_dbQuote($ciniki, "exif.$key.$name") . "', "
						. "'" . ciniki_core_dbQuote($ciniki, $val) . "', "
						. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
					$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
					if( $rc['stat'] != 'ok' ) {
						return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2276', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
					}
					ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 
						'ciniki_image_history', $business_id, 
						1, 'ciniki_image_details', $image_id, "exif.$key.$name", $val);
				}
			}
		}
	}

	//
	// sync the image, this will include the details
	//
	$ciniki['syncqueue'][] = array('push'=>'ciniki.images.image',
		'args'=>array('id'=>$image_id));

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
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2277', 'msg'=>'The image is empty'));
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
	// Get a new UUID
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$uuid = $rc['uuid'];

	//
	// Add the original version in the ciniki_image_versions table
	//
	$strsql = "INSERT INTO ciniki_image_versions (uuid, business_id, image_id, "
		. "version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', 'original', "
		. ciniki_core_dbQuote($ciniki, $flags) . ", UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2278', 'msg'=>'Unable to store original image', 'err'=>$rc['err']));	
	}
	$version_id = $rc['insert_id'];
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'uuid', $uuid);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'image_id', $image_id);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'version', 'original');
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'flags', $flags);
	$ciniki['syncqueue'][] = array('push'=>'ciniki.images.version',
		'args'=>array('id'=>$version_id));

	//
	// Get a new UUID
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$uuid = $rc['uuid'];

	//
	// Add the thumbnail version into the ciniki_image_versions table
	//
	$strsql = "INSERT INTO ciniki_image_versions (uuid, business_id, image_id, "
		. "version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
		. "'thumbnail', 0x03, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2279', 'msg'=>'Unable to store thumbnail image', 'err'=>$rc['err']));	
	}
	$version_id = $rc['insert_id'];
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'uuid', $uuid);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'image_id', $image_id);
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'version', 'thumbnail');
	ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
		$business_id, 1, 'ciniki_image_versions', $version_id, 'flags', 3);
	$ciniki['syncqueue'][] = array('push'=>'ciniki.images.version',
		'args'=>array('id'=>$version_id));

	//
	// Get a new UUID
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUUID');
	$rc = ciniki_core_dbUUID($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$uuid = $rc['uuid'];

	//
	// Insert the crop action into the ciniki_image_actions table for the thumbnail, if the original was not square
	//
	if( $thumb_crop_data != '' ) {
		$strsql = "INSERT INTO ciniki_image_actions (uuid, business_id, image_id, "
			. "version, sequence, action, params, date_added, last_updated"
			. ") VALUES ("
			. "'" . ciniki_core_dbQuote($ciniki, $uuid) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
			. "'" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
			. "'thumbnail', 1, 1, "
			. "'" . ciniki_core_dbQuote($ciniki, $thumb_crop_data) . "', "
			. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
		$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'2280', 'msg'=>'Unable to crop thumbnail', 'err'=>$rc['err']));	
		}
		$action_id = $rc['insert_id'];
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'uuid', $uuid);
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'image_id', $image_id);
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'version', 'thumbnail');
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'sequence', 1);
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'action', 1);
		ciniki_core_dbAddModuleHistory($ciniki, 'ciniki.images', 'ciniki_image_history', 
			$business_id, 1, 'ciniki_image_actions', $action_id, 'params', $thumb_crop_data);
		$ciniki['syncqueue'][] = array('push'=>'ciniki.images.action',
			'args'=>array('id'=>$action_id));
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
