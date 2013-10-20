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
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'objects');
	return ciniki_images_objects($ciniki);
}
?>
