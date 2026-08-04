/**
 * Shoppable Lookbook — dynamic Gutenberg block.
 *
 * Uses ServerSideRender so the editor shows a live preview that always matches
 * the front-end output, and a SelectControl in the block sidebar to pick which
 * lookbook to display.
 */
( function( blocks, element, blockEditor, editor, components, serverSideRender, i18n ) {
    var el = element.createElement;
    var __ = i18n.__;

    var registerBlockType  = blocks.registerBlockType;
    // InspectorControls moved from wp.editor to wp.blockEditor in WP 5.2+.
    var InspectorControls  = ( blockEditor && blockEditor.InspectorControls ) || ( editor && editor.InspectorControls );
    var SelectControl      = components.SelectControl;
    var PanelBody          = components.PanelBody;
    var Placeholder        = components.Placeholder;
    // ServerSideRender moved to its own package (wp.serverSideRender) in WP 5.3+.
    var ServerSideRender   = serverSideRender || ( components && components.ServerSideRender );

    var catIcon = el( 'svg', { width: 22, height: 22 },
        el( 'path', { d: "M 14.5625 7.4375 C 10.347656 4.640625 12.253906 1.917969 12.253906 1.917969 C 8.15625 4.277344 7.25 7.878906 8.253906 12.429688 C 6.773438 11.234375 6.5625 9.816406 6.609375 7.960938 C 4.726562 9.972656 4.390625 12.113281 4.515625 14.40625 C 4.710938 18.039062 7.796875 20.929688 11.457031 20.898438 C 15.011719 20.871094 18.015625 17.914062 18.1875 14.328125 C 18.332031 11.308594 17.058594 9.09375 14.5625 7.4375 Z", fill: "#1abc9c" } )
    );

    var blockIcon = el( 'svg', { width: 32, height: 22 },
        el( 'path', { d: "M 14.214844 3.082031 L 8.785156 3.082031 C 4.253906 3.082031 0.574219 6.761719 0.574219 11.292969 L 0.574219 11.683594 C 0.574219 16.214844 4.253906 19.894531 8.785156 19.894531 L 14.191406 19.894531 C 18.722656 19.894531 22.402344 16.214844 22.402344 11.683594 L 22.402344 11.292969 C 22.425781 6.761719 18.746094 3.082031 14.214844 3.082031 Z M 15.777344 12.351562 L 12.351562 12.351562 L 12.351562 15.777344 C 12.351562 15.914062 12.234375 16.007812 12.121094 16.007812 L 10.902344 16.007812 C 10.765625 16.007812 10.671875 15.894531 10.671875 15.777344 L 10.671875 12.351562 L 7.246094 12.351562 C 7.105469 12.351562 7.015625 12.234375 7.015625 12.121094 L 7.015625 10.902344 C 7.015625 10.765625 7.128906 10.671875 7.246094 10.671875 L 10.671875 10.671875 L 10.671875 7.246094 C 10.671875 7.105469 10.785156 7.015625 10.902344 7.015625 L 12.121094 7.015625 C 12.257812 7.015625 12.351562 7.128906 12.351562 7.246094 L 12.351562 10.671875 L 15.777344 10.671875 C 15.914062 10.671875 16.007812 10.785156 16.007812 10.902344 L 16.007812 12.121094 C 16.007812 12.234375 15.914062 12.351562 15.777344 12.351562 Z", fill: "#ffb818" } )
    );

    if ( blocks.updateCategory ) {
        blocks.updateCategory( 'douple', { icon: catIcon } );
    }

    var data    = window.litLookbookBlock || { lookbooks: [] };
    var options = [ { value: '', label: __( 'Select a lookbook', 'shoppable-lookbook' ) } ].concat( data.lookbooks || [] );

    // The ServerSideRender preview is static HTML and the front-end script does
    // not run inside the editor iframe, so we toggle the marker boxes here via a
    // delegated click handler on the preview wrapper (React handles the event).
    function handlePreviewClick( e ) {
        var target = e.target;
        if ( ! target || ! target.closest ) {
            return;
        }

        var link = target.closest( '.lookbook-product' );
        if ( link ) {
            // Don't navigate away from the editor when clicking a product link.
            e.preventDefault();
        }

        var grab = target.closest( '.lookbook-grab' );
        if ( grab ) {
            var marker = grab.closest( '.lookbook-marker' );
            if ( marker && marker.parentNode ) {
                marker.parentNode.querySelectorAll( '.lookbook-marker.is-active' ).forEach( function ( m ) {
                    if ( m !== marker ) { m.classList.remove( 'is-active' ); }
                } );
                marker.classList.toggle( 'is-active' );
            }
            return;
        }

        var close = target.closest( '.lookbook-close' );
        if ( close ) {
            var openMarker = close.closest( '.lookbook-marker' );
            if ( openMarker ) { openMarker.classList.remove( 'is-active' ); }
        }
    }

    registerBlockType( 'douple/shoppable-lookbook', {
        title:    __( 'Shoppable Lookbook', 'shoppable-lookbook' ),
        icon:     blockIcon,
        category: 'douple',
        attributes: {
            id: { type: 'string', default: '' }
        },

        edit: function( props ) {
            var id = props.attributes.id;

            function onChange( value ) {
                props.setAttributes( { id: value } );
            }

            var inspector = InspectorControls ? el( InspectorControls, {},
                el( PanelBody, { title: __( 'Settings', 'shoppable-lookbook' ), initialOpen: true },
                    el( SelectControl, {
                        label:    __( 'Select a lookbook', 'shoppable-lookbook' ),
                        value:    id,
                        options:  options,
                        onChange: onChange
                    } )
                )
            ) : null;

            var preview;
            if ( ! id ) {
                preview = el( Placeholder, {
                        icon:         blockIcon,
                        label:        __( 'Shoppable Lookbook', 'shoppable-lookbook' ),
                        instructions: __( 'Select a lookbook to display.', 'shoppable-lookbook' )
                    },
                    el( SelectControl, {
                        value:    id,
                        options:  options,
                        onChange: onChange
                    } )
                );
            } else if ( ServerSideRender ) {
                preview = el( 'div', { className: 'douple-ssr-preview', onClick: handlePreviewClick },
                    el( ServerSideRender, {
                        block:      'douple/shoppable-lookbook',
                        attributes: { id: id }
                    } )
                );
            } else {
                preview = el( 'div', { className: 'douple-shoppablelookbook' }, '[shoppablelookbook id=' + id + ']' );
            }

            return el( 'div', { className: props.className }, [ inspector, preview ] );
        },

        // Dynamic block: rendered server-side via render_callback.
        save: function() {
            return null;
        }
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.editor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
);
