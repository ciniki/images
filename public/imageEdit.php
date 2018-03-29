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
function ciniki_images_imageEdit($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'image_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Image'),
        'version'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Version'),
        'action'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Action'),
        'position'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Position'),
        'amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Amount'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['tnid'], 'ciniki.images.imageEdit');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

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
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.154', 'msg'=>'Image not found', 'err'=>$rc['err']));
    }
    if( !isset($rc['images'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.155', 'msg'=>'Unable to find Image'));
    }
    $image = $rc['images'][0];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadImage');
    $rc = ciniki_images_loadImage($ciniki, $args['tnid'], $args['image_id'], 'original');  
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.151', 'msg'=>'Unable to load image', 'err'=>$rc['err']));
    }
    if( !isset($rc['image']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.152', 'msg'=>'Unable to load image', 'err'=>$rc['err']));
    }
    $img = $rc['image'];

    //
    // Determine the size or the original, and the crop area for a thumbnail
    //
    $width = $img->getimagewidth();
    $height = $img->getimageheight();
    if( $width < 1 || $height < 1 ) {
        // Check to make sure there is some size to the image
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.153', 'msg'=>'The image is empty'));
    }

    //
    // Load the actions
    //
    $strsql = "SELECT id, version, sequence, action, params "
        . "FROM ciniki_image_actions "
        . "WHERE image_id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
        . "AND version = '" . ciniki_core_dbQuote($ciniki, $args['version']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY sequence "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.150', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $actions = $rc['rows'];
    
    if( $args['action'] == 'crop' ) {
        //
        // Load the action
        //
        foreach($actions as $action) {
            if( $action['action'] == 1 && isset($args['position']) ) {
                $thumb_crop_data = $action['params'];
                list($w, $h, $x, $y) = explode(',', $action['params']);
                if( $width < $height ) {
                    $offset = floor(($height-$width)/2);
                    if( $args['position'] == 'topleft' ) {
                        $thumb_crop_data = $width . ',' . $width . ',0,0';
                    } elseif( $args['position'] == 'center' ) {
                        $thumb_crop_data = $width . ',' . $width . ',0,' . $offset;
                    } elseif( $args['position'] == 'bottomright' ) {
                        $thumb_crop_data = $width . ',' . $width . ',0,' . ($height-$width);
                    } elseif( $args['position'] == 'upleft' && isset($args['amount']) && $args['amount'] != '' ) {
                        $y += (($args['amount']/100) * $height);
                        if( $y < 0 ) {
                            $y = 0;
                        }
                        $thumb_crop_data = $width . ',' . $width . ',0,' . $y;
                    } elseif( $args['position'] == 'downright' && isset($args['amount']) && $args['amount'] != '' ) {
                        $y -= (($args['amount']/100) * $height);
                        if( $y > ($height-$width) ) {
                            $y = ($height-$widht);
                        }
                        $thumb_crop_data = $width . ',' . $width . ',0,' . $y;
                    }
                } elseif( $width > $height ) {
                    $offset = floor(($width-$height)/2);
                    if( $args['position'] == 'topleft' ) {
                        $thumb_crop_data = $height . ',' . $height . ',0,0';
                    } elseif( $args['position'] == 'center' ) {
                        $thumb_crop_data = $height . ',' . $height . ',' . $offset . ',0';
                    } elseif( $args['position'] == 'bottomright' ) {
                        $thumb_crop_data = $height . ',' . $height . ',' . ($width-$height) . ',0';
                    } elseif( $args['position'] == 'upleft' && isset($args['amount']) && $args['amount'] != '' ) {
                        $x += (($args['amount']/100) * $width);
                        if( $x < 0 ) {
                            $x = 0;
                        }
                        $thumb_crop_data = $height . ',' . $height . ',' . $x . ',0';
                    } elseif( $args['position'] == 'downright' && isset($args['amount']) && $args['amount'] != '' ) {
                        $x -= (($args['amount']/100) * $width);
                        if( $x > ($width-$height) ) {
                            $x = ($width-$height);
                        }
                        $thumb_crop_data = $height . ',' . $height . ',' . $x . ',0';
                    }
                }
                if( $thumb_crop_data != $action['params'] ) {
                    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.images.action', $action['id'], array(
                        'params' => $thumb_crop_data),
                        0x04);
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.156', 'msg'=>'Unable to set crop', 'err'=>$rc['err']));
                    }
                    $strsql = "UPDATE ciniki_images SET last_updated = UTC_TIMESTAMP() "
                        . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['image_id']) . "' "
                        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                        . "";
                    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
                    $rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.images');
                    if( $rc['stat'] != 'ok' ) {
                        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.157', 'msg'=>'Unable to update crop', 'err'=>$rc['err']));
                    }
                }
            }
        }
    }

    return array('stat'=>'ok');
}
?>
