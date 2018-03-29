<?php
//
// Description
// ===========
// This method will return all the information about an image.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the image is attached to.
// image_id:          The ID of the image to get the details for.
//
// Returns
// -------
//
function ciniki_images_imageGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'image_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Image'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['tnid'], 'ciniki.images.imageGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Image
    //
    if( $args['image_id'] == 0 ) {
        $image = array('id'=>0,
            'user_id'=>'',
            'perms'=>'',
            'type'=>'',
            'original_filename'=>'',
            'remote_id'=>'',
            'title'=>'',
            'caption'=>'',
            'image'=>'',
            'checksum'=>'',
        );
    }

    //
    // Get the details for an existing Image
    //
    else {
        $strsql = "SELECT ciniki_images.id, "
            . "ciniki_images.user_id, "
            . "ciniki_images.perms, "
            . "ciniki_images.type, "
            . "ciniki_images.original_filename, "
            . "ciniki_images.remote_id, "
            . "ciniki_images.title, "
            . "ciniki_images.caption, "
            . "ciniki_images.checksum "
            . "FROM ciniki_images "
            . "WHERE ciniki_images.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_images.id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.images', array(
            array('container'=>'images', 'fname'=>'id', 
                'fields'=>array('image_id'=>'id', 'user_id', 'perms', 'type', 'original_filename', 'remote_id', 'title', 'caption', 'checksum'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.148', 'msg'=>'Image not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['images'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.149', 'msg'=>'Unable to find Image'));
        }
        $image = $rc['images'][0];
    }

    return array('stat'=>'ok', 'image'=>$image);
}
?>
