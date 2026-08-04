(function ($) { 
    "use strict";
    
    //$('.notice').remove();
    
    $(document).ready(function() {

        // ── PRO feature lock (free / unlicensed) ────────────────────────────
        // Server-side enforcement lives in shoppablelookbook_apply_free_limits();
        // this only presents the locked state. Bound BEFORE the generic
        // .image-radio handlers below so stopImmediatePropagation() wins.
        if ( window.litLookbook && parseInt( litLookbook.isPro, 10 ) === 0 ) {
            var lockedEls = $()
                .add( $('input[name="lookbook_layout"][value="3"], input[name="lookbook_layout"][value="4"]').closest('.form-option') )
                .add( $('input[name="lookbook_box_radius"]').closest('.form-row') )
                .add( $('input[name="lookbook_box_color"][value="custom"]').closest('.form-option') )
                .add( $('.box-custom-color-wrap') )
                .add( $('input[name="lookbook_opacity"]').closest('.lb-col') )
                .add( $('input[name="lookbook_animation"][value="beat"], input[name="lookbook_animation"][value="bounce"]').closest('.form-option') )
                .add( $('input[name="lookbook_trigger"][value="openall"], input[name="lookbook_trigger"][value="openfirst"], input[name="lookbook_trigger"][value="accordion"]').closest('.form-option') )
                .add( $('input[name="lookbook_mobilebox"][value="sheet"]').closest('.form-option') )
                .add( $('.marker-image-bar').closest('.form-row') );

            lockedEls.each(function () {
                var $el = $(this).addClass('lb-locked');
                $el.find('input, button').prop('disabled', true);
            });

            // The PRO badges themselves are rendered in PHP (always visible,
            // also when licensed); when locked they open the upgrade page.
            lockedEls.find('.lb-pro-badge').addClass('lb-pro-badge-link').on('click', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.open( litLookbook.upgradeUrl || 'https://douple.net/shoppable-lookbook/', '_blank' );
            });

            lockedEls.find('.image-radio').add(lockedEls.filter('.image-radio')).on('click', function (e) {
                if ( $(e.target).closest('.lb-pro-badge-link').length ) { return; }
                e.preventDefault();
                e.stopImmediatePropagation();
            });
        }

        // Editor sidebar: show the Marker section ABOVE Box (real DOM move so
        // keyboard order matches; sections render as HL, Box, Marker, Display).
        (function () {
            var $marker = $('.lookbook-settings > .lb-section').has('input[name="lookbook_marker_color"]').first();
            var $box    = $('.lookbook-settings > .lb-section').has('input[name="lookbook_box_color"]').first();
            if ($marker.length && $box.length) {
                $marker.insertBefore($box);
            }
        })();

        //Copy shortcode
        $(".lookbook-shortcode").on("click", function (e) {
            //$(this).select();
            var copyText = document.getElementById( 'lookbook-' + $(this).data('id') );

            /* Select the text field */
            copyText.select();
            copyText.setSelectionRange(0, 99999); /*For mobile devices*/

            /* Copy the text inside the text field */
            document.execCommand("copy");

            $(this).parent().find('.lookbook-copied').addClass('active');
            $(this).parent().find('.lookbook-copied').delay(600).queue(function(){
                $(this).removeClass('active').dequeue();
            });

            e.preventDefault();
        });

        //Copy shortcode
        $("#lookbook-shortcode").on("click", function (e) {
            //$(this).select();
            var copyText = document.getElementById( 'lookbook-shortcode' );

            /* Select the text field */
            copyText.select();
            copyText.setSelectionRange(0, 99999); /*For mobile devices*/

            /* Copy the text inside the text field */
            document.execCommand("copy");
            
            $(this).parent().find('.lookbook-copied').addClass('active');
            $(this).parent().find('.lookbook-copied').delay(600).queue(function(){
                $(this).removeClass('active').dequeue();
            });

            e.preventDefault();
        });

        //Radio checked
        $(".image-radio").each(function(){
            if($(this).find('input[type="radio"]').first().attr("checked")){
                $(this).addClass('image-radio-checked');
            }else{
                $(this).removeClass('image-radio-checked');
            }
        });

        //Sync the input state
        $(".image-radio").on("click", function(e){
            $(this).parent().parent().find(".image-radio").removeClass('image-radio-checked');
            $(this).addClass('image-radio-checked');
            var $radio = $(this).find('input[type="radio"]');
            $radio.prop("checked",!$radio.prop("checked"));

            if ( $(this).hasClass('marker1') ) litRefreshMarkers();
            if ( $(this).hasClass('color1') ) {
                $('.lit-selected').removeClass('light dark white yellow blue green rose gray custom').addClass($radio.val());
                if ( $radio.val() === 'custom' ) { $('.box-custom-color-wrap').slideDown(150); }
                else { $('.box-custom-color-wrap').slideUp(150); }
                applyOpacityPreview();
            }
            if ( $(this).hasClass('content1') ) {
                $('.lit-selected').removeClass('lb-content-light lb-content-dark');
                if ( $radio.val() !== 'auto' ) { $('.lit-selected').addClass('lb-content-' + $radio.val()); }
            }
            if ( $(this).hasClass('anim1') ) {
                $('#markers').removeClass('lookbook-anim-none lookbook-anim-pulse lookbook-anim-beat lookbook-anim-bounce').addClass('lookbook-anim-' + $radio.val());
            }
            if ( $(this).hasClass('image1') ) {
                // Box Style suggests a matching Box Corner default: Instagram
                // (style 2) reads best lightly rounded, Tag (style 3) as a
                // pill; the others stay square unless the user rounds them.
                var boxCornerDefaults = { '2': 5, '3': 50 };
                $('.boxradius-range').val(boxCornerDefaults.hasOwnProperty($radio.val()) ? boxCornerDefaults[$radio.val()] : 0);
                applyBoxRadius();
            }

            e.preventDefault();
        });

        //Field-heading "!" hint icons: click/tap toggles the tooltip (hover
        //already shows it via CSS); a keyboard Enter/Space does the same.
        //The tip is right-anchored to the icon by default, but a narrow
        //sidebar can still push it off either edge — clamp it into view,
        //same technique as clampBoxToViewport() on the frontend.
        function clampHintTip($hint) {
            var $tip = $hint.find('.lb-hint-tip');
            if ( ! $tip.length ) { return; }
            $tip.css('transform', '');
            var rect  = $tip[0].getBoundingClientRect(),
                pad   = 8,
                vw    = document.documentElement.clientWidth,
                shift = 0;
            if ( rect.right > vw - pad ) { shift = vw - pad - rect.right; }
            if ( rect.left + shift < pad ) { shift = pad - rect.left; }
            if ( shift ) { $tip.css('transform', 'translateX(' + shift + 'px)'); }
        }
        $(document).on('mouseenter focus', '.lb-hint', function () {
            clampHintTip($(this));
        });
        $(document).on('click', '.lb-hint', function (e) {
            e.stopPropagation();
            var isOpen = $(this).hasClass('lb-hint-open');
            $('.lb-hint').removeClass('lb-hint-open');
            if ( ! isOpen ) {
                $(this).addClass('lb-hint-open');
                clampHintTip($(this));
            }
        });
        $(document).on('keydown', '.lb-hint', function (e) {
            if ( e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32 ) {
                e.preventDefault();
                $(this).trigger('click');
            }
        });
        $(document).on('click', function () {
            $('.lb-hint').removeClass('lb-hint-open');
        });

        //Open search form again
        openSearch();
        //Drag markers
        draggableMarker();
        //Close marker
        closeMarker();
        //Sidebar hotspot list
        litRefreshMarkerList();

        //Click a hotspot list item: flash-highlight its marker on the image
        $(document).on('click', '.lit-marker-item', function (e) {
            if ( $(e.target).hasClass('lit-marker-item-remove') ) { return; }
            var $marker = $('#' + $(this).data('marker'));
            if ( ! $marker.length ) { return; }
            $('.lit-marker').removeClass('lit-flash');
            $marker.addClass('lit-flash');
            setTimeout(function () { $marker.removeClass('lit-flash'); }, 1400);
            if ( $marker[0].scrollIntoView ) {
                $marker[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        //Remove a marker from the hotspot list
        $(document).on('click', '.lit-marker-item-remove', function () {
            var $marker = $('#' + $(this).closest('.lit-marker-item').data('marker'));
            $marker.fadeOut(150, function () {
                $(this).remove();
                litRefreshMarkerList();
            });
        });

        //i18n helper for delegated handlers
        var li18n = (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n : {};
        function lt(key, fallback) { return (li18n && li18n[key]) ? li18n[key] : fallback; }

        //Duplicate a lookbook from the list page
        $(document).on('click', '.lookbook-duplicate', function (e) {
            e.preventDefault();
            var $btn = $(this);
            if ( $btn.data('busy') ) { return; }
            $btn.data('busy', 1);
            $.post(ajaxurl, {
                action: 'shoppablelookbook_duplicate_db',
                _ajax_nonce: litLookbook.nonce,
                id: $btn.data('id')
            }, function (res) {
                if ( res && res.success ) {
                    window.location.reload();
                } else {
                    $btn.data('busy', 0);
                    window.alert(lt('duplicateFailed', 'Could not duplicate the lookbook.'));
                }
            });
        });

        //Export a lookbook as a JSON download
        $(document).on('click', '.lookbook-export', function (e) {
            e.preventDefault();
            window.location.href = ajaxurl + '?action=shoppablelookbook_export&_ajax_nonce=' + encodeURIComponent(litLookbook.nonce) + '&id=' + encodeURIComponent($(this).data('id'));
        });

        //Import a lookbook from a JSON export file
        $('#lookbook-import').on('click', function (e) {
            e.preventDefault();
            $('#lookbook-import-file').trigger('click');
        });
        $('#lookbook-import-file').on('change', function () {
            var file = this.files && this.files[0];
            this.value = '';
            if ( ! file ) { return; }

            var reader = new FileReader();
            reader.onload = function () {
                $('.action-opacity').fadeIn();
                $('.action-loading').fadeIn('slow');
                $.post(ajaxurl, {
                    action: 'shoppablelookbook_import',
                    _ajax_nonce: litLookbook.nonce,
                    payload: reader.result
                }, function (res) {
                    $('.action-loading').fadeOut('fast');
                    $('.action-opacity').fadeOut('slow');
                    if ( res && res.success ) {
                        window.location.reload();
                    } else {
                        window.alert((res && res.data && res.data.message) || lt('importFailed', 'Could not import the lookbook.'));
                    }
                });
            };
            reader.readAsText(file);
        });

        //Switch between the Product / Custom Link tabs inside a marker box
        $('#markers').on('click', '.lit-tab', function(e){
            e.preventDefault();
            var $box = $(this).closest('.lit-box'),
                tab  = $(this).data('tab');
            $box.find('.lit-tab').removeClass('active');
            $(this).addClass('active');
            $box.find('.lit-pane').hide();
            $box.find('.lit-pane-' + tab).show();
        });

        //Pick a custom image from the media library
        $('#markers').on('click', '.lit-custom-image-btn', function(e){
            e.preventDefault();
            var $row  = $(this).closest('.lit-custom-image-row'),
                frame = wp.media({ title: lt('selectImage','Select Image'), multiple: false, library: { type: 'image' } });

            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $row.find('.lit-custom-image').val(att.url);
                $row.find('.lit-custom-image-name').text(att.filename || att.url);
            });

            frame.open();
        });

        //Apply a custom link to the marker
        $('#markers').on('click', '.lit-custom-apply', function(e){
            e.preventDefault();
            var $marker = $(this).closest('.lit-marker'),
                url     = $.trim($marker.find('.lit-custom-url').val()),
                title   = $.trim($marker.find('.lit-custom-title').val()),
                image   = $marker.find('.lit-custom-image').val(),
                price   = $.trim($marker.find('.lit-custom-price').val()),
                target  = $marker.find('.lit-custom-target').is(':checked') ? '_blank' : '';

            if ( !url ) {
                window.alert( lt('urlRequired','Please enter a link URL.') );
                return;
            }

            $marker.find('input[name=data-type]').val('custom');
            $marker.find('input[name=data-url]').val(url);
            $marker.find('input[name=data-title]').val(title);
            $marker.find('input[name=data-image]').val(image);
            $marker.find('input[name=data-price]').val(price);
            $marker.find('input[name=data-target]').val(target);
            $marker.find('input[name=data-product]').val('');

            var preview = ( image ? '<img src="' + image + '" alt="">' : '' ) + ( title || url );
            $marker.find('.lit-product').html(preview);

            $marker.removeClass('inactive').addClass('active');
            $marker.find('.lit-box').removeClass('active');
            $marker.find('.lit-selected').fadeIn(300);
            litRefreshMarkerList();
        });

        //Color Picker
        var myOptions = {
            defaultColor: false,
            change: function(event, ui){
                //console.log(ui.color.toString());
                $( event.target ).val( ui.color.toString() );
                $( event.target ).trigger('change');
                $('#hide-marker-color').val(ui.color.toString());
                $('#markers').css('--lb-marker-color', ui.color.toString());
                litRefreshMarkers();
                applyMarkerSize();
            },
            clear: function() {},
            hide: true,
            palettes: ['#000000', '#ffffff','#ffb818', '#e74c3c','#2ecc71', '#3498db', '#9b59b6']
        };
        $('.marker-color').wpColorPicker(myOptions);

        //Custom box color picker
        $('.box-custom-color').wpColorPicker({
            change: function(event, ui){
                jQuery(event.target).val(ui.color.toString());
                applyOpacityPreview();
            },
            clear: function(){ applyOpacityPreview(); }
        });

        //Is a hex colour light? (to pick black/white text on top)
        function isLightColor(hex) {
            hex = (hex || '').replace('#', '');
            if ( hex.length === 3 ) { hex = hex.replace(/(.)/g, '$1$1'); }
            if ( hex.length !== 6 ) { return true; }
            var r = parseInt(hex.substr(0,2),16),
                g = parseInt(hex.substr(2,2),16),
                b = parseInt(hex.substr(4,2),16);
            return (0.299*r + 0.587*g + 0.114*b) / 255 > 0.6;
        }

        //Current selected box colour as a hex value
        function getBoxColor() {
            var sel = $("input[name='lookbook_box_color']:checked").val() || 'light';
            if ( sel === 'custom' ) {
                return $("input[name='lookbook_box_custom_color']").val() || '#ffb818';
            }
            var map = { light:'#ffffff', dark:'#222222', yellow:'#ffb818', blue:'#2563eb', green:'#16a34a', rose:'#e84393' };
            return map[sel] || '#ffb818';
        }

        //Paint the filled portion + thumb of a range slider in a given colour
        function fillRange(el, color) {
            var $el = $(el),
                min = parseFloat($el.attr('min')) || 0,
                max = parseFloat($el.attr('max')) || 100,
                val = parseFloat($el.val());
            if ( isNaN(val) ) { val = min; }
            color = color || '#ffb818';
            var pct = ((val - min) / (max - min)) * 100;
            $el.css('--lb-accent', color);
            $el.css('background', 'linear-gradient(to right, ' + color + ' 0%, ' + color + ' ' + pct + '%, rgba(255,255,255,.18) ' + pct + '%, rgba(255,255,255,.18) 100%)');
        }

        function paintBadge($badge, color) {
            $badge.css({ 'background-color': color, 'color': isLightColor(color) ? '#1d1f23' : '#fff' });
        }

        //Background opacity slider — colour follows the box colour
        function applyOpacityPreview() {
            var $r = $('.opacity-range'),
                v  = parseInt($r.val(), 10);
            if ( isNaN(v) ) { v = 100; }
            var color = getBoxColor();
            $('.opacity-value').text(v);
            paintBadge($('.opacity-value'), color);
            fillRange($r, color);
            // Live preview: fade only the chip background (via CSS var on ::before).
            $('.lit-selected').css('--lit-bg-opacity', v / 100);
        }
        $('.opacity-range').on('input change', applyOpacityPreview);
        applyOpacityPreview();

        //Marker size slider — colour follows the marker colour
        function applyMarkerSize() {
            var $r = $('.markersize-range'),
                color = $('#hide-marker-color').val() || '#ffb818',
                size = parseInt($r.val(), 10) || 30;
            $('.markersize-value').text(size);
            paintBadge($('.markersize-value'), color);
            fillRange($r, color);
            // Drive marker size via the CSS variable (so mobile can cap it).
            $('#markers').css('--lb-marker-size', size + 'px');
            // Push the info chip out proportionally so a bigger marker never
            // overlaps it (see litSelectedGap()).
            litRepositionChips();
        }
        $('.markersize-range').on('input change', function(){
            applyMarkerSize();
            litRefreshMarkers();
        });
        applyMarkerSize();

        //Box corner slider — rounds the box + thumbnail (live preview via CSS var)
        function applyBoxRadius() {
            var $r = $('.boxradius-range'),
                radius = parseInt($r.val(), 10) || 0;
            $('.boxradius-value').text(radius);
            $('#markers').css('--lb-radius', radius + 'px');
            fillRange($r, '#fff');
        }
        $('.boxradius-range').on('input change', applyBoxRadius);
        applyBoxRadius();

        //Photo max-width slider — label only (frontend container width, not the fixed edit canvas)
        function applyMaxWidth() {
            var $r = $('.maxwidth-range'),
                width = parseInt($r.val(), 10) || 0;
            $('.maxwidth-value').text( width ? width : 'Full width' );
            paintBadge($('.maxwidth-value'), '#ffb818');
            fillRange($r, '#fff');
        }
        $('.maxwidth-range').on('input change', applyMaxWidth);
        applyMaxWidth();

        //Upload a custom marker image (PNG / SVG)
        $('.marker-image-upload').on('click', function(e){
            e.preventDefault();
            var frame = wp.media({
                title: (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n.selectImage : 'Select Image',
                multiple: false,
                library: { type: ['image'] }
            });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $('.marker-image-id').val(att.id);
                $('.marker-image-url').val(att.url);
                $('.marker-image-preview').html('<img src="' + att.url + '" alt="">');
                $('.marker-image-remove').show();
                litRefreshMarkers();
            });
            frame.open();
        });

        //Collapsible settings sections — accordion with slide animation
        var $settings = $('.lookbook-settings');

        // On load: ensure first section is open; show any pre-opened section bodies instantly.
        (function () {
            var $sections = $settings.find('.lb-section');
            if (!$sections.filter('.open').length) {
                $sections.first().addClass('open');
            }
            $sections.filter('.open').find('.lb-section-body').show();
        })();

        $settings.on('click', '.lb-section-head', function(){
            var $section = $(this).closest('.lb-section');
            var isOpen   = $section.hasClass('open');
            $section.siblings('.lb-section').removeClass('open').find('.lb-section-body').slideUp(200);
            if (isOpen) {
                $section.removeClass('open').find('.lb-section-body').slideUp(200);
            } else {
                $section.addClass('open').find('.lb-section-body').slideDown(200);
            }
        });

        //Remove the custom marker image (revert to icon)
        $('.marker-image-remove').on('click', function(e){
            e.preventDefault();
            $('.marker-image-id').val('0');
            $('.marker-image-url').val('');
            $('.marker-image-preview').empty();
            $(this).hide();
            litRefreshMarkers();
        });

        //Click on photo
        $('#lit-photo').on('click', function(e){
            createMarker(e);
        });

        //Upload new image
        $('#lit-upload').click(function(e) {
            var lit_frame;
            if ( lit_frame ){
                lit_frame.open();
            }

            // Define lit_frame as wp.media object
            lit_frame = wp.media({
                title: (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n.selectImage : 'Select Image',
                multiple : false,
                library : {
                    type : 'image',
                }
            });

            lit_frame.on('close',function() {
                // On close, get selections and save to the hidden input
                // plus other AJAX stuff to refresh the image preview
                var selection =  lit_frame.state().get('selection'),
                    gallery_ids = new Array(),
                    my_index = 0;

                selection.each(function(attachment) {
                    gallery_ids[my_index] = attachment['id'];
                    my_index++;
                });

                var ids = gallery_ids.join(",");
                $('#lit-temp').val(ids);

                // Call function
                refresh_image(ids);
            });

            lit_frame.on('open',function() {
                // On open, get the id from the hidden input
                // and select the appropiate images in the media manager
                var selection =  lit_frame.state().get('selection'),
                    ids = $('#lit-temp').val().split(',');
                ids.forEach(function(id) {
                    var attachment = wp.media.attachment(id);
                    attachment.fetch();
                    selection.add( attachment ? [ attachment ] : [] );
                });

            });

            lit_frame.open();

            e.preventDefault();
        });

        // Drag & drop an image file onto the frame: upload it through WP's
        // async-upload endpoint, then run the same select flow the media
        // modal uses (refresh_image for a new lookbook, change_image when one
        // already exists). The nonce comes from the plupload settings that
        // wp_enqueue_media() prints for the media modal.
        var $litFrame = $('#lit-frame'),
            litDragDepth = 0;
        if ( $litFrame.length ) {
            $litFrame.on('dragenter', function(e){
                e.preventDefault();
                litDragDepth++;
                $litFrame.addClass('lit-dragover');
            });
            $litFrame.on('dragover', function(e){
                e.preventDefault();
            });
            $litFrame.on('dragleave', function(e){
                e.preventDefault();
                litDragDepth = Math.max(0, litDragDepth - 1);
                if ( ! litDragDepth ) { $litFrame.removeClass('lit-dragover'); }
            });
            $litFrame.on('drop', function(e){
                e.preventDefault();
                litDragDepth = 0;
                $litFrame.removeClass('lit-dragover');

                var dt   = e.originalEvent.dataTransfer,
                    file = dt && dt.files && dt.files[0];
                if ( ! file || ! /^image\//.test(file.type) ) { return; }

                var settings = window._wpPluploadSettings,
                    nonce = settings && settings.defaults && settings.defaults.multipart_params
                        ? settings.defaults.multipart_params._wpnonce : '';
                if ( ! nonce ) { return; }

                $('.action-opacity').fadeIn();
                $('.action-loading').fadeIn('slow');

                var fd = new FormData();
                fd.append('name', file.name);
                fd.append('action', 'upload-attachment');
                fd.append('_wpnonce', nonce);
                fd.append('async-upload', file);

                $.ajax({
                    url: (window.litLookbook || {}).uploadUrl || ajaxurl.replace('admin-ajax.php', 'async-upload.php'),
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    // async-upload.php answers JSON but with a text/plain
                    // content type — force parsing or .success is never seen.
                    dataType: 'json'
                }).done(function(res){
                    $('.action-loading').fadeOut('fast');
                    $('.action-opacity').fadeOut('slow');
                    if ( ! res || ! res.success || ! res.data || ! res.data.id ) { return; }
                    var attId = res.data.id,
                        litId = $('#lit-id').val();
                    $('#lit-temp').val(attId);
                    if ( litId ) {
                        change_image(attId, litId);
                    } else {
                        refresh_image(attId);
                    }
                }).fail(function(){
                    $('.action-loading').fadeOut('fast');
                    $('.action-opacity').fadeOut('slow');
                });
            });
        }

        //Change Image
        $('.lookbook-change').click(function(e) {
            var lit_frame;
            if ( lit_frame ){
                lit_frame.open();
            }

            // Define lit_frame as wp.media object
            lit_frame = wp.media({
                title: (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n.selectImage : 'Select Image',
                multiple : false,
                library : {
                    type : 'image',
                }
            });

            lit_frame.on('close',function() {
                // On close, get selections and save to the hidden input
                // plus other AJAX stuff to refresh the image preview
                var selection =  lit_frame.state().get('selection'),
                    gallery_ids = new Array(),
                    my_index = 0;

                selection.each(function(attachment) {
                    gallery_ids[my_index] = attachment['id'];
                    my_index++;
                });

                var id = $('#lit-id').val(),
                    ids = gallery_ids.join(",");
                $('#lit-temp').val(ids);

                // Call function
                change_image(ids, id);
            });

            lit_frame.on('open',function() {
                // On open, get the id from the hidden input
                // and select the appropiate images in the media manager
                var selection =  lit_frame.state().get('selection'),
                    ids = $('#lit-temp').val().split(',');
                ids.forEach(function(id) {
                    var attachment = wp.media.attachment(id);
                    attachment.fetch();
                    selection.add( attachment ? [ attachment ] : [] );
                });

            });

            lit_frame.open();

            e.preventDefault();
        });

        // Update lookbook — redirect back to Lookbook editor if opened from one.
        $('#lookbook-submit').click(function() {
            var returnUrl = (window.litLookbook || {}).returnLookbookUrl;
            uploadLookbook(returnUrl ? function () {
                window.location.href = returnUrl;
            } : undefined);
        });

        // Preview lookbook
        $('#lookbook-preview').click(function() {
            previewLookbook();
        });
        
        //Sticky sidebar 
    //    $(window).scroll(function() {
    //        var windowTop = $(window).scrollTop();
    //        if ( windowTop > ($('#lit-frame').offset().top - 32) ) {
    //            $('#lit-sidebar').addClass('sticky');
    //        } else {
    //            $('#lit-sidebar').removeClass('sticky');
    //        }
    //    });
    });
    
})(jQuery);

// Rebuild the sidebar "Hotspot List" panel from the markers currently on the image
function litRefreshMarkerList() {
    var $list = jQuery('#lit-marker-list');
    if ( ! $list.length ) { return; }

    var i18n = (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n : {};
    var items = '';

    jQuery('#markers .lit-marker').each(function (idx) {
        var $m = jQuery(this),
            label = jQuery.trim($m.find('.lit-product').text()) || (i18n.unlinkedMarker || '(no link yet)'),
            thumb = $m.find('.lit-product img').attr('src');

        items += '<li class="lit-marker-item" data-marker="' + $m.attr('id') + '">' +
            '<span class="lit-marker-item-num">' + (idx + 1) + '</span>' +
            (thumb ? '<img class="lit-marker-item-thumb" src="' + thumb + '" alt="">' : '<span class="lit-marker-item-noimg material-icons-round">image</span>') +
            '<span class="lit-marker-item-name">' + jQuery('<span>').text(label).html() + '</span>' +
            '<i class="lit-marker-item-remove material-icons-round">delete</i>' +
            '</li>';
    });

    if ( ! items ) {
        items = '<li class="lit-marker-list-empty">' + (i18n.noMarkers || 'No markers yet. Click on the image to add one.') + '</li>';
    }

    $list.html(items);
}

// Build the marker "grab" element HTML (custom image or icon) from current settings
function litMarkerGrabHtml() {
    var imgUrl = jQuery('.marker-image-url').val();

    if ( imgUrl ) {
        // Size comes from the --lb-marker-size CSS variable on #markers.
        return '<img class="lit-grab lit-grab-img" src="' + imgUrl + '" alt="">';
    }

    var icon  = jQuery("input[name='lookbook_marker']:checked").parent().find('i').html() || 'add_circle',
        color = jQuery('#hide-marker-color').val() || '#000';
    return '<i class="lit-grab material-icons-round" style="color:' + color + '">' + icon + '</i>';
}

// Re-render every marker's grab element to reflect current image/icon/size/color
function litRefreshMarkers() {
    var html = litMarkerGrabHtml();
    jQuery('.lit-marker').each(function () {
        jQuery(this).find('.lit-grab').first().replaceWith(html);
    });
}

// How far the info chip (.lit-selected) sits from the marker's own edge —
// grows with the marker size so a large icon never overlaps it. 20px is the
// original fixed clearance at the default 30px marker size, kept constant
// as size changes instead of shrinking (and eventually overlapping) it.
function litSelectedGap() {
    var size = parseInt(jQuery('.markersize-range').val(), 10) || 30;
    return Math.round(size / 2) + 20;
}

// Re-apply the chip's top offset to every existing marker with the current
// gap, preserving whichever side (above/below the marker) it was already on.
function litRepositionChips() {
    var gap = litSelectedGap();
    jQuery('.lit-marker .lit-selected').each(function () {
        var current = parseFloat(jQuery(this).css('top'));
        jQuery(this).css('top', (!isNaN(current) && current < 0) ? -gap : gap);
    });
}

// Create marker when click on photo
function createMarker(e) {
        var photo = jQuery('#lit-photo'),
            id = jQuery('#markers').data('id'),
            positionX = e.offsetX ? (e.offsetX) : e.pageX - photo.offsetLeft,
            positionY = e.offsetY ? (e.offsetY) : e.pageY- photo.offsetTop,
            newpositionX = positionX - 13,
            newpositionY = positionY - 13,
            percentX = ( newpositionX / photo.width() ) * 100,
            percentY = ( newpositionY / photo.height() ) * 100,
            marker = jQuery("input[name='lookbook_marker']:checked").parent().find('i').html(),
            color = jQuery('#hide-marker-color').val();
        
        var i18n = (window.litLookbook && litLookbook.i18n) ? litLookbook.i18n : {};
        function t(key, fallback) { return (i18n && i18n[key]) ? i18n[key] : fallback; }

        //Create a marker
        var boxColorNow = jQuery("input[name='lookbook_box_color']:checked").val() || 'light',
            contentNow  = jQuery("input[name='lookbook_content_color']:checked").val() || 'auto',
            chipClasses = boxColorNow + (contentNow !== 'auto' ? ' lb-content-' + contentNow : '');
        var markerHtml = '<div id="litmarker'+id+'" class="lit-marker draggable inactive">' +
            litMarkerGrabHtml() +
            '<div class="lit-selected '+chipClasses+'"><span class="lit-product"></span><i class="lit-cancel material-icons">clear</i></div>' +
            '<div class="lit-box">' +
                '<div class="lit-header"><h5>'+t('addLink','Add a link')+'</h5></div>' +
                '<i class="lit-close material-icons">clear</i><i class="lit-less material-icons">undo</i>' +
                '<div class="lit-tabs"><button type="button" class="lit-tab active" data-tab="product">'+t('productTab','Product')+'</button><button type="button" class="lit-tab" data-tab="custom">'+t('customTab','Custom Link')+'</button></div>' +
                '<div class="lit-form lit-pane lit-pane-product"><input id="litproduct'+id+'" type="text" value="" autocomplete="off" placeholder="'+t('searchProduct','Search a product...')+'" onkeyup="litfetch('+id+')" /><div class="lit-result"></div></div>' +
                '<div class="lit-pane lit-pane-custom" style="display:none">' +
                    '<input class="lit-custom-url" type="url" value="" placeholder="'+t('urlPlaceholder','Link URL (https://...)')+'">' +
                    '<input class="lit-custom-title" type="text" value="" placeholder="'+t('titlePlaceholder','Title')+'">' +
                    '<input class="lit-custom-price" type="text" value="" placeholder="'+t('pricePlaceholder','Price (optional)')+'">' +
                    '<div class="lit-custom-image-row"><button type="button" class="lit-custom-image-btn button">'+t('selectImageBtn','Select image')+'</button><input class="lit-custom-image" type="hidden" value=""><span class="lit-custom-image-name"></span></div>' +
                    '<label class="lit-custom-target-label"><input type="checkbox" class="lit-custom-target"> '+t('openNewTab','Open in new tab')+'</label>' +
                    '<button type="button" class="lit-custom-apply button button-primary">'+t('applyLink','Apply link')+'</button>' +
                '</div>' +
            '</div>' +
            '<input type="hidden" name="data-x" value="" />' +
            '<input type="hidden" name="data-y" value="" />' +
            '<input type="hidden" name="data-product" value="" />' +
            '<input type="hidden" name="data-type" value="product" />' +
            '<input type="hidden" name="data-url" value="" />' +
            '<input type="hidden" name="data-title" value="" />' +
            '<input type="hidden" name="data-image" value="" />' +
            '<input type="hidden" name="data-price" value="" />' +
            '<input type="hidden" name="data-target" value="" />' +
        '</div>';
        jQuery('#markers').append(markerHtml);
        //Add meta data to marker
        jQuery('#litmarker'+id).css({'left': percentX+'%', 'top': percentY+'%'});
        jQuery('#litmarker'+id).find('input[name=data-x]').val(percentX);
        jQuery('#litmarker'+id).find('input[name=data-y]').val(percentY);
        //Set left/right position
        if ( positionX > photo.width()/2 ) { 
            jQuery('#litmarker'+id+' .lit-box').css('right', -10);
            jQuery('#litmarker'+id+' .lit-selected').css('right', 0);
        } else {
            jQuery('#litmarker'+id+' .lit-box').css('left', -10);
            jQuery('#litmarker'+id+' .lit-selected').css('left', 0);
        }
        //Set top/bototm position
        if ( positionY > photo.height() - 150 ) {
            jQuery('#litmarker'+id+' .lit-box').css('top', -95);
            jQuery('#litmarker'+id+' .lit-selected').css('top', -litSelectedGap());
        } else {
            jQuery('#litmarker'+id+' .lit-box').css('top', 35);
            jQuery('#litmarker'+id+' .lit-selected').css('top', litSelectedGap());
        }
        //Open search form
        jQuery('#litmarker'+id+' .lit-box').delay(150).queue(function(){
            jQuery(this).addClass("active").dequeue();
        });
        //Remove all inactive markers
        jQuery('.lit-marker.inactive').not('#litmarker'+id).remove();
        //Increase marker index
        jQuery('#markers').data('id', id+1);
        
        //Open search form again
        openSearch();
        //Drag markers
        draggableMarker();
        //Close marker
        closeMarker();
        //Sidebar hotspot list
        litRefreshMarkerList();

        //e.preventDefault();
}

// Drag markers function
function draggableMarker() {
    
    var photo = jQuery('#lit-photo');
    
    //Drag the marker
    jQuery('.draggable').draggable({
        containment: "#lit-frame",
        drag: function() {
            //Set left/right position
            var photoLeft = photo.offset().left,
                photoTop = photo.offset().top,
                halfLeft = jQuery(this).offset().left - photoLeft,
                halfTop = jQuery(this).offset().top - photoTop;
            if ( halfLeft > photo.width()/2 ) {
                jQuery(this).find('.lit-box').css({'right':-10, 'left':'auto'});
                jQuery(this).find('.lit-selected').css({'right':0, 'left':'auto'});
            } else {
                jQuery(this).find('.lit-box').css({'left':-10, 'right':'auto'});
                jQuery(this).find('.lit-selected').css({'left':0, 'right':'auto'});
            }
            //Set top/bototm position
            if ( halfTop > photo.height() - 150 ) {
                jQuery(this).find('.lit-box').css('top', -95);
                jQuery(this).find('.lit-selected').css('top', -litSelectedGap());
            } else {
                jQuery(this).find('.lit-box').css('top', 35);
                jQuery(this).find('.lit-selected').css('top', litSelectedGap());
            }
        },
        stop: function() {
            var dragX = jQuery(this).offset().left - photo.offset().left,
                dragY = jQuery(this).offset().top - photo.offset().top,
                percentX = ( dragX / photo.width() ) * 100,
                percentY = ( dragY / photo.height() ) * 100;

            jQuery(this).css({'left': percentX+'%', 'top': percentY+'%'});
            jQuery(this).find('input[name=data-x]').val(percentX);
            jQuery(this).find('input[name=data-y]').val(percentY);
        }
    });
}

// Open search
function openSearch() {
    jQuery('.lit-marker .lit-selected').not('.lit-cancel').on('click', function(e){
        jQuery(this).parent().find('.lit-selected').fadeOut(300);
        jQuery(this).parent().find('.lit-box').addClass('active');
        jQuery(this).parent().find('.lit-close').fadeOut('fast');
        jQuery(this).parent().find('.lit-less').addClass('active');
        
        jQuery('.lit-marker .lit-less').on('click', function(e) {
            jQuery(this).parent().parent().find('.lit-less').removeClass('active');
            jQuery(this).parent().parent().find('.lit-box').removeClass('active');
            jQuery(this).parent().parent().find('.lit-selected').fadeIn( 300 );
            e.preventDefault();
        });
        
        e.preventDefault();
    });
}

// Close marker
function closeMarker() {

    jQuery('.lit-marker .lit-close').on('click', function(e) {
        jQuery(this).parent().find('.lit-box').removeClass("active");
        jQuery(this).parent().fadeOut( 200 ).delay(100).queue(function(){
            jQuery(this).parent().remove().dequeue();
            litRefreshMarkerList();
        });
        e.preventDefault();
    });

    jQuery('.lit-marker .lit-cancel').on('click', function(e) {
        jQuery(this).parent().find('.lit-box').removeClass("active");
        jQuery(this).parent().fadeOut( 100 ).delay(20).queue(function(){
            jQuery(this).parent().remove().dequeue();
            litRefreshMarkerList();
        });
        e.preventDefault();
    });
}

// Get product results from WP
function litfetch(id) {
    // Remove loading
    jQuery('#litmarker'+id+' .lit-form').addClass('lit-loading');
    
    var data = {
        action: 'shoppablelookbook_search',
        _ajax_nonce: litLookbook.nonce,
        keyword: jQuery('#litproduct'+id).val()
    };
    // Ajax
    jQuery.post(ajaxurl, data, function(response) {
        if ( response.success === true && response.data.html ) {
            
            
            //Display result
            jQuery('#litmarker'+id+' .lit-result').html(response.data.html);
            //Remove loading
            jQuery('#litmarker'+id+' .lit-form').removeClass('lit-loading');
            //Click on a result
            jQuery('#litmarker'+id+' .lit-result a').on('click', function(e){
                jQuery('#litmarker'+id).removeClass('inactive').addClass('active');
                jQuery('#litmarker'+id).find('input[name=data-type]').val('product');
                jQuery('#litmarker'+id).find('input[name=data-product]').val(jQuery(this).data('product'));
                jQuery('#litmarker'+id).find('input[name=data-url]').val('');
                jQuery('#litmarker'+id+' .lit-product').html( jQuery(this).html() );
                jQuery('#litmarker'+id+' .lit-box').removeClass('active');
                jQuery('#litmarker'+id+' .lit-selected').fadeIn( 300 );
                litRefreshMarkerList();
                e.preventDefault();
            });
        }
    });
}

// Ajax request to refresh the image preview
function refresh_image(ids) {
    
    // Show loading
    jQuery('.action-opacity').fadeIn();
    jQuery('.action-loading').fadeIn('slow');
    
    var data = {
        action: 'shoppablelookbook_get_image',
        _ajax_nonce: litLookbook.nonce,
        media_id: ids
    };

    // Ajax
    jQuery.get(ajaxurl, data, function(response) {
        if ( response.success === true && response.data.image ) {
            jQuery('#lit-photo').attr('src',response.data.image).fadeIn('slow');
            jQuery('#upload-buttons').fadeOut('fast');
            jQuery('#lit-frame').removeClass('lookbook-upload');
            jQuery('.lookbook-change').removeClass('hidden');
            jQuery('#lit-sidebar').removeClass('disabled');
            
            var dbdata = {
                action: 'shoppablelookbook_insert_db',
                _ajax_nonce: litLookbook.nonce,
                media_id: ids
            };
            
            // Ajax
            jQuery.get(ajaxurl, dbdata, function(res) {
                if ( res.success === true && res.data.id ) {
                    
                    // Hide Loading
                    jQuery('.action-loading').fadeOut('fast');
                    jQuery('.action-opacity').fadeOut('slow');
                    
                    jQuery('#lit-id').val(res.data.id);
                    jQuery('#lookbook-name').val('Lookbook ' + res.data.id);
                    jQuery('#lookbook-shortcode').val('[shoppablelookbook id='+res.data.id+']');
                    
                    var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=shoppable-lookbook&action=edit&id='+res.data.id;
                    window.history.pushState({path:newurl},'',newurl);
                } 
                else {
                    // Hide Loading
                    jQuery('.action-loading').fadeOut('fast');
                    jQuery('.action-opacity').fadeOut('slow');
                }
            });
        } 
        else {
            // Hide Loading
            jQuery('.action-loading').fadeOut('fast');
            jQuery('.action-opacity').fadeOut('slow');
        }
    });
}

// Ajax request to refresh the image preview
function change_image(ids, id) {
    
    // Show loading
    jQuery('.action-opacity').fadeIn();
    jQuery('.action-loading').fadeIn('slow');
    
    var data = {
        action: 'shoppablelookbook_get_image',
        _ajax_nonce: litLookbook.nonce,
        media_id: ids
    };

    // Ajax
    jQuery.get(ajaxurl, data, function(response) {
        if ( response.success === true && response.data.image ) {
            jQuery('#lit-photo').attr('src',response.data.image).fadeIn('slow');
            
            var dbdata = {
                action: 'shoppablelookbook_update_media_db',
                _ajax_nonce: litLookbook.nonce,
                media_id: ids,
                id: id
            };
            
            // Ajax
            jQuery.get(ajaxurl, dbdata, function(res) {
                if ( res.success === true ) {
                    
                    // Hide Loading
                    jQuery('.action-loading').fadeOut('fast');
                    jQuery('.action-opacity').fadeOut('slow');
                }
            });
        } else {
            // Hide Loading
            jQuery('.action-loading').fadeOut('fast');
            jQuery('.action-opacity').fadeOut('slow');
        }
    });
}


// Only the positional inline styles may be persisted — fadeIn/fadeOut leave
// transient display/opacity values behind, and saving those would permanently
// hide the marker chip on the next edit.
function litPositionStyle($el) {
    var el = $el[0], out = '';
    if ( ! el ) { return out; }
    ['top', 'left', 'right', 'bottom'].forEach(function (prop) {
        var v = el.style[prop];
        if ( v ) { out += prop + ': ' + v + '; '; }
    });
    return out.replace(/\s+$/, '');
}

// Ajax request to update the lookbook
function uploadLookbook(onDone) {
    // Show loading
    jQuery('.action-opacity').fadeIn();
    jQuery('.action-saving').fadeIn('slow');

    var lit_options = { name: jQuery("#lookbook-name").val(),
                       layout: parseInt(jQuery("input[name='lookbook_layout']:checked").val()),
                       boxcolor: jQuery("input[name='lookbook_box_color']:checked").val(),
                       boxcustomcolor: jQuery("input[name='lookbook_box_custom_color']").val(),
                       contentcolor: jQuery("input[name='lookbook_content_color']:checked").val() || 'auto',
                       opacity: parseInt(jQuery("input[name='lookbook_opacity']").val()),
                       marker: parseInt(jQuery("input[name='lookbook_marker']:checked").val()),
                       markerimage: parseInt(jQuery("input[name='lookbook_marker_image']").val()) || 0,
                       markersize: parseInt(jQuery("input[name='lookbook_marker_size']").val()) || 30,
                       boxradius: parseInt(jQuery("input[name='lookbook_box_radius']").val(), 10) || 0,
                       maxwidth: parseInt(jQuery("input[name='lookbook_max_width']").val(), 10) || 0,
                       animation: jQuery("input[name='lookbook_animation']:checked").val() || 'pulse',
                       trigger: jQuery("input[name='lookbook_trigger']:checked").val() || 'click',
                       mobilebox: jQuery("input[name='lookbook_mobilebox']:checked").val() || 'sheet',
                       color: jQuery("input[name='lookbook_marker_color']").val(),
                       price: parseInt(jQuery("input[name='lookbook_price']:checked").val()),
                       cart: parseInt(jQuery("input[name='lookbook_cart']:checked").val()),
                       sidebar: parseInt(jQuery("input[name='lookbook_sidebar']:checked").val()) },
        lit_products = [];

    // Extension point: add-ons (Pro) push collectors that return extra option fields.
    if ( window.shoppableLookbookOptionCollectors && window.shoppableLookbookOptionCollectors.length ) {
        window.shoppableLookbookOptionCollectors.forEach(function(fn){
            try { jQuery.extend(lit_options, fn() || {}); } catch (e) {}
        });
    }

    jQuery( ".lit-marker" ).each(function() {
        var $m       = jQuery(this),
            type     = $m.find('input[name=data-type]').val() || 'product',
            productId = $m.find('input[name=data-product]').val(),
            url      = $m.find('input[name=data-url]').val();

        // Skip markers with no link target.
        if ( type === 'custom' ? !url : !productId ) {
            return;
        }

        var myMarker = {
            type: type,
            left: $m.find('input[name=data-x]').val(),
            top: $m.find('input[name=data-y]').val(),
            lit_selected: litPositionStyle($m.find('.lit-selected')),
            lit_box: litPositionStyle($m.find('.lit-box'))
        };

        if ( type === 'custom' ) {
            myMarker.url    = url;
            myMarker.title  = $m.find('input[name=data-title]').val();
            myMarker.image  = $m.find('input[name=data-image]').val();
            myMarker.price  = $m.find('input[name=data-price]').val();
            myMarker.target = ( $m.find('input[name=data-target]').val() === '_blank' ) ? 1 : 0;
        } else {
            myMarker.product_id = parseInt(productId);
        }

        lit_products.push(myMarker);
    });

    var data = {
        action: 'shoppablelookbook_update_lookbook_db',
        _ajax_nonce: litLookbook.nonce,
        lit_options: lit_options,
        lit_products: lit_products,
        id: jQuery("#lit-id").val(),
        // When this editor was opened from a Lookbook (gallery) via its
        // "Add New Image Hotspot" button, the Pro gallery module attaches
        // the saved hotspot to that lookbook server-side.
        return_lookbook: (window.litLookbook || {}).returnLookbookId || 0
    };

    // Ajax
    jQuery.post(ajaxurl, data, function(response) {
        if ( response.success === true ) {

            jQuery('.action-saving').css('display', 'none');
            jQuery('.action-saved').fadeIn('slow').delay(600).queue(function(){
                // Hide saved
                jQuery(this).fadeOut('fast').dequeue();
                jQuery('.action-opacity').fadeOut('slow').dequeue();
            });

            if ( typeof onDone === 'function' ) {
                onDone();
            }
        }
        else {
            // Hide loading
            jQuery(this).fadeOut('fast');
            jQuery('.action-opacity').fadeOut('slow');
        }
    });
}

// Save first, then open a live preview overlay of the lookbook
function previewLookbook() {
    var id = jQuery('#lit-id').val();
    if ( ! id ) { return; }

    uploadLookbook(function () {
        jQuery('.action-opacity').fadeIn();
        jQuery('.action-loading').fadeIn('slow');

        jQuery.get(ajaxurl, {
            action: 'shoppablelookbook_preview',
            _ajax_nonce: litLookbook.nonce,
            id: id
        }, function (res) {
            jQuery('.action-loading').fadeOut('fast');
            jQuery('.action-opacity').fadeOut('slow');

            if ( res.success === true && res.data.html ) {
                showPreviewOverlay(res.data.html);
            }
        });
    });
}

// Size the previewed lookbook so a portrait photo fits the screen: give the
// root the width at which the photo's height lands at ~74vh (or less when
// the modal or the photo's natural width is the tighter limit). The ROOT,
// not the img, must shrink — markers position against it. Direct child
// only: galleries and product-list wrappers size their photos themselves.
function litSizePreviewPhoto($overlay) {
    var $lb = $overlay.find('.lookbook-preview-body > .shoppable-lookbook');
    if ( ! $lb.length ) { return; }
    var img = $lb.find('.lookbook-photo')[0];
    if ( ! img || ! img.naturalWidth || ! img.naturalHeight ) { return; }
    var bodyW = $overlay.find('.lookbook-preview-body').width();
    var maxH  = window.innerHeight * 0.74;
    var w = Math.min(bodyW, img.naturalWidth, maxH * img.naturalWidth / img.naturalHeight);
    if ( w > 0 ) {
        $lb.css('width', Math.floor(w) + 'px');
    }
}

function showPreviewOverlay(html) {
    var $overlay = jQuery('#lookbook-preview-overlay');

    if ( ! $overlay.length ) {
        var title = (window.litLookbook && litLookbook.i18n && litLookbook.i18n.previewTitle) ? litLookbook.i18n.previewTitle : 'Preview';
        $overlay = jQuery('<div id="lookbook-preview-overlay"><div class="lookbook-preview-inner"><div class="lookbook-preview-head"><span class="lookbook-preview-title"><span class="dashicons dashicons-visibility"></span></span><button type="button" class="lookbook-preview-close" aria-label="Close">&times;</button></div><div class="lookbook-preview-body"></div></div></div>');
        $overlay.find('.lookbook-preview-title').append(document.createTextNode(title));
        jQuery('body').append($overlay);
        $overlay.on('click', function (e) { if ( e.target === this ) { closePreviewOverlay(); } });
        $overlay.find('.lookbook-preview-close').on('click', closePreviewOverlay);
        jQuery(document).on('keydown.lookbookpreview', function (e) {
            if ( e.key === 'Escape' || e.keyCode === 27 ) { closePreviewOverlay(); }
        });
    }

    $overlay.find('.lookbook-preview-body').html(html);
    // Position markers only once the overlay is visible — offsets measured
    // while it is still hidden are all zero.
    $overlay.css('display', 'flex').hide().fadeIn(150, reposition);

    function reposition() {
        // Fit the photo to the screen first, then position markers against
        // the final geometry.
        litSizePreviewPhoto($overlay);
        if ( window.shoppableLookbookInit ) { window.shoppableLookbookInit($overlay[0]); }
        if ( window.shoppableLookbookProductListInit ) { window.shoppableLookbookProductListInit($overlay[0]); }
    }

    $overlay.find('.lookbook-photo').each(function () {
        if ( ! this.complete ) { jQuery(this).on('load', reposition); }
    });
}

function closePreviewOverlay() {
    jQuery('#lookbook-preview-overlay').fadeOut(150);
}

// On delete lookbook
function ondeleteLookbook(id, name) {
    // Show loading
    jQuery('.action-opacity').fadeIn();
    jQuery('.action-opacity h5 strong').html(name);
    jQuery('.action-content').fadeIn('slow');
    jQuery('.action-opacity input[name=lookbook-id]').val(id);
    
    // Cancel delete
    jQuery('#action-cancel').on('click',function(){
        jQuery('.action-opacity, .action-content').fadeOut();
        jQuery('.action-opacity h5 strong').html('');
        jQuery('.action-opacity input[name=lookbook-id]').val('');
    });
    
    // Delete
    jQuery('#action-delete').on('click',function(){
        var data = {
            action: 'shoppablelookbook_delete_lookbook_db',
            _ajax_nonce: litLookbook.nonce,
            id: jQuery('.action-opacity input[name=lookbook-id]').val()
        };
        
        jQuery('.action-content').css('display', 'none');
        jQuery('.action-deleting').fadeIn('slow');

        // Ajax
        jQuery.post(ajaxurl, data, function(response) {
            if ( response.success === true ) {

                jQuery('.lookbook-body .lookbook-'+jQuery('.action-opacity input[name=lookbook-id]').val()).remove();
                jQuery('.action-deleting').css('display', 'none');
                jQuery('.action-deleted').fadeIn('slow').delay(600).queue(function(){
                    // Hide deleted
                    jQuery(this).fadeOut('fast').dequeue();
                    jQuery('.action-opacity').fadeOut('slow').dequeue();
                    jQuery('.action-opacity h5 strong').html('');
                    jQuery('.action-opacity input[name=lookbook-id]').val('');
                });
            } 
            else {
                // Hide deleting
                jQuery('.action-deleting').css('display', 'none');
                jQuery('.action-fail').fadeIn('slow').delay(600).queue(function(){
                    // Hide false
                    jQuery(this).fadeOut('fast').dequeue();
                    jQuery('.action-opacity').fadeOut('slow').dequeue();
                    jQuery('.action-opacity h5 strong').html('');
                    jQuery('.action-opacity input[name=lookbook-id]').val('');
                });
            }
        });
    });
}
//
//// Delete lookbook
//function deleteLookbook(id) {
//    var data = {
//        action: 'shoppablelookbook_delete_lookbook_db',
//        id: jQuery("#lit-id").val()
//    };
//
//    // Ajax
//    jQuery.post(ajaxurl, data, function(response) {
//        if ( response.success === true ) {
//
//            jQuery('.action-saving').css('display', 'none');
//            jQuery('.action-saved').fadeIn('slow').delay(600).queue(function(){
//                // Hide loading
//                jQuery(this).fadeOut('fast').dequeue();
//                jQuery('.action-opacity').fadeOut('slow').dequeue();
//            });
//        } 
//        else {
//            // Hide loading
//            jQuery(this).fadeOut('fast');
//            jQuery('.action-opacity').fadeOut('slow');
//        }
//    });
//}