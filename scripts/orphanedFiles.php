<?php
//
// Description
// -----------
// This script will check for badly formed email addresses in the database
// 

//
// Initialize Ciniki by including the ciniki_api.php
//
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
    $ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}
// loadMethod is required by all function to ensure the functions are dynamically loaded
require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');
require_once($ciniki_root . '/ciniki-mods/core/private/checkModuleFlags.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
    error_log("unable to initialize core");
    exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];
$ciniki['session']['user']['id'] = -4;  // Setup to Ciniki Robot

$strsql = "SELECT "
    . "ciniki_tenants.name, "
    . "ciniki_tenants.uuid AS tenant_uuid, "
    . "ciniki_images.uuid AS image_uuid "
    . "FROM ciniki_images, ciniki_tenants "
    . "WHERE ciniki_images.tnid = ciniki_tenants.id "
    . "ORDER BY ciniki_tenants.uuid, ciniki_images.uuid "
    . "";
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
$rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.images', array(
    array('container'=>'tenants', 'fname'=>'tenant_uuid', 'fields'=>array('uuid'=>'tenant_uuid', 'name')),
    array('container'=>'images', 'fname'=>'image_uuid', 'fields'=>array('uuid'=>'image_uuid')),
    ));
if( $rc['stat'] != 'ok' ) {
    return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.170', 'msg'=>'Unable to load ', 'err'=>$rc['err']));
}
$tenants = isset($rc['tenants']) ? $rc['tenants'] : array();
//print_r($tenants);
//print_r($tenants['ad81d5b4-c6a7-11e5-a78b-001851fcc939']);

$sizes = 0;
$tldir = opendir($ciniki['config']['ciniki.core']['storage_dir']);
while(($tltr = readdir($tldir)) !== false ) {
    if( preg_match("/^[0-9a-f]$/", $tltr) ) {
        $fname = $ciniki['config']['ciniki.core']['storage_dir'] . '/' . $tltr;
        $tdir = opendir($fname);
        while(($tenant = readdir($tdir)) !== false ) {
            if( $tenant == '.' || $tenant == '..' ) {
                continue;
            }
            if( !isset($tenants[$tenant]) ) {
                print("Orphaned Tenant: " . $tenant . "\n");
            }
            if( file_exists($fname . '/' . $tenant . '/ciniki.images') ) {
                $i_dirname = $fname . '/' . $tenant . '/ciniki.images';
                $ildir = opendir($i_dirname);
                if( !$ildir ) { 
                    continue;
                }
                while(($iltr = readdir($ildir)) !== false) {
                    if( preg_match("/^[0-9a-f]$/", $iltr) ) {
                        $dirname = $i_dirname . '/' . $iltr;
                        if( $udir = opendir($dirname) ) {
                            while(($uuid = readdir($udir)) !== false) {
                                if( $uuid == '.' || $uuid == '..' ) {
                                    continue;
                                }
                                if( !isset($tenants[$tenant]['images'][$uuid]) ) {
                                    $sizes += filesize($dirname . '/' . $uuid);
//                                    print($tenant . "[$uuid]" . "\n");
//                                    print($dirname  . ' -- ' . $uuid . "\n");
                                }
                            } 
                        }
                    }
                } 
            }
        }
    }
}

print(($sizes/1024/1024) . "\n");
exit(0);
?>
