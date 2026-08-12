( function () {
	'use strict';

	var AUTO_DISMISS_MS = 6000;
	var STATUS_ARGS = [
		'rs_run',
		'rs_snapshot',
		'rs_settings',
		'rs_update',
		'rs_recovery',
		'rs_batch',
		'rs_completed',
		'rs_code',
		'rs_recommend',
		'rs_recovery_snapshot'
	];

	function ready( callback ) {
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', callback );
			return;
		}

		callback();
	}

	function normalizeText( element ) {
		return ( element.textContent || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 300 );
	}

	function storageKey( element ) {
		var text = normalizeText( element );
		var hash = 0;
		var index;

		for ( index = 0; index < text.length; index++ ) {
			hash = ( ( hash << 5 ) - hash ) + text.charCodeAt( index );
			hash |= 0;
		}

		return 'revertshield_notice_' + String( hash );
	}

	function isPersistent( notice ) {
		return notice.classList.contains( 'notice-error' ) ||
			notice.classList.contains( 'error' ) ||
			notice.classList.contains( 'notice-warning' );
	}

	function ensureDismissButton( notice ) {
		if ( notice.querySelector( '.notice-dismiss' ) ) {
			return;
		}

		var button = document.createElement( 'button' );
		var text = document.createElement( 'span' );

		button.type = 'button';
		button.className = 'notice-dismiss';
		text.className = 'screen-reader-text';
		text.textContent = 'Dismiss this notice.';
		button.appendChild( text );
		notice.appendChild( button );
	}

	function markDismissed( notice ) {
		if ( isPersistent( notice ) ) {
			return;
		}

		try {
			window.sessionStorage.setItem( storageKey( notice ), '1' );
		} catch ( error ) {
			// Storage can be unavailable in privacy-restricted browsers.
		}
	}

	function wasDismissed( notice ) {
		if ( isPersistent( notice ) ) {
			return false;
		}

		try {
			return '1' === window.sessionStorage.getItem( storageKey( notice ) );
		} catch ( error ) {
			return false;
		}
	}

	function cleanStatusArguments() {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}

		var url = new URL( window.location.href );
		var changed = false;

		STATUS_ARGS.forEach( function ( key ) {
			if ( url.searchParams.has( key ) ) {
				url.searchParams.delete( key );
				changed = true;
			}
		} );

		if ( changed ) {
			window.history.replaceState( {}, document.title, url.toString() );
		}
	}

	ready( function () {
		var main = document.querySelector( '.revertshield-wrap' );
		var navigation = document.querySelector( '.revertshield-admin-navigation' );
		var parent;
		var center;
		var list;
		var count;
		var observer;

		if ( ! main || ! main.parentNode ) {
			return;
		}

		parent = main.parentNode;

		if ( navigation && navigation.parentNode === parent ) {
			parent.insertBefore( navigation, main );
		}

		center = document.createElement( 'section' );
		center.id = 'revertshield-notice-center';
		center.className = 'revertshield-notice-center';
		center.setAttribute( 'aria-live', 'polite' );
		center.hidden = true;
		center.innerHTML = '<div class="revertshield-notice-center-header"><strong>Notifications</strong><span data-revertshield-notice-count></span></div><div data-revertshield-notice-list></div>';

		if ( navigation && navigation.parentNode === parent ) {
			navigation.insertAdjacentElement( 'afterend', center );
		} else {
			parent.insertBefore( center, main );
		}

		list = center.querySelector( '[data-revertshield-notice-list]' );
		count = center.querySelector( '[data-revertshield-notice-count]' );

		function refreshCount() {
			var total = list.children.length;
			count.textContent = total ? String( total ) : '';
			center.hidden = 0 === total;
		}

		function removeNotice( notice, remember ) {
			if ( remember ) {
				markDismissed( notice );
			}

			if ( notice.parentNode ) {
				notice.parentNode.removeChild( notice );
			}
			refreshCount();
		}

		function manageNotice( notice ) {
			var button;

			if ( ! notice || ! notice.classList || notice.closest( '#revertshield-notice-center' ) ) {
				return;
			}

			if ( notice.classList.contains( 'inline' ) ) {
				return;
			}

			if ( '1' === notice.getAttribute( 'data-revertshield-managed' ) ) {
				return;
			}

			notice.setAttribute( 'data-revertshield-managed', '1' );

			if ( wasDismissed( notice ) ) {
				removeNotice( notice, false );
				return;
			}

			ensureDismissButton( notice );
			list.appendChild( notice );
			button = notice.querySelector( '.notice-dismiss' );

			if ( button ) {
				button.addEventListener( 'click', function () {
					removeNotice( notice, true );
				} );
			}

			if ( ! isPersistent( notice ) ) {
				window.setTimeout( function () {
					if ( notice.parentNode ) {
						removeNotice( notice, true );
					}
				}, AUTO_DISMISS_MS );
			}

			refreshCount();
		}

		function collectNotices() {
			var notices = document.querySelectorAll( '#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error' );

			notices.forEach( function ( notice ) {
				manageNotice( notice );
			} );
		}

		collectNotices();
		cleanStatusArguments();

		observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( 1 !== node.nodeType ) {
						return;
					}

					if ( node.matches && ( node.matches( '.notice' ) || node.matches( '.updated' ) || node.matches( '.error' ) ) ) {
						manageNotice( node );
					}

					if ( node.querySelectorAll ) {
						node.querySelectorAll( '.notice, .updated, .error' ).forEach( manageNotice );
					}
				} );
			} );
		} );

		observer.observe( document.getElementById( 'wpbody-content' ) || parent, {
			childList: true,
			subtree: true
		} );
	} );
}() );
