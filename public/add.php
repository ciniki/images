<?php
//
// Description
// ===========
// This function will add a new image into the image database.  The image
// data should be posted as a file upload.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:		The ID of the business to add the image to.
// 
// Returns
// -------
// <rsp stat='ok' id='34' />
//
function ciniki_images_add(&$ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];

    //  
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['business_id'], 'ciniki.images.add'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

	//  
	// Turn off autocommit
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Check to see if an image was uploaded
	//
	if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'656', 'msg'=>'Upload failed, file too large.'));
	}
	// FIXME: Add other checkes for $_FILES['uploadfile']['error']

	//
	// Check for a uploaded file
	//
	if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['tmp_name'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'657', 'msg'=>'Upload failed, no file specified.'));
	}
	$uploaded_file = $_FILES['uploadfile']['tmp_name'];

	//
	// Add the image into the database
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'insertFromUpload');
	$rc = ciniki_images_insertFromUpload($ciniki, $args['business_id'], $ciniki['session']['user']['id'], 
		$_FILES['uploadfile'], 1, $_FILES['uploadfile']['name'], '', 'no');
	// If a duplicate image is found, then use that id instead of uploading a new one
	if( $rc['stat'] != 'ok' && $rc['err']['code'] != '330' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.images');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'658', 'msg'=>'Internal Error', 'err'=>$rc['err']));
	}
	if( !isset($rc['id']) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.images');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'659', 'msg'=>'Invalid file type'));
	}
	$image_id = $rc['id'];

	//
	// Commit the database changes
	//
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.images');
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	return array('stat'=>'ok', 'id'=>$image_id);
}
?>
