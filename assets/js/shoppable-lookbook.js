(function ($) {
    "use strict";

    // Breakpoint below which markers open as a bottom-sheet drawer.
    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    // Per-lookbook "Mobile Display" setting: the full-screen bottom sheet
    // (default) or the "compact" style, which keeps the desktop box
    // behaviour (positioned next to its marker, sized down via CSS) instead
    // of portaling into a sheet. $lb is the ".shoppable-lookbook" root.
    function usesSheet($lb) {
        return isMobile() && ! $lb.hasClass('lookbook-mobile-compact');
    }

    // The mobile sheet behaves like a toast: no backdrop, no scroll lock —
    // the page stays usable while the sheet sits fixed at the bottom.

    // Keep a desktop box inside the viewport. Pages no longer clip their
    // boxes (flipbook leaves are overflow:visible), so a box opened near the
    // window edge — or on the outer half of a flipbook spread — can poke
    // off-screen. Measure it and shift it back with a translate; if it is
    // wider than the viewport, keeping the left edge visible wins.
    function clampBoxToViewport($box) {
        if ( ! $box.length ) { return; }
        var el = $box[0];

        // Suspend the box transition while measuring: it transitions "all"
        // with a 0.4s delay, so a cleared transform would otherwise keep
        // reporting the previous clamp's geometry and poison the numbers
        // (a re-position pass would then see the box as fine, drop the
        // shift, and let it slide back off-screen).
        el.style.transition = 'none';
        el.style.transform = 'none';

        var rect  = el.getBoundingClientRect(),
            pad   = 10,
            vw    = document.documentElement.clientWidth,
            shift = 0;
        if ( rect.width ) {
            if ( rect.right > vw - pad ) {
                shift = vw - pad - rect.right;
            }
            if ( rect.left + shift < pad ) {
                shift = pad - rect.left;
            }
        }
        el.style.transform = shift ? 'translateX(' + shift + 'px)' : '';

        // Restore the stylesheet transition on the next frame, after the
        // instant (un-animated) shift above has been committed.
        window.requestAnimationFrame(function () {
            el.style.transition = '';
        });
    }

    // Position every marker's box relative to the photo so it stays on-screen.
    function positionLookbooks(root) {
        $(root).find('.shoppable-lookbook').each(function () {
            var $lb   = $(this),
                photo = $lb.find('.lookbook-photo');
            if ( ! photo.length || ! photo.offset() ) {
                return;
            }

            // Never upscale a STANDALONE lookbook past its photo's natural
            // width — blown-up images go blurry. The ROOT is capped (not just
            // the img) so the %-positioned markers keep matching the photo;
            // the stylesheet's auto margins then centre it in the column.
            // Gallery slides / Product List wrappers size the photo themselves.
            // The admin's own "Photo Max Width" setting (data-max-width) is a
            // further, user-chosen ceiling on top of that natural-size cap.
            if ( photo[0].naturalWidth && ! $lb.closest('.lookbook-gallery, .lookbook-with-list').length ) {
                var cap = photo[0].naturalWidth,
                    userMax = parseInt($lb.attr('data-max-width'), 10);
                if ( userMax > 0 ) {
                    cap = Math.min(cap, userMax);
                }
                $lb.css('max-width', cap + 'px');
            }

            // In the default "Bottom sheet" style the box becomes a fixed
            // sheet (styled via CSS), so clear any desktop inline positioning
            // and skip the math. The "Compact" style keeps the desktop
            // positioning below, just scaled down by CSS.
            if ( usesSheet($lb) ) {
                $lb.find('.lookbook-box').css({ top: '', left: '', right: '', bottom: '', width: '', transform: '' });
                return;
            }
            var isBottomCard = $(this).hasClass('lookbook-layout-4'),
                photoLeft = photo.offset().left,
                photoTop = photo.offset().top,
                limitTop = $(this).data('limit-top'),
                limitLeft = $(this).data('limit-left'),
                marginTop = $(this).data('top'),
                marginBottom = $(this).data('bottom'),
                marginLeft = $(this).data('left'),
                marginRight = $(this).data('right');

            $(this).find('.lookbook-marker').each(function () {
                var halfLeft = $(this).offset().left - photoLeft,
                    halfTop = $(this).offset().top - photoTop,
                    $box = $(this).find('.lookbook-box');

                // Prefer the box's real size over the static per-layout limits:
                // the limits assume a full-width photo and overshoot badly on
                // small ones (gallery slides, flipbook pages), letting the box
                // open past the photo edge where containers clip it. The box is
                // hidden via visibility (not display), so it stays measurable.
                var boxH = $box.outerHeight() || limitTop,
                    boxW = $box.outerWidth() || limitLeft;

                // Layout 4 (Bottom card): ignore the marker position and pin
                // the box inside the photo, centred along its bottom edge.
                // The inline values are relative to the MARKER — the box's
                // containing block — so the marker offset is subtracted out.
                // No viewport clamp: the box never leaves the photo, and its
                // transform is the slide-up animation.
                if ( isBottomCard ) {
                    var cardMargin = 12,
                        cardW      = Math.min( photo.width() - cardMargin * 2, 480 ),
                        markerH    = $(this).outerHeight() || 0;
                    $box.removeClass('is-top is-bottom is-left is-right').css({
                        width:  cardW + 'px',
                        left:   ( ( photo.width() - cardW ) / 2 - halfLeft ) + 'px',
                        right:  'auto',
                        top:    'auto',
                        bottom: ( halfTop + markerH - photo.height() + cardMargin ) + 'px'
                    });
                    return;
                }

                // Reset any previously computed position classes.
                $box.removeClass('is-top is-bottom is-left is-right');

                //Top/Bottom position
                if ( halfTop + boxH + 10 > photo.height() ) {
                    $box.addClass('is-bottom').css({'bottom':'calc(6% + ' + marginBottom + 'px)', 'top':'auto'});
                } else {
                    $box.addClass('is-top').css({'top':'calc(6% - ' + marginTop + 'px)', 'bottom':'auto'});
                }

                //Right/Left position
                if ( halfLeft + boxW + 10 > photo.width() ) {
                    $box.addClass('is-right').css({'right':marginRight, 'left':'auto'});
                } else {
                    $box.addClass('is-left').css({'left':marginLeft, 'right':'auto'});
                }

                clampBoxToViewport($box);
            });
        });
    }

    // ── Mobile sheet portal ─────────────────────────────────────────────────
    // position:fixed breaks inside transformed ancestors (flipbook 3D pages,
    // gallery slides): the transform becomes the containing block and the
    // sheet lands inside the page instead of the viewport. So on mobile the
    // box is moved to a host under <body> while open. The host mirrors the
    // lookbook's classes so all sheet CSS still matches.

    // The sheet is fixed (viewport-relative), so the CSS default is a
    // full-bleed edge-to-edge bar. Whenever the theme's content column has
    // side padding (near-universal on mobile) that leaves it floating past
    // the actual photo's edges. Measure the photo's own rect — already in
    // viewport coordinates, exactly what a fixed element's left/width need —
    // and pin the sheet to match it instead.
    function positionSheet($lb, $box) {
        var photo = $lb.find('.lookbook-photo')[0];
        if ( ! photo ) { return; }
        var r = photo.getBoundingClientRect();
        if ( ! r.width ) { return; }
        $box.css({ left: r.left + 'px', width: r.width + 'px' });
    }

    function openSheet($marker) {
        var $box = $marker.find('.lookbook-box');
        if ( ! $box.length || $box.data('lbHosted') ) { return; }

        var $lb   = $marker.closest('.shoppable-lookbook');
        var $wrap = $marker.closest('.lookbook-markers');

        var $host  = $('<div></div>')
            .attr('class', 'lookbook-sheet-host ' + ($lb.attr('class') || 'shoppable-lookbook'))
            // Analytics resolves the lookbook from its root id; carry it along.
            .attr('data-lb-id', ($lb.attr('id') || '').replace('shoppable-lookbook-', ''))
            .appendTo('body');
        var $inner = $('<div></div>').attr('class', $wrap.attr('class') || 'lookbook-markers').appendTo($host);
        var $ghost = $('<div class="lookbook-marker"></div>').appendTo($inner);

        window.clearTimeout($box.data('lbCloseTimer'));
        $box.data('lbHosted', { marker: $marker, host: $host, lb: $lb });
        $ghost.append($box);
        positionSheet($lb, $box);

        // Let the parked state paint first so the slide-up transition runs.
        window.requestAnimationFrame(function () { $ghost.addClass('is-active'); });
    }

    function closeSheet($box, instant) {
        var info = $box.data('lbHosted');
        if ( ! info ) { return; }

        $box.parent().removeClass('is-active');
        $box.removeData('lbHosted');

        var restore = function () {
            info.marker.append($box);
            info.host.remove();
        };
        if ( instant ) {
            restore();
        } else {
            $box.data('lbCloseTimer', window.setTimeout(restore, 350));
        }
    }

    // Instantly restore any hosted sheet (used when resizing up to desktop).
    function collapseHostedSheets() {
        $('.lookbook-sheet-host .lookbook-box').each(function () {
            var $box = $(this), info = $box.data('lbHosted');
            if ( info && info.marker ) {
                info.marker.removeClass('is-active').find('.lookbook-grab').attr('aria-expanded', 'false');
            }
            closeSheet($box, true);
        });
        releaseSheet();
    }

    function openMarker(grab) {
        var $grab   = $(grab),
            $marker = $grab.parent(),
            $lb     = $marker.closest('.shoppable-lookbook');

        // "Open All" lookbooks allow many boxes open at once on desktop (and
        // on the "Compact" mobile style, which behaves like desktop); the
        // "Bottom sheet" style always keeps one open at a time. force=true so
        // an accordion's open box yields to the new one.
        var multiOpen = ! usesSheet($lb) && ( $lb.hasClass('lookbook-trigger-openall') || $lb.hasClass('lookbook-trigger-always') );
        if ( ! multiOpen ) {
            closeAllMarkers(true);
        }

        if ( usesSheet($lb) ) {
            openSheet($marker);
        } else if ( ! $lb.hasClass('lookbook-layout-4') ) {
            // Layout 4 boxes live inside the photo and animate via transform —
            // the clamp would overwrite that transform and kill the slide-up.
            clampBoxToViewport($marker.find('.lookbook-box'));
        }

        $marker.addClass('is-active');
        $grab.attr('aria-expanded', 'true');
    }

    function closeMarker(closeBtn) {
        var $box    = $(closeBtn).closest('.lookbook-box');
        var hosted  = $box.length ? $box.data('lbHosted') : null;
        var $marker = hosted ? hosted.marker : $(closeBtn).closest('.lookbook-marker');

        if ( hosted ) {
            closeSheet($box);
        } else {
            // Remove the clamp translate entirely ('' , not 'inherit') so
            // stylesheet transforms — layout 4's slide-up — keep working.
            $marker.find('.lookbook-box').css('transform', '');
        }
        $marker.removeClass('is-active');
        $marker.find('.lookbook-grab').attr('aria-expanded', 'false');
        releaseSheet();
    }

    function closeAllMarkers(force) {
        // Hosted mobile sheets first — their boxes live outside the marker.
        $('.lookbook-sheet-host .lookbook-box').each(function () {
            closeSheet($(this));
        });
        $('.lookbook-marker.is-active').each(function () {
            // Accordion keeps one box open on desktop (and on the "Compact"
            // mobile style); it only yields when a new box is about to be
            // opened (force).
            if ( ! force && ! usesSheet($(this).closest('.shoppable-lookbook')) && $(this).closest('.lookbook-trigger-accordion').length ) {
                return;
            }
            $(this).find('.lookbook-box').css('transform', '');
            $(this).removeClass('is-active').find('.lookbook-grab').attr('aria-expanded', 'false');
        });
        releaseSheet();
    }

    // Auto-open boxes for the "Open All" / "Open First" / "Accordion" triggers.
    // Desktop (and the "Compact" mobile style) only — the default mobile
    // bottom sheet shows one box at a time — and only once per lookbook, so
    // boxes the visitor closed stay closed.
    function applyInitialState(root) {
        $(root).find('.shoppable-lookbook').addBack('.shoppable-lookbook').each(function () {
            var $lb     = $(this),
                openAll = $lb.hasClass('lookbook-trigger-openall') || $lb.hasClass('lookbook-trigger-always'),
                openOne = $lb.hasClass('lookbook-trigger-openfirst') || $lb.hasClass('lookbook-trigger-accordion');

            if ( ! openAll && ! openOne ) { return; }

            if ( usesSheet($lb) ) {
                // Boxes opened on desktop would stack as sheets after a resize.
                var $active = $lb.find('.lookbook-marker.is-active');
                if ( $active.length > 1 ) {
                    $active.removeClass('is-active').find('.lookbook-grab').attr('aria-expanded', 'false');
                    releaseSheet();
                }
                return;
            }

            if ( $lb.data('lbAutoOpened') ) { return; }
            $lb.data('lbAutoOpened', 1);

            var $markers = openAll ? $lb.find('.lookbook-marker') : $lb.find('.lookbook-marker').first();
            $markers.addClass('is-active').find('.lookbook-grab').attr('aria-expanded', 'true');
        });
    }

    // Clean up any legacy sheet state when nothing is open.
    function releaseSheet() {
        if ( ! $('.lookbook-marker.is-active').length ) {
            $('body').removeClass('lookbook-sheet-open');
        }
    }

    function isActivationKey(e) {
        return e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32;
    }

    // Public initializer so a preview (admin / editor) can (re)position a subtree.
    window.shoppableLookbookInit = function (root) {
        positionLookbooks(root || document);
        applyInitialState(root || document);
    };

    // (Re)position once each lookbook photo has actually loaded — offsets are
    // only correct after the image has real dimensions. The listener stays
    // bound (not just a one-off check) because a responsive img with
    // srcset/sizes can swap to a different candidate — and fire another
    // 'load' with a different naturalWidth — after a later viewport resize;
    // without this the natural-size cap in positionLookbooks() can get
    // stuck at whatever (possibly smaller) candidate loaded last.
    function bindImageLoads(root) {
        $(root).find('.shoppable-lookbook .lookbook-photo').each(function () {
            var img = this;
            if ( img.complete && img.naturalWidth ) {
                positionLookbooks(img.closest ? img.closest('.shoppable-lookbook') : document);
            }
            $(img).off('load.lookbook').on('load.lookbook', function () {
                positionLookbooks(img.closest ? img.closest('.shoppable-lookbook') : document);
            });
        });
    }

    $(document).ready(function () {

        positionLookbooks(document);
        bindImageLoads(document);
        applyInitialState(document);

        // After all assets (images/fonts) are loaded.
        $(window).on('load', function () { positionLookbooks(document); applyInitialState(document); });

        // Reposition on resize / orientation change (debounced).
        var resizeTimer;
        $(window).on('resize orientationchange', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                // A sheet opened on mobile must not survive a switch to desktop.
                if ( ! isMobile() ) {
                    collapseHostedSheets();
                } else {
                    // Still mobile (e.g. orientation change) — keep any open
                    // sheet aligned with its photo's new width/position.
                    $('.lookbook-sheet-host .lookbook-box').each(function () {
                        var info = $(this).data('lbHosted');
                        if ( info && info.lb ) { positionSheet(info.lb, $(this)); }
                    });
                }
                positionLookbooks(document);
                applyInitialState(document);
            }, 150);
        });

        // Elementor: re-init when widgets render (editor preview + live page).
        $(window).on('elementor/frontend/init', function () {
            if ( window.elementorFrontend && elementorFrontend.hooks ) {
                elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
                    var node = ( $scope && $scope.get ) ? $scope.get(0) : document;
                    positionLookbooks(node);
                    bindImageLoads(node);
                });
            }
        });

        // Fallback for any builder that injects markup via AJAX (WPBakery /
        // Fusion frontend editors, etc.): observe new lookbooks and re-init.
        if ( window.MutationObserver ) {
            var moTimer;
            var observer = new MutationObserver(function (mutations) {
                var found = false;
                mutations.forEach(function (m) {
                    [].forEach.call(m.addedNodes || [], function (n) {
                        if ( n.nodeType === 1 && ( (n.classList && n.classList.contains('shoppable-lookbook')) || (n.querySelector && n.querySelector('.shoppable-lookbook')) ) ) {
                            found = true;
                        }
                    });
                });
                if ( found ) {
                    clearTimeout(moTimer);
                    moTimer = setTimeout(function () { positionLookbooks(document); bindImageLoads(document); }, 200);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        // Hover trigger: open on mouseenter, close when leaving the marker
        // (desktop only — touch devices keep the tap behaviour).
        $(document)
            .on('mouseenter', '.lookbook-trigger-hover .lookbook-grab', function () {
                if ( ! isMobile() ) { openMarker(this); }
            })
            .on('mouseleave', '.lookbook-trigger-hover .lookbook-marker', function () {
                if ( ! isMobile() ) { closeMarker($(this).find('.lookbook-close')); }
            });

        // Delegated handlers so dynamically injected lookbooks (previews) also work.
        $(document)
            .on('click', '.lookbook-grab', function () { openMarker(this); })
            .on('keydown', '.lookbook-grab', function (e) {
                if ( isActivationKey(e) ) { e.preventDefault(); openMarker(this); }
            })
            .on('click', '.lookbook-close', function () { closeMarker(this); })
            .on('keydown', '.lookbook-close', function (e) {
                if ( isActivationKey(e) ) { e.preventDefault(); closeMarker(this); }
            })
            .on('keydown', function (e) {
                if ( e.key === 'Escape' || e.keyCode === 27 ) {
                    closeAllMarkers();
                }
            })
            // Variation select: match attributes → update add-to-cart button.
            .on('change', '.lookbook-variation-select', function () {
                var $variation = $(this).closest('.lookbook-variation');
                // Works inside a marker box and inside a Product List card (Pro).
                var $box       = $(this).closest('.lookbook-box, .lbpl-item');
                var variations = $variation.data('variations') || [];

                // Collect all selected attribute values.
                var selected = {};
                $variation.find('.lookbook-variation-select').each(function () {
                    selected[$(this).data('attr')] = $(this).val();
                });

                // All attributes must be chosen.
                var allPicked = true;
                $.each(selected, function (k, v) { if (!v) { allPicked = false; return false; } });

                var $btn = $box.find('.lookbook-variation-btn');

                if (!allPicked) {
                    resetVariationBtn($btn);
                    return;
                }

                // Find the first matching in-stock variation.
                var match = null;
                for (var i = 0; i < variations.length; i++) {
                    var v = variations[i];
                    var ok = true;
                    for (var key in selected) {
                        if (!selected.hasOwnProperty(key)) { continue; }
                        var vAttr = v.attributes[key];
                        // Empty string in variation data means "any".
                        if (vAttr !== '' && vAttr !== selected[key]) { ok = false; break; }
                    }
                    if (ok && v.in_stock) { match = v; break; }
                }

                if (match) {
                    $btn.attr('href', '?add-to-cart=' + match.id + '&quantity=1')
                        .attr('data-product_id', match.id)
                        // Set via .data() too — WooCommerce's add-to-cart script
                        // reads $btn.data('product_id'), and jQuery caches that
                        // after the first read, ignoring later attr changes.
                        .data('product_id', match.id)
                        .addClass('ajax_add_to_cart add_to_cart_button')
                        .removeAttr('aria-disabled');
                } else {
                    resetVariationBtn($btn);
                }
            })
            // Clicking a not-yet-enabled variation button: point at the selects
            // that still need a choice instead of doing nothing.
            .on('click', '.lookbook-variation-btn', function (e) {
                if ( $(this).attr('aria-disabled') !== 'true' ) { return; }
                e.preventDefault();
                var $empty = $(this).closest('.lookbook-box, .lbpl-item')
                    .find('.lookbook-variation-select')
                    .filter(function () { return ! this.value; });
                $empty.addClass('lb-attention');
                setTimeout(function () { $empty.removeClass('lb-attention'); }, 1200);
            });
    });

    function resetVariationBtn($btn) {
        $btn.attr('href', '#')
            .removeAttr('data-product_id')
            .removeData('product_id')
            .removeClass('ajax_add_to_cart add_to_cart_button')
            .attr('aria-disabled', 'true');
    }

    // WooCommerce's core AJAX add-to-cart script (enqueued alongside this file)
    // handles the actual add: it adds/removes the "loading" class itself (styled
    // as a spinner below) and fires this event on success. Give success an
    // unmistakable "Added" state instead of WC's default 30%-opacity dim.
    $(document.body).on('added_to_cart', function (e, fragments, cartHash, $button) {
        if (!$button || !$button.length || !$button.closest('.lookbook-cart').length) {
            return;
        }
        var i18n = (window.shoppableLookbookFront && shoppableLookbookFront.i18n) || {};
        var original = lbOriginalText($button);
        clearTimeout($button.data('lbRevertTimer'));
        $button.addClass('lb-cart-added').text(i18n.added || original);
        var timer = setTimeout(function () {
            $button.removeClass('lb-cart-added').text(original);
        }, 2200);
        $button.data('lbRevertTimer', timer);
    });

    // Swap the label to "Adding…" the moment the click happens, so the spinner
    // (::before on .loading, see CSS) isn't sitting next to a static "Add to
    // cart" the whole time it's working.
    $(document.body).on('click', '.lookbook-cart .ajax_add_to_cart', function () {
        var i18n = (window.shoppableLookbookFront && shoppableLookbookFront.i18n) || {};
        var $button = $(this);
        lbOriginalText($button);
        if (i18n.adding) {
            $button.text(i18n.adding);
        }
    });

    function lbOriginalText($button) {
        var original = $button.data('lbOriginalText');
        if (original === undefined) {
            original = $button.text();
            $button.data('lbOriginalText', original);
        }
        return original;
    }
})(jQuery);
