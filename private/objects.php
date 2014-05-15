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
function ciniki_images_objects($ciniki) {
	$objects = array();
	$objects['image'] = array(
		'name'=>'Image',
		'sync'=>'yes',
		'backup'=>'no',
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
		'sync'=>'yes',
		'backup'=>'no',
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
		'sync'=>'yes',
		'backup'=>'no',
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
	$objects['ref'] = array(
		'name'=>'Image References',
		'sync'=>'yes',
		'backup'=>'no',
		'table'=>'ciniki_image_refs',
		'fields'=>array(
			'ref_id'=>array('ref'=>'ciniki.images.image'),
			'object'=>array(),
			'object_id'=>array('oref'=>'object'),
			'object_field'=>array(),
			),
		'history_table'=>'ciniki_image_history',
		);
	
	return array('stat'=>'ok', 'objects'=>$objects);
}
?>
