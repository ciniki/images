<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The ID of the business to get the image from.
// image_id:			The ID if the image requested.
// version:				The version of the image (regular, thumbnail)
//
//						*note* the thumbnail is not referring to the size, but to a 
//						square cropped version, designed for use as a thumbnail.
//						This allows only a portion of the original image to be used
//						for thumbnails, as some images are too complex for thumbnails.
//
// maxwidth:			The max width of the longest side should be.  This allows
//						for generation of thumbnail's, etc.
//
// maxlength:			The max length of the longest side should be.  This allows
//						for generation of thumbnail's, etc.
//
// Returns
// -------
// Binary image data
//
function ciniki_images_get($ciniki) {
	//
	// Check args
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'image_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No image specified'), 
		'version'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No version specified'),
		'maxwidth'=>array('required'=>'no', 'default'=>'0', 'blank'=>'no', 'errmsg'=>'No size specified'),
		'maxheight'=>array('required'=>'no', 'default'=>'0', 'blank'=>'no', 'errmsg'=>'No size specified'),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check session user permission to run this function for this business
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
	$rc = ciniki_images_checkAccess($ciniki, $args['business_id'], 'ciniki.images.get', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}
	
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'getImage');
	return ciniki_images_getImage($ciniki, $args['business_id'], $args['image_id'], $args['version'], $args['maxwidth'], $args['maxheight']);
}
?>
