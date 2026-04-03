/**
 * vendors-map.js
 *
 * Initialises one Mapbox GL map per [vendors_map] shortcode instance.
 *
 * Data flow (set up by vendors-map-shortcode.php via wp_add_inline_script):
 *   window.tmVendorsMapInstances  -- Array of instance config objects, each:
 *   {
 *     mapId:   string   -- id of the <div> to mount the map into
 *     token:   string   -- Mapbox access token
 *     vendors: Array    -- vendor marker data
 *       { name, url, lat, lng, address, avatar }
 *   }
 *
 * The inline data script runs with position='before', so this array is fully
 * populated before this file executes.
 */

/* global mapboxgl */
(function () {
	'use strict';

	if ( typeof mapboxgl === 'undefined' ) {
		return; // Mapbox GL JS not available
	}

	var instances = window.tmVendorsMapInstances;
	if ( ! instances || ! instances.length ) {
		return; // No shortcode instances on this page
	}

	/** Layers to remove for a clean, minimal dark basemap */
	var DROP_LAYERS = /building|poi|transit|housenumber|airport|rail|ferry|road-label/;

	// -- Debug helper ----------------------------------------------------------
	// Open browser DevTools -> Console, then run:
	//   tmVendorsMapDebug()            -- print full coordinate table
	//   tmVendorsMapFixCoords(userId)  -- re-geocode one vendor from their stored address
	//   tmVendorsMapFixAll()           -- re-geocode every vendor that looks mismatched
	window.tmVendorsMapDebug = function () {
		instances.forEach( function ( instance, idx ) {
			console.group( '%c[vendors_map] instance #' + ( idx + 1 ) + '  (' + instance.vendors.length + ' vendors)', 'color:#D4AF37;font-weight:bold' );
			var rows = instance.vendors.map( function ( v ) {
				// Quick sanity: if |lat| < 1 and |lng| > 90 the pair is likely swapped
				var swapSuspect = Math.abs( v.lat ) < 1 && Math.abs( v.lng ) > 90;
				return {
					name:         v.name,
					address:      v.address || '(none)',
					lat:          v.lat,
					lng:          v.lng,
					swap_suspect: swapSuspect ? '[!] POSSIBLE SWAP' : '',
					mapbox_url:   'https://www.openstreetmap.org/?mlat=' + v.lat + '&mlon=' + v.lng + '#map=12/' + v.lat + '/' + v.lng,
					fix_command:  'tmVendorsMapFixCoords(' + v.id + ') // ' + v.name,
				};
			} );
			console.table( rows );
			console.groupEnd();
		} );
	};

	// Re-geocode a single vendor by their stored dokan_geo_address.
	// Usage: tmVendorsMapFixCoords(23)   <-- pass the user ID from the table above
	window.tmVendorsMapFixCoords = function ( userId ) {
		return fetch( window.tmVendorsMapAjax.ajaxurl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    'action=tm_fix_vendor_geocoords&nonce=' + window.tmVendorsMapAjax.nonce + '&user_id=' + userId,
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			if ( data.success ) {
				console.log(
					'%c[OK] Fixed: ' + data.data.address,
					'color:#4caf50;font-weight:bold',
					'\nPlace: '   + data.data.place_name,
					'\nNew lat:'  + data.data.new_lat,
					'New lng:'   + data.data.new_lng
				);
			} else {
				console.error( '[ERR] Fix failed:', data.data );
			}
			return data;
		} );
	};

	// Re-geocode ALL vendors in one go. Staggers requests 300 ms apart to avoid
	// hammering the Mapbox API. Reload the page when it completes.
	window.tmVendorsMapFixAll = function () {
		var all = [];
		instances.forEach( function ( inst ) {
			inst.vendors.forEach( function ( v ) { if ( v.id ) { all.push( v.id ); } } );
		} );
		console.log( '%cRe-geocoding ' + all.length + ' vendors...', 'color:#D4AF37' );
		all.reduce( function ( chain, id, i ) {
			return chain.then( function () {
				return new Promise( function ( resolve ) { setTimeout( resolve, 300 ); } )
					.then( function () { return window.tmVendorsMapFixCoords( id ); } );
			} );
		}, Promise.resolve() ).then( function () {
			console.log( '%c[OK] Done. Reload the page to see updated positions.', 'color:#4caf50;font-weight:bold' );
		} );
	};

	instances.forEach( function ( instance ) {
		initMap( instance );
	} );

	// -- Per-instance initialisation -------------------------------------------

	function initMap( instance ) {
		var mapId   = instance.mapId;
		var token   = instance.token;
		var vendors = instance.vendors;

		if ( ! mapId || ! token || ! vendors || ! vendors.length ) {
			return;
		}

		var container = document.getElementById( mapId );
		if ( ! container ) {
			return;
		}

		mapboxgl.accessToken = token;

		var map = new mapboxgl.Map( {
			container:         mapId,
			style:             'mapbox://styles/mapbox/dark-v11',
			center:            [ -95.7129, 37.0902 ], // continental US fallback
			zoom:              3,
			minZoom:           2,
			maxZoom:           14,
			pitch:             0,
			bearing:           0,
			renderWorldCopies: false,
		} );

		// -- Controls -------------------------------------------------------
		// Navigation only -- no fullscreen clutter on a full-page platform page.
		map.addControl( new mapboxgl.NavigationControl( { showCompass: false } ), 'top-right' );

		// - Interaction controls -
		// Keep only the interactions we want; disable the rest.
		//
		// ENABLED:
		//   scrollZoom  -- laptop/desktop: scroll up = zoom in, scroll down = zoom out
		//   keyboard    -- Smart TV remote: built-in Mapbox handler maps +/= -> zoom in,
		//                  - -> zoom out, arrow keys -> pan
		//   touchZoomRotate (zoom only) -- tablet/phone: 2-finger spread = zoom in,
		//                                  2-finger pinch = zoom out
		//
		// DISABLED:
		//   boxZoom, dragRotate, doubleClickZoom, touchPitch  -- unnecessary clutter
		map.boxZoom.disable();
		map.dragRotate.disable();
		map.doubleClickZoom.disable();
		if ( map.touchZoomRotate && map.touchZoomRotate.disableRotation ) {
			map.touchZoomRotate.disableRotation(); // keep pinch-zoom, remove rotation
		}
		if ( map.touchPitch ) {
			map.touchPitch.disable();
		}

		// Smart TV remotes that send PageUp / PageDown (Samsung, LG, etc.)
		// The map container already has tabindex="-1" via Mapbox so it receives focus.
		map.getContainer().addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'PageUp'   ) { map.zoomIn();  e.preventDefault(); }
			if ( e.key === 'PageDown' ) { map.zoomOut(); e.preventDefault(); }
		} );

		// -- Pre-calculate bounds (synchronous, before map loads) ---------------
		var bounds = new mapboxgl.LngLatBounds();
		vendors.forEach( function ( v ) { bounds.extend( [ v.lng, v.lat ] ); } );

		// -- GeoJSON FeatureCollection -------------------------------------------
		// Integer 'id' on each Feature is required for feature-state hover.
		// 'cats' is a comma-separated list of store_category slugs for client-side filtering.
		var geojson = {
			type: 'FeatureCollection',
			features: vendors.map( function ( v, i ) {
				return {
					type:       'Feature',
					id:         i,
					geometry:   { type: 'Point', coordinates: [ v.lng, v.lat ] },
					properties: {
						name:       v.name,
						url:        v.url,
						address:    v.address || '',
						country:    v.country || '',
						avatar:     v.avatar,
						cats:       v.cats || '',
						vendorId:   v.id,
						registered: v.registered || 0,
					},
				};
			} ),
		};

		// Keep a permanent copy of ALL features so the category panel can restore
		// the full set after a filter is cleared.
		var allFeatures = geojson.features.slice();

		// Unique source/layer IDs per instance (safe when shortcode used multiple times)
		var srcId     = mapId + '-src';
		var haloId    = mapId + '-halo';
		var circlesId = mapId + '-circles';

		// -- Everything inside map.once('load') ----------------------------------
		// Source + circle layers render on the WebGL canvas -- same GPU frame as
		// the map tiles. No DOM elements, no CSS transforms, zero pan/zoom lag.
		map.once( 'load', function () {

			// Strip noisy layers for a clean minimal dark basemap
			( map.getStyle().layers || [] ).forEach( function ( layer ) {
				if ( layer && layer.id && DROP_LAYERS.test( layer.id ) ) {
					try { map.removeLayer( layer.id ); } catch ( e ) { /* ignore */ }
				}
			} );

			// Register the vendor GeoJSON source
			map.addSource( srcId, { type: 'geojson', data: geojson } );

			// Subtle outer glow ring behind each dot
			map.addLayer( {
				id:     haloId,
				type:   'circle',
				source: srcId,
				paint:  {
					'circle-radius':  17,
					'circle-color':   '#D4AF37',
					'circle-opacity': 0.18,
				},
			} );

			// Main gold dot -- radius grows on hover via feature-state
			map.addLayer( {
				id:     circlesId,
				type:   'circle',
				source: srcId,
				paint:  {
					'circle-radius': [
						'case',
						[ 'boolean', [ 'feature-state', 'hover' ], false ],
						13,  // hovered
						9,   // normal
					],
					'circle-color':        '#D4AF37',
					'circle-stroke-width': 3,
					'circle-stroke-color': 'rgba(255,255,255,0.85)',
				},
			} );

			// -- Hover interaction -----------------------------------------------
			var hoveredId = null;

			map.on( 'mouseenter', circlesId, function () {
				map.getCanvas().style.cursor = 'pointer';
			} );

			map.on( 'mousemove', circlesId, function ( e ) {
				if ( ! e.features.length ) { return; }
				if ( hoveredId !== null ) {
					map.setFeatureState( { source: srcId, id: hoveredId }, { hover: false } );
				}
				hoveredId = e.features[ 0 ].id;
				map.setFeatureState( { source: srcId, id: hoveredId }, { hover: true } );
			} );

			map.on( 'mouseleave', circlesId, function () {
				if ( hoveredId !== null ) {
					map.setFeatureState( { source: srcId, id: hoveredId }, { hover: false } );
					hoveredId = null;
				}
				map.getCanvas().style.cursor = '';
			} );

			// -- Click -> popup -------------------------------------------------
			map.on( 'click', circlesId, function ( e ) {
				var props  = e.features[ 0 ].properties;
				var coords = e.features[ 0 ].geometry.coordinates.slice();

				// Normalize longitude when renderWorldCopies=false wraps coordinates
				while ( Math.abs( e.lngLat.lng - coords[ 0 ] ) > 180 ) {
					coords[ 0 ] += e.lngLat.lng > coords[ 0 ] ? 360 : -360;
				}

				var addrHTML = props.address
					? '<p class="tm-vmap-popup__address">' + escapeHtml( props.address ) + '</p>'
					: '';

				new mapboxgl.Popup( { offset: 14, maxWidth: '240px' } )
					.setLngLat( coords )
					.setHTML(
						'<div class="tm-vmap-popup">'
						+ '<img class="tm-vmap-popup__avatar" src="' + props.avatar + '" alt="" />'
						+ '<h3 class="tm-vmap-popup__name">' + escapeHtml( props.name ) + '</h3>'
						+ addrHTML
						+ '<a class="tm-vmap-popup__btn" href="' + props.url + '">View Profile</a>'
						+ '</div>'
					)
					.addTo( map );
			} );

			// -- Auto-fit to all markers -----------------------------------------
			if ( ! bounds.isEmpty() ) {
				map.fitBounds( bounds, { padding: 60, maxZoom: 8, duration: 800 } );
			}

			// -- Category filter panel -------------------------------------------
			// Builds the left-strip panel and wires up click-to-filter on map source.
			buildCategoryPanel( instance.categories || [] );
		} );

		// ---- Category panel (inner function: closes over map, srcId, allFeatures) ----
		function buildCategoryPanel( categories ) {
			var panelEl = document.getElementById( mapId + '-cat-panel' );
			if ( ! panelEl ) { return; }

			var activeSlug    = null;
			var activeCountry = null;
			var activeSortKey = 'newest';

			var SORT_OPTIONS = [
				{ value: 'newest',  label: 'Newest first'  },
				{ value: 'oldest',  label: 'Oldest first'  },
				{ value: 'name_az', label: 'Name A \u2192 Z' },
				{ value: 'name_za', label: 'Name Z \u2192 A' },
			];

			/** Return the currently-filtered feature set (respects activeSlug + activeCountry). */
			function filteredFeatures() {
				return allFeatures.filter( function ( f ) {
					var catOk = ! activeSlug ||
						( f.properties.cats || '' ).split( ',' ).indexOf( activeSlug ) !== -1;
					var countryOk = ! activeCountry ||
						( f.properties.country || '' ) === activeCountry;
					return catOk && countryOk;
				} );
			}

			/** Sort a feature array and return the vendor IDs in that order. */
			function sortedVendorIds( features, sortKey ) {
				var sorted = features.slice();
				switch ( sortKey ) {
					case 'oldest':
						sorted.sort( function ( a, b ) { return ( a.properties.registered || 0 ) - ( b.properties.registered || 0 ); } );
						break;
					case 'name_az':
						sorted.sort( function ( a, b ) { return a.properties.name.localeCompare( b.properties.name ); } );
						break;
					case 'name_za':
						sorted.sort( function ( a, b ) { return b.properties.name.localeCompare( a.properties.name ); } );
						break;
					default: // newest
						sorted.sort( function ( a, b ) { return ( b.properties.registered || 0 ) - ( a.properties.registered || 0 ); } );
				}
				return sorted.map( function ( f ) { return f.properties.vendorId; } );
			}

			/** Rebuild hrefs + counts on both action buttons. */
			function updatePanelBtns() {
				var visible = filteredFeatures();
				var ids     = sortedVendorIds( visible, activeSortKey );
				var count   = ids.length;

				// Showcase button
				var scBtn = panelEl.querySelector( '.tm-vmap-showcase-btn' );
				if ( scBtn ) {
					scBtn.href = ( instance.showcaseUrl || '/showcase/' ) + '?tm_ids=' + ids.join( ',' ) + '&tm_order=' + activeSortKey;
					var scCount = scBtn.querySelector( '.tm-vmap-sc-count' );
					if ( scCount ) { scCount.textContent = count; }
				}

				// Filter button
				var fBtn = panelEl.querySelector( '.tm-vmap-filter-btn' );
				if ( fBtn ) {
					var fParams = new URLSearchParams();
					if ( activeSlug )   { fParams.set( 'ecomcine_person_category', activeSlug ); }
					if ( activeCountry ) { fParams.set( 'country', activeCountry ); }
					fParams.set( 'tm_order', activeSortKey );
					fBtn.href = ( instance.talentsUrl || '/talents/' ) + '?' + fParams.toString();
					var fCount = fBtn.querySelector( '.tm-vmap-sc-count' );
					if ( fCount ) { fCount.textContent = count; }
				}
			}

			// ── Build HTML ──────────────────────────────────────────────────────
			var html = '';

			// Country dropdown (only shown when 2+ distinct countries exist)
			var countrySet = {};
			allFeatures.forEach( function ( f ) {
				var c = f.properties.country;
				if ( c ) { countrySet[ c ] = true; }
			} );
			var countryList = Object.keys( countrySet ).sort();
			if ( countryList.length >= 2 ) {
				html += '<p class="tm-vmap-cat-panel__title">Filter by country</p>'
					+ '<select class="tm-vmap-country-select">'
					+ '<option value="">All countries</option>';
				countryList.forEach( function ( c ) {
					html += '<option value="' + escapeHtml( c ) + '">' + escapeHtml( c ) + '</option>';
				} );
				html += '</select>';
			}

			// Category section (only shown when there are categories)
			if ( categories && categories.length ) {
				html += '<p class="tm-vmap-cat-panel__title">Filter by category</p>'
					+ '<div class="tm-vmap-cats">';
				html += '<button class="tm-vmap-cat-btn is-all is-active" data-slug="">All</button>';
				categories.forEach( function ( cat ) {
					html += '<button class="tm-vmap-cat-btn" data-slug="'
						+ escapeHtml( cat.slug ) + '">' + escapeHtml( cat.name ) + '</button>';
				} );
				html += '</div>';
			}

			// Sort section
			html += '<p class="tm-vmap-cat-panel__title tm-vmap-sort-title">Sort by</p>'
				+ '<ul class="tm-vmap-sort-list">';
			SORT_OPTIONS.forEach( function ( opt ) {
				var cls = opt.value === activeSortKey ? ' is-active' : '';
				html += '<li class="tm-vmap-sort-item' + cls + '" data-sort="' + opt.value + '">'
					+ escapeHtml( opt.label ) + '</li>';
			} );
			html += '</ul>';

			// Showcase + Filter buttons — initial state uses all vendors sorted by activeSortKey
			var initialIds    = sortedVendorIds( allFeatures, activeSortKey );
			var initialCount  = initialIds.length;
			var initialFParams = new URLSearchParams();
			initialFParams.set( 'tm_order', activeSortKey );

			html += '<a class="tm-vmap-showcase-btn"'
				+ ' href="' + escapeHtml( ( instance.showcaseUrl || '/showcase/' ) + '?tm_ids=' + initialIds.join( ',' ) + '&tm_order=' + activeSortKey ) + '">'
				+ '&#9654;&#8201;Showcase '
				+ '<span class="tm-vmap-sc-count">' + initialCount + '</span>'
				+ ' talents</a>';

			html += '<a class="tm-vmap-filter-btn"'
				+ ' href="' + escapeHtml( ( instance.talentsUrl || '/talents/' ) + '?' + initialFParams.toString() ) + '">'
				+ '&#9783;&#8201;Filter '
				+ '<span class="tm-vmap-sc-count">' + initialCount + '</span>'
				+ ' talents</a>';

			panelEl.innerHTML = html;

			// ── Country select listener ──────────────────────────────────────────
			var countrySelect = panelEl.querySelector( '.tm-vmap-country-select' );
			if ( countrySelect ) {
				countrySelect.addEventListener( 'change', function () {
					activeCountry = this.value || null;
					var filtered = filteredFeatures();
					map.getSource( srcId ).setData( { type: 'FeatureCollection', features: filtered } );
					if ( filtered.length ) {
						var sumLng = 0, sumLat = 0;
						filtered.forEach( function ( f ) {
							sumLng += f.geometry.coordinates[0];
							sumLat += f.geometry.coordinates[1];
						} );
						map.easeTo( { center: [ sumLng / filtered.length, sumLat / filtered.length ], duration: 600 } );
					}
					updatePanelBtns();
				} );
			}

			// ── Single delegated listener ────────────────────────────────────────
			panelEl.addEventListener( 'click', function ( e ) {

				// ── Category button ─────────────────────────────────────────────
				var catBtn = e.target.closest( '.tm-vmap-cat-btn' );
				if ( catBtn ) {
					var slug = catBtn.getAttribute( 'data-slug' );
					if ( slug === activeSlug ) { return; }
					activeSlug = slug;

					panelEl.querySelectorAll( '.tm-vmap-cat-btn' ).forEach( function ( b ) {
						b.classList.remove( 'is-active' );
					} );
					catBtn.classList.add( 'is-active' );

					var filtered = filteredFeatures();
					map.getSource( srcId ).setData( { type: 'FeatureCollection', features: filtered } );

				// Pan to the centroid of the filtered markers — no zoom change.
				if ( filtered.length ) {
					var sumLng = 0, sumLat = 0;
					filtered.forEach( function ( f ) {
						sumLng += f.geometry.coordinates[0];
						sumLat += f.geometry.coordinates[1];
					} );
					map.easeTo( {
						center:   [ sumLng / filtered.length, sumLat / filtered.length ],
						duration: 600,
					} );
					}
					updatePanelBtns();
					return;
				}

				// ── Sort item ────────────────────────────────────────────────────
				var sortItem = e.target.closest( '.tm-vmap-sort-item' );
				if ( sortItem ) {
					var sortKey = sortItem.getAttribute( 'data-sort' );
					if ( sortKey === activeSortKey ) { return; }
					activeSortKey = sortKey;

					panelEl.querySelectorAll( '.tm-vmap-sort-item' ).forEach( function ( li ) {
						li.classList.remove( 'is-active' );
					} );
					sortItem.classList.add( 'is-active' );
					updatePanelBtns();
				}
			} );
		}
	}

	/** Minimal HTML escaping for values injected into popup innerHTML */
	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g,  '&amp;' )
			.replace( /</g,  '&lt;' )
			.replace( />/g,  '&gt;' )
			.replace( /"/g,  '&quot;' )
			.replace( /'/g,  '&#39;' );
	}

} )();