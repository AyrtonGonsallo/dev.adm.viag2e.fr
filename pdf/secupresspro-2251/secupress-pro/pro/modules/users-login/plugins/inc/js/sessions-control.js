/* global jQuery, ajaxurl, commonL10n, spSessionsControlL10n, secupressIsSpaceOrEnterKey */
(function($, d, w, undefined) {
// IMs405c!HdM%qaGWF8!rWyTK
	spSessionsControlL10n.userId = Number( spSessionsControlL10n.userId );

	// Make notice dismissible.
	function makeNoticeDismissible() {
		if ( ! spSessionsControlL10n.hasDismissibleNotices ) {
			return;
		}

		$( '#secupress-sessions-control-notice' ).each( function() {
			var $el     = $( this ),
				$button = $( '<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>' ),
				btnText = commonL10n.dismiss || '';

			// Ensure plain text.
			$button.find( '.screen-reader-text' ).text( btnText );
			$button.on( 'click.wp-dismiss-notice', function( e ) {
				e.preventDefault();
				$el.fadeTo( 100, 0, function() {
					$el.slideUp( 100, function() {
						$el.remove();
					} );
				} );
			} );

			$el.append( $button );
		} );
	}

	// Display a notice.
	function displayNotice( r ) {
		var $title, $notice;

		if ( ! r.data.message ) {
			return;
		}

		$notice = $( '#secupress-sessions-control-notice' );

		// The notice exists, change its content.
		if ( $notice.length ) {
			$notice.removeClass( 'updated error' ).addClass( r.success ? 'updated' : 'error' );
			$notice.children( 'p' ).remove();
			$notice.prepend( '<p>' + r.data.message + '</p>' );
			return;
		}

		// Create a new notice.
		$title  = $( '.wrap > h1' ).first();
		$notice = $( '<div id="secupress-sessions-control-notice" class="updated notice is-dismissible"><p></p></div>' );
		$notice.children( 'p' ).html( r.data.message );

		if ( ! $title.length ) {
			$title = $( '.wrap > h2' ).first();
		}

		if ( $title.length ) {
			$title.after( $notice );
		} else {
			$( '.wrap' ).first().prepend( $notice );
		}

		// Make the notice dismissible.
		makeNoticeDismissible();
	}

	// Change cells text, given a set of "Destroy user sessions" buttons.
	function changeCellText( $buttons ) {
		$buttons.closest( 'tr' ).each( function() {
			var id      = Number( this.id.replace( /^user-/, '' ) ),
				message = id === spSessionsControlL10n.userId ? spSessionsControlL10n.currentUserCellText : spSessionsControlL10n.otherUsersCellText;

			$( this ).children( '.column-secupress-sessions' ).html( '<em>' + message + '</em>' );
			$( this ).find( '#user_' + id ).prop( 'checked', false );
		} );
	}

	// Disable a button and get its href attribute.
	function prepareAjaxCall( e, button ) {
		var $button, href;

		if ( "keyup" === e.type && ! secupressIsSpaceOrEnterKey( e ) ) {
			return false;
		}

		$button = $( button );

		if ( $button.hasClass( 'disabled' ) ) {
			return false;
		}
		$button.addClass( 'disabled' ).attr( { 'aria-disabled': true } );

		e.preventDefault();

		href = $button.attr( 'href' );
		return href ? href.replace( 'users.php', 'admin-ajax.php' ) : false;
	}

	// Bulk: insert a new value.
	$( '.bulkactions select' ).append( '<option value="secupress-destroy-user-sessions">' + spSessionsControlL10n.destroySessionsText + '</option>' );

	// Bind submit event on bulk action.
	$( '.bulkactions select' ).first().closest( 'form' ).on( 'submit.secupress', function( e ) {
		var $this    = $( this ),
			$buttons = $this.find( '[type="submit"]' ),
			params   = {
				'action':   'secupress-destroy-user-sessions',
				'_wpnonce': spSessionsControlL10n.bulkNonce,
				'users':    []
			},
			tmp, hasAction = false;

		if ( $buttons.first().attr( 'disabled' ) ) {
			return;
		}

		tmp = $this.serializeArray();

		$.each( tmp, function( i, v ) {
			// Test for the "action" value.
			if ( ( 'action' === v.name || 'action2' === v.name ) && 'secupress-destroy-user-sessions' === v.value ) {
				hasAction = true;
			}
			// Test for users.
			else if ( 'users[]' === v.name && v.value ) {
				params.users.push( Number( v.value ) );
			}
		} );

		if ( hasAction && ! params.users ) {
			e.preventDefault();
			return;
		}

		if ( ! hasAction ) {
			return;
		}

		$buttons.attr( { 'disabled': 'disabled', 'aria-disabled': true } );
		e.preventDefault();

		$.getJSON( ajaxurl, params )
		.done( function( r ) {
			if ( ! $.isPlainObject( r ) || ! r.data ) {
				return;
			}

			// Change the rows cell content.
			if ( r.success ) {
				$.each( r.data.ids, function( i, id ) {
					changeCellText( $( '#user-' + id ) );
				} );
			}

			// Insert a notice.
			displayNotice( r );
		} )
		.always( function() {
			$buttons.removeAttr( 'disabled aria-disabled' );
		} );
	} ).find( '[type="submit"]' ).removeAttr( 'disabled aria-disabled' );

} )(jQuery, document, window);
