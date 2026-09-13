/**
 * GrabWP shared admin UI — in-page tabs.
 *
 * Markup:
 *   <nav|h2 class="nav-tab-wrapper grabwp-tabs" data-grabwp-tabs-store="unique-key">
 *     <a class="nav-tab" href="#panel-id">…</a>
 *   </nav>
 *   <div class="tab-panels-wrap">  <!-- or sibling panels -->
 *     <div id="panel-id" class="grabwp-tab-panel">…</div>
 *   </div>
 *
 * Each tab bar persists its active href in localStorage. Keys must be unique
 * per bar: set data-grabwp-tabs-store, or the script derives
 * grabwp_tabs:{page}:{id|index} so two admin pages never share state.
 *
 * URL tabs (WaaS settings, status, tenant action nav) stay as real links —
 * do not add grabwp-tabs to those bars.
 *
 * @package GrabWP_Tenancy
 */
(function () {
	'use strict';

	function adminPageSlug() {
		try {
			return new URLSearchParams( window.location.search ).get( 'page' ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function storeKey( nav, index ) {
		var explicit = nav.getAttribute( 'data-grabwp-tabs-store' );
		if ( explicit ) {
			return explicit;
		}
		return 'grabwp_tabs:' + adminPageSlug() + ':' + ( nav.id || String( index ) );
	}

	function storageGet( key ) {
		try {
			return localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function storageSet( key, value ) {
		try {
			localStorage.setItem( key, value );
		} catch ( e ) {
			// Private mode / quota — persistence is optional.
		}
	}

	function panelsFor( nav ) {
		var el = nav.nextElementSibling;
		while ( el && ! el.classList.contains( 'tab-panels-wrap' ) && ! el.classList.contains( 'grabwp-tab-panel' ) ) {
			el = el.nextElementSibling;
		}
		if ( ! el ) {
			return [];
		}
		if ( el.classList.contains( 'tab-panels-wrap' ) ) {
			return Array.prototype.slice.call( el.querySelectorAll( '.grabwp-tab-panel' ) );
		}
		var panels = [];
		while ( el && el.classList.contains( 'grabwp-tab-panel' ) ) {
			panels.push( el );
			el = el.nextElementSibling;
		}
		return panels;
	}

	function tabByHref( nav, hash ) {
		var tabs = nav.querySelectorAll( 'a.nav-tab' );
		for ( var i = 0; i < tabs.length; i++ ) {
			if ( tabs[ i ].getAttribute( 'href' ) === hash ) {
				return tabs[ i ];
			}
		}
		return null;
	}

	function activate( nav, hash, key ) {
		if ( ! tabByHref( nav, hash ) ) {
			return;
		}
		var tabs = nav.querySelectorAll( 'a.nav-tab' );
		for ( var t = 0; t < tabs.length; t++ ) {
			tabs[ t ].classList.toggle( 'nav-tab-active', tabs[ t ].getAttribute( 'href' ) === hash );
		}
		var panels = panelsFor( nav );
		for ( var p = 0; p < panels.length; p++ ) {
			var on = ( '#' + panels[ p ].id ) === hash;
			panels[ p ].classList.toggle( 'grabwp-hidden', ! on );
			if ( on ) {
				panels[ p ].style.display = '';
			}
		}
		storageSet( key, hash );
	}

	function initNav( nav, index ) {
		var key = storeKey( nav, index );
		var saved = storageGet( key );
		var start = ( saved && tabByHref( nav, saved ) ) ? saved : null;
		if ( ! start ) {
			var current = nav.querySelector( 'a.nav-tab.nav-tab-active' );
			start = current ? current.getAttribute( 'href' ) : null;
		}
		if ( start ) {
			activate( nav, start, key );
		}

		nav.addEventListener( 'click', function ( e ) {
			var tab = e.target.closest( 'a.nav-tab' );
			if ( ! tab || ! nav.contains( tab ) ) {
				return;
			}
			var href = tab.getAttribute( 'href' );
			if ( ! href || href.charAt( 0 ) !== '#' ) {
				return;
			}
			e.preventDefault();
			activate( nav, href, key );
		} );
	}

	function boot() {
		var navs = document.querySelectorAll( '.grabwp-tabs' );
		for ( var i = 0; i < navs.length; i++ ) {
			initNav( navs[ i ], i );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
