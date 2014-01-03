<?php
//
// Description
// -----------
// This script will update the cache files required for images in the ciniki-cache directory
// for all businesses
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
require_once($ciniki_root . '/ciniki-mods/cron/private/execCronMethod.php');
require_once($ciniki_root . '/ciniki-mods/cron/private/getExecutionList.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
	error_log("unable to initialize core");
	exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];

//
// Check if there was an override to the cache_dir.  This is used when updating a mounted cache tree
//
if( isset($argv[2]) && $argv[2] != '' ) {
	$ciniki['config']['ciniki.core']['cache_dir'] = $argv[2];
}

//
// Check the cache directory exists
//
if( !isset($ciniki['config']['ciniki.core']['cache_dir']) ) {
	error_log('CACHE-ERR[cacheUpdate.php]: config error, cache_dir not set.');
	exit(0);
}

if( !is_dir($ciniki['config']['ciniki.core']['cache_dir']) ) {
	error_log('CACHE-ERR[cacheUpdate.php]: cache_dir does not exist.');
	exit(0);
}

ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheThumbnail');
ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'loadCacheOriginal');

//
// Get the list of images
//
$strsql = "SELECT "
	. "ciniki_businesses.id AS business_id, "
	. "ciniki_businesses.uuid AS business_uuid, "
	. "ciniki_images.id AS image_id, "
	. "ciniki_images.uuid AS image_uuid, "
	. "UNIX_TIMESTAMP(ciniki_images.last_updated) AS last_updated "
	. "FROM ciniki_images "
	. "LEFT JOIN ciniki_businesses ON (ciniki_images.business_id = ciniki_businesses.id) "
	. "";
if( !isset($argv[1]) ) {
	// Default to last 24 hours of images
	$strsql .= "WHERE (UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(ciniki_images.last_updated)) < 86400 ";
} elseif( $argv[1] > 0 ) {
	$strsql .= "WHERE (UNIX_TIMESTAMP(UTC_TIMESTAMP()) - UNIX_TIMESTAMP(ciniki_images.last_updated)) < '" . ciniki_core_dbQuote($ciniki, $argv[1]) . "' ";
}

$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image'); 
if( $rc['stat'] != 'ok' ) {
	error_log('CACHE-ERR[cacheUpdate.php]: Unable to get list of images');
	exit(0);
}
if( isset($rc['rows']) ) {
	$images = $rc['rows'];
} else {
	$images = array();
}

$utc_offset = date_offset_get(new DateTime);
foreach($images as $iid => $image) {
	$ciniki['business']['settings']['cache_dir'] = $ciniki['config']['ciniki.core']['cache_dir'] . '/'
		. $image['business_uuid'][0] . '/' . $image['business_uuid'];

	$cache_dir = $ciniki['business']['settings']['cache_dir'] . '/' 
		. $image['image_uuid'][0] . '/' . $image['image_uuid'];
	//
	// Generate the required thumbnails for the UI
	//
	if( !file_exists("$cache_dir/t75.jpg")
		|| (filemtime("$cache_dir/t75.jpg") - $utc_offset) < $image['last_updated'] ) {
		ciniki_images_loadCacheThumbnail($ciniki, $image['business_id'], $image['image_id'], 75);
	}	
	//
	// Generate the thumbnail images for PDF creation
	//
	if( !file_exists("$cache_dir/t300.jpg")
		|| (filemtime("$cache_dir/t300.jpg") - $utc_offset) < $image['last_updated'] ) {
		ciniki_images_loadCacheThumbnail($ciniki, $image['business_id'], $image['image_id'], 300);
	}
	// Quad/4 to a page PDF sizes
//	if( !file_exists("$cache_dir/o300.jpg")
//		|| (filemtime("$cache_dir/t300.jpg") - $utc_offset) < $image['last_updated'] ) {
//		ciniki_images_loadCacheThumbnail($ciniki, $image['business_id'], $image['image_id'], 300);
//	}
	// Single page sizes
	if( !file_exists("$cache_dir/o2000_2000.jpg")
		|| (filemtime("$cache_dir/o2000_2000.jpg") - $utc_offset) < $image['last_updated'] ) {
		ciniki_images_loadCacheOriginal($ciniki, $image['business_id'], $image['image_id'], 2000, 2000);
	}

}

exit(0);
?>
