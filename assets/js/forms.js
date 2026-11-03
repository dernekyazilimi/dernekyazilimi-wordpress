/**
 * Dernek Yazılımı forms: send the answers to this site, which passes them on
 * to the association's portal, then show the portal's page in a frame.
 */
( function () {
	'use strict';

	var settings = window.dernekyazilimiForms || {};
	var i18n = settings.i18n || {};

	function post( path, data ) {
		return fetch( settings.restUrl + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			credentials: 'omit',
			body: JSON.stringify( data ),
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				return { ok: response.ok, body: body || {} };
			} );
		} );
	}

	function values( form ) {
		var data = {};
		Array.prototype.forEach.call( form.elements, function ( element ) {
			if ( ! element.name || element.disabled ) {
				return;
			}
			if ( ( element.type === 'checkbox' || element.type === 'radio' ) && ! element.checked ) {
				return;
			}
			var nested = element.name.match( /^(\w+)\[(\w+)\]$/ );
			if ( nested ) {
				data[ nested[ 1 ] ] = data[ nested[ 1 ] ] || {};
				data[ nested[ 1 ] ][ nested[ 2 ] ] = element.value;
			} else {
				data[ element.name ] = element.value;
			}
		} );
		return data;
	}

	// Cloudflare Turnstile: the widget puts its answer in a "turnstile" field
	// of the form. The script may already be on the page from another plugin.
	function drawTurnstile() {
		if ( ! window.turnstile ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '.dy-turnstile:not([data-dy-widget])' ), function ( box ) {
			box.setAttribute( 'data-dy-widget', window.turnstile.render( box, {
				sitekey: box.getAttribute( 'data-sitekey' ),
				'response-field-name': 'turnstile',
				callback: function () {
					box.dispatchEvent( new CustomEvent( 'dy-turnstile-done' ) );
				},
			} ) );
		} );
	}
	window.dernekyazilimiTurnstile = drawTurnstile;

	// An answer is good for one submission only.
	function resetTurnstile( box ) {
		if ( box && window.turnstile && box.getAttribute( 'data-dy-widget' ) ) {
			window.turnstile.reset( box.getAttribute( 'data-dy-widget' ) );
		}
	}

	// Frames show "Loading…" until their page is in: a slow payment page
	// otherwise looks like an empty, broken window.
	function openFrame( iframe, url ) {
		var loading = iframe.parentNode.querySelector( '.dy-loading' );
		if ( loading ) {
			loading.hidden = false;
		}
		iframe.src = url;
	}

	function watchFrame( iframe ) {
		var loading = iframe.parentNode.querySelector( '.dy-loading' );
		if ( loading ) {
			iframe.addEventListener( 'load', function () {
				loading.hidden = true;
			} );
		}
	}

	function setup( root ) {
		var form = root.querySelector( '.dy-form' );
		var message = root.querySelector( '.dy-message' );
		var widget = root.querySelector( '.dy-turnstile' );
		var submit = root.querySelector( '.dy-submit' );
		var frameBox = root.querySelector( '.dy-frame' );
		var frame = frameBox ? frameBox.querySelector( 'iframe' ) : null;
		var kind = root.getAttribute( 'data-dy-kind' );
		var verifiedPhone = null;

		if ( ! form ) {
			return;
		}

		function say( text, good ) {
			message.textContent = text || '';
			message.hidden = ! text;
			message.classList.toggle( 'dy-good', !! good );
		}

		function clearErrors() {
			say( '' );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-error' ), function ( node ) {
				node.remove();
			} );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-invalid' ), function ( node ) {
				node.classList.remove( 'dy-invalid' );
			} );
		}

		function fieldError( name, text ) {
			var field = root.querySelector( '[data-dy-field="' + name + '"]' );
			if ( ! field ) {
				return false;
			}
			var note = document.createElement( 'p' );
			note.className = 'dy-error';
			note.textContent = text;
			field.appendChild( note );
			field.classList.add( 'dy-invalid' );
			return true;
		}

		function showErrors( body ) {
			var shown = false;
			var first = null;
			Object.keys( body.errors || {} ).forEach( function ( name ) {
				if ( fieldError( name, body.errors[ name ][ 0 ] ) ) {
					shown = true;
					first = first || root.querySelector( '[data-dy-field="' + name + '"]' );
				}
			} );
			if ( ! shown || ! body.errors ) {
				say( body.message || i18n.failed );
			}
			if ( first ) {
				first.scrollIntoView( { block: 'center', behavior: 'smooth' } );
			}
		}

		function validate() {
			var valid = true;
			Array.prototype.forEach.call( form.querySelectorAll( '[required]' ), function ( element ) {
				if ( element.checkValidity() ) {
					return;
				}
				var field = element.closest( '[data-dy-field]' );
				if ( field && ! field.querySelector( '.dy-error' ) ) {
					fieldError( field.getAttribute( 'data-dy-field' ), element.validationMessage || i18n.required );
				}
				if ( valid ) {
					element.focus();
				}
				valid = false;
			} );
			return valid;
		}

		function busy( button, on ) {
			if ( on ) {
				button.dataset.dyLabel = button.textContent;
				button.textContent = i18n.sending;
			} else if ( button.dataset.dyLabel ) {
				button.textContent = button.dataset.dyLabel;
			}
			button.disabled = on;
		}

		var modal = root.querySelector( '.dy-modal:not(.dy-doc-modal)' );
		var docModal = root.querySelector( '.dy-doc-modal' );

		// Agreement text: the portal's page opens in a dialog over the page.
		if ( docModal && typeof docModal.showModal === 'function' ) {
			var docFrame = docModal.querySelector( 'iframe' );
			watchFrame( docFrame );
			Array.prototype.forEach.call( root.querySelectorAll( '.dy-doc-link' ), function ( link ) {
				link.addEventListener( 'click', function ( event ) {
					if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
						return;
					}
					event.preventDefault();
					openFrame( docFrame, link.href );
					docModal.querySelector( '.dy-modal-head strong' ).textContent = link.textContent;
					docModal.querySelector( '.dy-fallback a' ).href = link.href;
					document.documentElement.classList.add( 'dy-modal-open' );
					docModal.showModal();
				} );
			} );
			docModal.querySelector( '.dy-modal-close' ).addEventListener( 'click', function () {
				docModal.close();
			} );
			docModal.addEventListener( 'click', function ( event ) {
				if ( event.target === docModal ) {
					docModal.close();
				}
			} );
			docModal.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'dy-modal-open' );
				docFrame.src = 'about:blank';
			} );
		}
		var framePlace = frameBox ? document.createComment( 'dy-frame' ) : null;
		if ( frame ) {
			watchFrame( frame );
		}
		var resultUrl = '';
		var reachedPortal = false;

		function showFrame( url, note ) {
			var noteBox = frameBox.querySelector( '.dy-note' );
			noteBox.textContent = note || '';
			noteBox.hidden = ! note;
			frameBox.querySelector( '.dy-fallback a' ).href = url;
			frame.style.height = '';
			openFrame( frame, url );
			form.hidden = true;
			frameBox.hidden = false;
			root.scrollIntoView( { block: 'start', behavior: 'smooth' } );
		}

		function showForm() {
			frame.src = 'about:blank';
			frameBox.hidden = true;
			form.hidden = false;
		}

		// Card payment: the provider's page opens in a dialog over the page.
		function showPayment( url, result ) {
			if ( ! modal || typeof modal.showModal !== 'function' ) {
				showFrame( url );
				return;
			}
			resultUrl = result || '';
			reachedPortal = false;
			frameBox.parentNode.insertBefore( framePlace, frameBox );
			modal.querySelector( '.dy-modal-body' ).appendChild( frameBox );
			frameBox.querySelector( '.dy-fallback a' ).href = url;
			frame.style.height = '';
			openFrame( frame, url );
			frameBox.hidden = false;
			document.documentElement.classList.add( 'dy-modal-open' );
			modal.showModal();
		}

		function closePayment() {
			if ( ! reachedPortal && ! window.confirm( i18n.closePayment ) ) {
				return;
			}
			modal.close();
		}

		if ( modal ) {
			modal.querySelector( '.dy-modal-close' ).addEventListener( 'click', closePayment );
			// Escape asks first, like the close button.
			modal.addEventListener( 'cancel', function ( event ) {
				event.preventDefault();
				closePayment();
			} );
			modal.addEventListener( 'close', function () {
				document.documentElement.classList.remove( 'dy-modal-open' );
				framePlace.parentNode.replaceChild( frameBox, framePlace );
				// After the payment the result stays on the page; a payment
				// left unfinished brings the form back.
				if ( reachedPortal && resultUrl ) {
					showFrame( resultUrl );
				} else {
					showForm();
				}
			} );
		}

		// The portal's pages report their height and may ask for the form again.
		window.addEventListener( 'message', function ( event ) {
			if ( ! frame || event.source !== frame.contentWindow || event.origin !== settings.portalOrigin || ! event.data ) {
				return;
			}
			if ( event.data.type === 'dernekyazilimi:height' && event.data.height > 0 ) {
				reachedPortal = true;
				frame.style.height = Math.min( Math.ceil( event.data.height ) + 8, 20000 ) + 'px';
			}
			if ( event.data.type === 'dernekyazilimi:restart' ) {
				reachedPortal = false;
				if ( modal && modal.open ) {
					modal.close();
				} else {
					showForm();
				}
			}
		} );

		// Suggested amounts.
		Array.prototype.forEach.call( root.querySelectorAll( '[data-dy-amount]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				form.elements.amount.value = button.getAttribute( 'data-dy-amount' );
			} );
		} );

		// Phone verification.
		var phone = form.elements.phone_number;
		var sendCode = root.querySelector( '.dy-send-code' );
		var verifyCode = root.querySelector( '.dy-verify-code' );
		var codeBox = root.querySelector( '.dy-code' );
		var phoneState = root.querySelector( '.dy-phone-state' );

		if ( phone && sendCode ) {
			phone.addEventListener( 'input', function () {
				if ( verifiedPhone !== null && phone.value !== verifiedPhone ) {
					verifiedPhone = null;
					phoneState.classList.remove( 'dy-good' );
					phoneState.textContent = i18n.verifyFirst;
					sendCode.hidden = false;
				}
			} );

			sendCode.addEventListener( 'click', function () {
				clearErrors();
				if ( ! phone.value.trim() ) {
					fieldError( 'phone_number', i18n.required );
					phone.focus();
					return;
				}
				busy( sendCode, true );
				post( 'phone/send', { phone_number: phone.value, website: form.elements.website.value } ).then( function ( result ) {
					busy( sendCode, false );
					if ( ! result.ok ) {
						fieldError( 'phone_number', ( result.body.errors && result.body.errors.phone_number && result.body.errors.phone_number[ 0 ] ) || result.body.message || i18n.failed );
						return;
					}
					sendCode.textContent = i18n.sendAgain;
					phoneState.textContent = i18n.codeSent;
					codeBox.hidden = false;
					form.elements.code.focus();
				} ).catch( function () {
					busy( sendCode, false );
					fieldError( 'phone_number', i18n.failed );
				} );
			} );

			verifyCode.addEventListener( 'click', function () {
				clearErrors();
				var code = form.elements.code.value.replace( /\D/g, '' );
				if ( code.length !== 6 ) {
					fieldError( 'code', i18n.codeNeeded );
					return;
				}
				busy( verifyCode, true );
				post( 'phone/verify', { phone_number: phone.value, code: code, website: form.elements.website.value } ).then( function ( result ) {
					busy( verifyCode, false );
					if ( ! result.ok ) {
						fieldError( 'code', result.body.message || i18n.failed );
						return;
					}
					verifiedPhone = phone.value;
					codeBox.hidden = true;
					sendCode.hidden = true;
					phoneState.textContent = i18n.verified;
					phoneState.classList.add( 'dy-good' );
				} ).catch( function () {
					busy( verifyCode, false );
					fieldError( 'code', i18n.failed );
				} );
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearErrors();
			if ( ! validate() ) {
				return;
			}
			if ( phone && sendCode && verifiedPhone !== phone.value ) {
				fieldError( 'phone_number', i18n.verifyFirst );
				phone.scrollIntoView( { block: 'center', behavior: 'smooth' } );
				return;
			}

			var data = values( form );
			var path = 'donations';
			if ( root.getAttribute( 'data-dy-form' ) === 'account' ) {
				path = 'accounts';
				data.form = kind;
				delete data.code;
			}

			busy( submit, true );
			post( path, data ).then( function ( result ) {
				busy( submit, false );
				resetTurnstile( widget );
				if ( ! result.ok ) {
					showErrors( result.body );
					return;
				}
				if ( result.body.frame_url && result.body.method === 'card' ) {
					showPayment( result.body.frame_url, result.body.result_url );
				} else if ( result.body.frame_url ) {
					showFrame( result.body.frame_url );
				} else if ( result.body.continue_url ) {
					showFrame( result.body.continue_url, result.body.created === false ? i18n.existingNote : '' );
				} else {
					form.hidden = true;
					root.querySelector( '.dy-done' ).hidden = false;
					root.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				}
			} ).catch( function () {
				busy( submit, false );
				resetTurnstile( widget );
				say( i18n.failed );
			} );
		} );
	}

	function setupVerification( root ) {
		var form = root.querySelector( 'form' );
		var input = form.querySelector( '[name="kod"]' );
		var button = form.querySelector( '[type="submit"]' );
		var widget = form.querySelector( '.dy-turnstile' );
		var message = root.querySelector( '.dy-message' );
		var documentBox = root.querySelector( '[data-dy-document]' );
		var headline = root.querySelector( '[data-dy-headline]' );
		var status = root.querySelector( '[data-dy-status]' );
		var files = root.querySelector( '[data-dy-files]' );
		var attachments = root.querySelector( '[data-dy-attachments]' );
		var preview = root.querySelector( '[data-dy-preview]' );
		var waiting = false;

		function say( text, failed ) {
			message.textContent = text || '';
			message.hidden = ! text;
			message.classList.toggle( 'dy-failed', !! failed );
		}
		function publicUrl( value ) {
			try {
				var url = new URL( value );
				return url.protocol === 'https:' && url.origin === settings.portalOrigin ? url.href : '';
			} catch ( error ) { return ''; }
		}
		function fileButton( label, value, outline, download ) {
			var url = publicUrl( value );
			if ( ! url ) { return; }
			var link = document.createElement( 'a' );
			link.className = 'wp-element-button' + ( outline ? ' is-style-outline' : '' );
			link.textContent = label;
			link.href = url;
			if ( ! download ) {
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
			}
			files.appendChild( link );
		}
		function attachmentRow( attachment ) {
			var row = document.createElement( 'li' );
			var url = publicUrl( attachment.url );
			if ( url ) {
				var link = document.createElement( 'a' );
				link.textContent = attachment.name;
				link.href = url;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				row.appendChild( link );
			} else {
				row.textContent = attachment.name + ' (' + attachments.dataset.physicalLabel + ')';
			}
			attachments.querySelector( 'ul' ).appendChild( row );
			attachments.hidden = false;
		}
		function clearDocument() {
			documentBox.hidden = true;
			attachments.hidden = true;
			preview.hidden = true;
			preview.removeAttribute( 'src' );
			files.textContent = '';
			attachments.querySelector( 'ul' ).textContent = '';
		}
		function show( body ) {
			var cancelled = body.status === 'cancelled';
			Array.prototype.forEach.call( root.querySelectorAll( '[data-dy-value]' ), function ( field ) {
				field.textContent = body[ field.dataset.dyValue ] || '—';
			} );
			documentBox.classList.toggle( 'is-cancelled', cancelled );
			headline.textContent = cancelled ? headline.dataset.cancelled : headline.dataset.active;
			status.textContent = cancelled ? status.dataset.cancelled : status.dataset.active;
			fileButton( files.dataset.previewLabel, body.preview_url, false, false );
			fileButton( files.dataset.downloadLabel, body.download_url, true, true );
			fileButton( files.dataset.originalLabel, body.original_url, true, false );
			( Array.isArray( body.attachments ) ? body.attachments : [] ).forEach( attachmentRow );
			if ( publicUrl( body.preview_url ) ) {
				preview.src = publicUrl( body.preview_url );
				preview.hidden = false;
			}
			form.hidden = true;
			documentBox.hidden = false;
			say( '' );
			headline.focus();
		}
		function verify() {
			waiting = false;
			clearDocument();
			say( i18n.sending );
			button.disabled = true;
			post( 'documents/verify', values( form ) ).then( function ( result ) {
				if ( ! result.ok ) {
					say( result.body.message || i18n.failed, true );
				} else if ( [ 'numbered', 'cancelled' ].indexOf( result.body.status ) === -1 ) {
					say( i18n.failed, true );
				} else {
					show( result.body );
				}
			} ).catch( function () {
				say( i18n.failed, true );
			} ).then( function () {
				button.disabled = false;
				if ( ! form.hidden ) {
					resetTurnstile( widget );
				}
			} );
		}
		// The check may still be running, above all for a code from a QR link:
		// the lookup then starts as soon as its answer is in.
		function request() {
			if ( ! widget || ( form.elements.turnstile && form.elements.turnstile.value ) ) {
				verify();
				return;
			}
			waiting = true;
			say( i18n.humanCheck );
		}
		if ( widget ) {
			widget.addEventListener( 'dy-turnstile-done', function () {
				if ( waiting ) { verify(); }
			} );
		}
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( ! button.disabled ) { request(); }
		} );
		root.querySelector( '[data-dy-again]' ).addEventListener( 'click', function () {
			clearDocument();
			form.hidden = false;
			resetTurnstile( widget );
			input.value = '';
			if ( form.elements.sayi ) { form.elements.sayi.value = ''; }
			input.focus();
		} );
		var code = new URLSearchParams( window.location.search ).get( 'kod' );
		if ( code ) {
			input.value = code;
			request();
		}
	}

	function start() {
		drawTurnstile();
		Array.prototype.forEach.call( document.querySelectorAll( '[data-dy-verify]' ), function ( root ) {
			if ( ! root.dataset.dyReady ) {
				root.dataset.dyReady = '1';
				setupVerification( root );
			}
		} );
		Array.prototype.forEach.call( document.querySelectorAll( '.dernekyazilimi[data-dy-form]' ), function ( root ) {
			if ( ! root.dataset.dyReady ) {
				root.dataset.dyReady = '1';
				setup( root );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
