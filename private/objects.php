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
        'o_container'=>'images',
        'o_name'=>'image',
        'fields'=>array(
            'user_id'=>array('name'=>'User', 'ref'=>'ciniki.users.user'),
            'perms'=>array('name'=>'Permissions'),
            'type'=>array('name'=>'Image Type'),
            'original_filename'=>array('name'=>'Original Filename'),
            'remote_id'=>array('name'=>'Remove ID'),
            'title'=>array('name'=>'Title'),
            'caption'=>array('name'=>'Caption'),
            'image'=>array('name'=>'Image Data'),
            'checksum'=>array('name'=>'Checksum'),
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
