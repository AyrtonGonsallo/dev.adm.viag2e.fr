/* global jQuery, _, ajaxurl, pwsL10n, userProfileL10n */
(function($, w, d, undefined) {
	var updateLock = false,
		$pass1Row,
		$pass1Wrap,
		$pass1,
		$pass1Text,
		$pass1Label,
		$pass2,
		$weakRow,
		$toggleButton,
		$submitButtons,
		$submitButton,
		currentPass,
		inputEvent;

	/*
	 * Use feature detection to determine whether password inputs should use
	 * the `keyup` or `input` event. Input is preferred but lacks support
	 * in legacy browsers.
	 */
	if ( 'oninput' in d.createElement( 'input' ) ) {
		inputEvent = 'input';
	} else {
		inputEvent = 'keyup';
	}

	function generatePassword() {
		if ( typeof zxcvbn !== 'function' ) {
			setTimeout( generatePassword, 50 );
		} else {
			$pass1.val( $pass1.data( 'pw' ) );
			$pass1.trigger( 'pwupdate' ).trigger( 'wp-check-valid-field' );

			if ( 1 !== parseInt( $toggleButton.data( 'start-masked' ), 10 ) ) {
				$pass1Wrap.addClass( 'show-password' );
			} else {
				$toggleButton.trigger( 'click' );
			}
		}
	}

	function bindPass1() {
		var passStrength = $( '#secupress-pass-strength-result' )[0];

		currentPass = $pass1.val();

		$pass1Wrap = $pass1.parent();

		$pass1Text = $( '<input type="text"/>' )
			.attr( {
				'id':           'pass1-text',
				'name':         'pass1-text',
				'autocomplete': 'off'
			} )
			.addClass( $pass1[0].className )
			.data( 'pw', $pass1.data( 'pw' ) )
			.val( $pass1.val() )
			.on( inputEvent, function () {
				if ( $pass1Text.val() === currentPass ) {
					return;
				}
				$pass2.val( $pass1Text.val() );
				$pass1.val( $pass1Text.val() ).trigger( 'pwupdate' );
				currentPass = $pass1Text.val();
			} );

		$pass1.after( $pass1Text );

		if ( 1 === parseInt( $pass1.data( 'reveal' ), 10 ) ) {
			generatePassword();
		}

		$pass1.on( inputEvent + ' pwupdate', function () {
			if ( $pass1.val() === currentPass ) {
				return;
			}

			currentPass = $pass1.val();
			if ( $pass1Text.val() !== currentPass ) {
				$pass1Text.val( currentPass );
			}
			$pass1.add( $pass1Text ).removeClass( 'short bad good strong' );

			if ( passStrength.className ) {
				$pass1.add( $pass1Text ).addClass( passStrength.className );
				if ( 'strong' !== passStrength.className ) {
					$submitButtons.prop( 'disabled', true );
					$weakRow.show();
				} else {
					$submitButtons.prop( 'disabled', false );
					$weakRow.hide();
				}
			}
		} );
	}

	function resetToggle() {
		$toggleButton
			.data( 'toggle', 0 )
			.attr( {
				'aria-label': userProfileL10n.ariaHide
			} )
			.find( '.text' )
				.text( userProfileL10n.hide )
			.end()
			.find( '.dashicons' )
				.removeClass( 'dashicons-visibility' )
				.addClass( 'dashicons-hidden' );

		$pass1Text.focus();

		$pass1Label.attr( 'for', 'pass1-text' );
	}

	function bindToggleButton() {
		$toggleButton = $pass1Row.find( '.wp-hide-pw' );
		$toggleButton.show().on( 'click', function () {
			if ( 1 === parseInt( $toggleButton.data( 'toggle' ), 10 ) ) {
				$pass1Wrap.addClass( 'show-password' );

				resetToggle();

				if ( ! _.isUndefined( $pass1Text[0].setSelectionRange ) ) {
					$pass1Text[0].setSelectionRange( 0, 100 );
				}
			} else {
				$pass1Wrap.removeClass( 'show-password' );
				$toggleButton
					.data( 'toggle', 1 )
					.attr( {
						'aria-label': userProfileL10n.ariaShow
					} )
					.find( '.text' )
						.text( userProfileL10n.show )
					.end()
					.find( '.dashicons' )
						.removeClass( 'dashicons-hidden' )
						.addClass( 'dashicons-visibility' );

				$pass1.focus();

				$pass1Label.attr( 'for', 'secupress-pass1' );

				if ( ! _.isUndefined( $pass1[0].setSelectionRange ) ) {
					$pass1[0].setSelectionRange( 0, 100 );
				}
			}
		} );
	}

	function bindPasswordForm() {
		var $passwordWrapper,
			$generateButton,
			$cancelButton;

		$pass1Row   = $( '.secupress-user-pass1-wrap' );
		$pass1Label = $pass1Row.find( 'th label' ).attr( 'for', 'pass1-text' );

		// Hide this.
		$( '.secupress-user-pass2-wrap' ).hide();

		$submitButton = $( '#submit' ).on( 'click', function () {
			updateLock = false;
		} );

		$submitButtons = $submitButton.add( ' #createusersub' );
		$weakRow       = $( '.pw-weak' );
		$pass1         = $( '#secupress-pass1' );

		if ( $pass1.length ) {
			bindPass1();
		}

		/**
		 * Fix a LastPass mismatch issue, LastPass only changes pass2.
		 *
		 * This fixes the issue by copying any changes from the hidden
		 * pass2 field to the pass1 field, then running check_pass_strength.
		 */
		$pass2 = $( '#secupress-pass2' ).on( inputEvent, function () {
			if ( $pass2.val().length > 0 ) {
				$pass1.val( $pass2.val() );
				$pass2.val( '' );
				currentPass = '';
				$pass1.trigger( 'pwupdate' );
			}
		} );

		// Disable hidden inputs to prevent autofill and submission.
		if ( $pass1.is( ':hidden' ) ) {
			$pass1.prop( 'disabled', true );
			$pass2.prop( 'disabled', true );
			$pass1Text.prop( 'disabled', true );
		}

		$passwordWrapper = $pass1Row.find( '.wp-pwd' );
		$generateButton  = $pass1Row.find( 'button.wp-generate-pw' );

		bindToggleButton();

		if ( $generateButton.length ) {
			$passwordWrapper.hide();
		}

		$generateButton.show();
		$generateButton.on( 'click', function () {
			updateLock = true;

			$generateButton.hide();
			$passwordWrapper.show();

			// Enable the inputs when showing.
			$pass1.attr( 'disabled', false );
			$pass2.attr( 'disabled', false );
			$pass1Text.attr( 'disabled', false );

			if ( $pass1Text.val().length === 0 ) {
				generatePassword();
			}

			_.defer( function() {
				$pass1Text.focus();
				if ( ! _.isUndefined( $pass1Text[0].setSelectionRange ) ) {
					$pass1Text[0].setSelectionRange( 0, 100 );
				}
			}, 0 );
		} );

		$cancelButton = $pass1Row.find( 'button.wp-cancel-pw' );
		$cancelButton.on( 'click', function () {
			updateLock = false;

			// Clear any entered password.
			$pass1Text.val( '' );

			// Generate a new password.
			wp.ajax.post( 'secupress-generate-password' )
				.done( function( data ) {
					$pass1.data( 'pw', data );
				} );

			$generateButton.show();
			$passwordWrapper.hide();
			$weakRow.hide();

			// Disable the inputs when hiding to prevent autofill and submission.
			$pass1.prop( 'disabled', true );
			$pass2.prop( 'disabled', true );
			$pass1Text.prop( 'disabled', true );

			resetToggle();

			// Clear password field to prevent update.
			$pass1.val( '' ).trigger( 'pwupdate' );
			$submitButtons.prop( 'disabled', false );
		} );

		$pass1Row.closest( 'form' ).on( 'submit', function () {
			updateLock = false;

			$pass1.prop( 'disabled', false );
			$pass2.prop( 'disabled', false );
			$pass2.val( $pass1.val() );
			$pass1Wrap.removeClass( 'show-password' );
		} );
	}

	function check_pass_strength() {
		var pass1 = $( '#secupress-pass1' ).val(), strength;

		$( '#secupress-pass-strength-result' ).removeClass( 'short bad good strong' );
		if ( ! pass1 ) {
			$( '#secupress-pass-strength-result' ).html( '&nbsp;' );
			return;
		}

		strength = wp.passwordStrength.meter( pass1, wp.passwordStrength.userInputDisallowedList(), pass1 );

		switch ( strength ) {
			case 2:
				$( '#secupress-pass-strength-result' ).addClass( 'bad' ).html( pwsL10n.bad );
				break;
			case 3:
				$( '#secupress-pass-strength-result' ).addClass( 'good' ).html( pwsL10n.good );
				break;
			case 4:
				$( '#secupress-pass-strength-result' ).addClass( 'strong' ).html( pwsL10n.strong );
				break;
			case 5:
				$( '#secupress-pass-strength-result' ).addClass( 'short' ).html( pwsL10n.mismatch );
				break;
			default:
				$( '#secupress-pass-strength-result' ).addClass( 'short' ).html( pwsL10n.short );
		}
	}

	$( d ).ready( function() {
		$( '#secupress-pass1' ).val( '' ).on( inputEvent + ' pwupdate', check_pass_strength );
		$( '#secupress-pass-strength-result' ).show();
		bindPasswordForm();
	} );

	w.generatePassword = generatePassword;

	/* Warn the user if password was generated but not saved. */
	$( w ).on( 'beforeunload', function () {
		if ( true === updateLock ) {
			return userProfileL10n.warn;
		}
	} );

} )(jQuery, window, document);
