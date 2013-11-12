<?php
//
// Description
// -----------
// This method will check the integrity of the data in the images module.  All
// cross references within the module will be verified, and added if missing.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_images_dbIntegrityCheck($ciniki) {
	//
	// Find all the required and optional arguments
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'fix'=>array('required'=>'no', 'default'=>'no', 'name'=>'Fix Problems'),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];
	
	//
	// Check access to business_id as owner, or sys admin
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
	$rc = ciniki_images_checkAccess($ciniki, $args['business_id'], 'ciniki.images.dbIntegrityCheck', 0, 0);
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFixTableHistory');

	//
	// FIXME: Check the refs are all valid, and remove invalid
	//

	//
	// Check for items that are missing a add value in history
	//
	if( $args['fix'] == 'yes' ) {
		$rc = ciniki_core_dbFixTableHistory($ciniki, 'ciniki.images', $args['business_id'],
			'ciniki_images', 'ciniki_image_history', 
			array('uuid', 'user_id', 'perms', 'type', 'original_filename', 'remote_id', 
				'title', 'caption', 'checksum'));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}

		//
		// Check ciniki_image_versions for missing history
		//
		$rc = ciniki_core_dbFixTableHistory($ciniki, 'ciniki.images', $args['business_id'],
			'ciniki_image_versions', 'ciniki_image_history', 
			array('uuid', 'image_id','version','flags'));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		
		//
		// Check ciniki_image_actions for missing history
		//
		$rc = ciniki_core_dbFixTableHistory($ciniki, 'ciniki.images', $args['business_id'],
			'ciniki_image_actions', 'ciniki_image_history', 
			array('uuid', 'image_id','version','sequence', 'action', 'params'));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		
		//
		// Check ciniki_image_refs for missing history
		//
		$rc = ciniki_core_dbFixTableHistory($ciniki, 'ciniki.images', $args['business_id'],
			'ciniki_image_refs', 'ciniki_image_history', 
			array('uuid', 'ref_id','object','object_id', 'object_field'));
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		
		//
		// Check for items missing a UUID
		//
		$strsql = "UPDATE ciniki_image_history SET uuid = UUID() WHERE uuid = ''";
		$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}

		//
		// FIXME: Add code to add any missing detail history
		//

		//
		// Remove entries with - in table_key
		//
		$strsql = "DELETE FROM ciniki_image_history WHERE table_key LIKE '%-%'";
		$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}

		//
		// Remove any entries with blank table_key, they are useless we 
		// don't know what they were attached to
		//
		$strsql = "DELETE FROM ciniki_image_history WHERE table_key = ''";
		$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.images');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
	}

	return array('stat'=>'ok');
}
?>
