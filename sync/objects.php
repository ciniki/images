<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_images_sync_objects($ciniki, &$sync, $business_id, $args) {
	
	//
	// NOTES: When pushing a change, grab the history for the current session
	// When increment/partial/full, sync history on it's own
	//

	//
	// Working on version 2 of sync, completely object based
	//
	$objects = array();
	$objects['image'] = array(
		'name'=>'Image',
		'table'=>'ciniki_images',
		'fields'=>array(
			'user_id'=>array('ref'=>'ciniki.users.user'),
			'perms'=>array(),
			'type'=>array(),
			'original_filename'=>array(),
			'remote_id'=>array(),
			'title'=>array(),
			'caption'=>array(),
			'image'=>array(),
			'checksum'=>array(),
			),
		'details'=>array('key'=>'image_id', 'table'=>'ciniki_image_details'),
		'history_table'=>'ciniki_image_history',
		);
	$objects['version'] = array(
		'name'=>'Image Version',
		'table'=>'ciniki_image_versions',
		'fields'=>array(
			'image_id'=>array('ref'=>'ciniki.images.image'),
			'version'=>array(),
			'flags'=>array(),
			),
		'history_table'=>'ciniki_image_history',
		);
	$objects['action'] = array(
		'name'=>'Image Action',
		'table'=>'ciniki_image_actions',
		'fields'=>array(
			'image_id'=>array('ref'=>'ciniki.images.image'),
			'version'=>array(),
			'sequence'=>array(),
			'action'=>array(),
			'params'=>array(),
			),
		'history_table'=>'ciniki_image_history',
		);
	
	return array('stat'=>'ok', 'objects'=>$objects);
}
?>
