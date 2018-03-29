//
// This app will handle the listing, additions and deletions of images.  These are associated tenant.
//
function ciniki_images_cropper() {
    //
    // images panel
    //
    this.menu = new M.panel('Image Cropper', 'ciniki_images_cropper', 'menu', 'mc', 'full', 'sectioned', 'ciniki.images.cropper.menu');
    this.menu.sections = {
        'images':{'label':'Gallery', 'type':'simplethumbs'},
        };
    this.menu.thumbFn = function(s, i, d) {
        return 'M.startApp(\'ciniki.images.editor\',null,\'M.ciniki_images_cropper.menu.open();\',\'mc\',{\'image_id\':\'' + d.id + '\'});';
    };
    this.menu.open = function(cb) {
        M.api.getJSONCb('ciniki.images.list', {'tnid':M.curTenantID, 'imagedata':'yes'}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_images_cropper.menu;
            p.data = rsp;
            p.refresh();
            p.show(cb);
        });
    }
    this.menu.addClose('Back');

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
        var appContainer = M.createContainer(appPrefix, 'ciniki_images_cropper', 'yes');
        if( appContainer == null ) {
            alert('App Error');
            return false;
        } 

        this.menu.open(cb);
    }
}
