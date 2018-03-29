<?php
//
// Description
// -----------
// This function will return an array of image information based
// on the image id's in the 
//
// Arguments
// ---------
// ciniki:
// tnid:     The ID of the tenant to get the images from.
// images:          The list of image ID's to get from the database.
// 
// Returns
// -------
//
function ciniki_images_getImagesFromArray($ciniki, $tnid, $images) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');

    $strsql = "SELECT id, perms, type, title, caption "
        . "FROM ciniki_images "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND id IN (" . ciniki_core_dbQuoteIDs($ciniki, $images) . ")";
    return ciniki_core_dbHashIDQuery($ciniki, $strsql, 'ciniki.images', 'images', 'id');
}
?>
