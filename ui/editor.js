//
// This app will handle the listing, additions and deletions of images.  These are associated tenant.
//
function ciniki_images_editor() {
    //
    // images panel
    //
    this.main = new M.panel('Image Cropper', 'ciniki_images_editor', 'main', 'mc', 'medium', 'sectioned', 'ciniki.images.editor.main');
    this.main.image_id = 0;
    this.main.data = {};
    this.main.sections = {
        '_image':{'label':'', 'type':'imageform', 'fields':{
            'image_id':{'label':'', 'type':'image_id', 'version':'thumbnail', 'hidelabel':'yes', 'history':'no'},
            }},
        '_crop':{'label':'Crop', 'buttons':{
            'topleft':{'label':'Top Left', 'fn':'M.ciniki_images_editor.main.crop("topleft");'},
            'center':{'label':'Center', 'fn':'M.ciniki_images_editor.main.crop("center");'},
            'bottomright':{'label':'Bottom Right', 'fn':'M.ciniki_images_editor.main.crop("bottomright");'},
            }},
        };
    this.main.fieldValue = function(s, i, d) { return this.data[i]; }
    this.main.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.images.imageHistory', 'args':{'tnid':M.curTenantID, 'image_id':this.image_id, 'field':i}};
    }
    this.main.crop = function(pos) {
        M.api.getJSONCb('ciniki.images.imageEdit', {'tnid':M.curTenantID, 'image_id':this.image_id, 'version':'thumbnail', 'action':'crop', 'position':pos}, function(rsp) {
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
            alert('App Error');
            return false;
        } 

        this.main.open(cb, args.image_id);
    }
}
