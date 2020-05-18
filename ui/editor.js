//
// This app will handle the listing, additions and deletions of images.  These are associated tenant.
//
function ciniki_images_editor() {
    //
    // images panel
    //
    this.main = new M.panel('Image Cropper', 'ciniki_images_editor', 'main', 'mc', 'narrow narrowaside', 'sectioned', 'ciniki.images.editor.main');
    this.main.image_id = 0;
    this.main.data = {};
    this.main.sections = {
        'thumbnail_image':{'label':'Thumbnail', 'type':'imageform', 'aside':'yes', 'fields':{
            't_image_id':{'label':'', 'type':'image_id', 'version':'thumbnail', 'hidelabel':'yes', 'history':'no'},
            }},
        'original_image':{'label':'Original', 'type':'imageform', 'aside':'yes', 'fields':{
            'o_image_id':{'label':'', 'type':'image_id', 'version':'original', 'hidelabel':'yes', 'history':'no'},
            }},
        '_quickcrop':{'label':'Quick Crop', 'buttons':{
            'topleft':{'label':'Top Left', 'fn':'M.ciniki_images_editor.main.crop("topleft","");'},
            'center':{'label':'Center', 'fn':'M.ciniki_images_editor.main.crop("center","");'},
            'bottomright':{'label':'Bottom Right', 'fn':'M.ciniki_images_editor.main.crop("bottomright","");'},
            }},
        '_crop':{'label':'Adjust Crop', 'buttons':{
            'minus10':{'label':'Up/Left 10%', 'fn':'M.ciniki_images_editor.main.crop("upleft", 10);'},
            'minus05':{'label':'Up/Left 5%', 'fn':'M.ciniki_images_editor.main.crop("upleft", 5);'},
            'plus05':{'label':'Down/Right 5%', 'fn':'M.ciniki_images_editor.main.crop("downright", 5);'},
            'plus10':{'label':'Down/Right 10%', 'fn':'M.ciniki_images_editor.main.crop("downright", 10);'},
            }},
        };
    this.main.fieldValue = function(s, i, d) { 
        if( s == 'thumbnail_image' || s == 'original_image' ) {
            return this.data['image_id']; 
        }
    }
    this.main.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.images.imageHistory', 'args':{'tnid':M.curTenantID, 'image_id':this.image_id, 'field':i}};
    }
    this.main.crop = function(pos, amt) {
        M.api.getJSONCb('ciniki.images.imageEdit', {'tnid':M.curTenantID, 'image_id':this.image_id, 'version':'thumbnail', 'action':'crop', 'position':pos, 'amount':amt}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_images_editor.main;
            p.refresh();
            p.show();
        });
    }
    this.main.open = function(cb,iid) {
        if( iid != null ) { this.image_id = iid; }
        M.api.getJSONCb('ciniki.images.imageGet', {'tnid':M.curTenantID, 'image_id':this.image_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_images_editor.main;
            p.data = rsp.image;
            p.refresh();
            p.show(cb);
        });
    }
    this.main.addClose('Back');

    //
    // Arguments:
    // aG - The arguments to be parsed into args
    //
    this.start = function(cb, appPrefix, aG) {
        args = {};
        if( aG != null ) { args = eval(aG); }

        //
        // Create the app container if it doesn't exist, and clear it out
        // if it does exist.
        //
        var appContainer = M.createContainer(appPrefix, 'ciniki_images_editor', 'yes');
        if( appContainer == null ) {
            M.alert('App Error');
            return false;
        } 

        this.main.open(cb, args.image_id);
    }
}
