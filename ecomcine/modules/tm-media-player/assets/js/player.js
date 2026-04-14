/**
 * Vendor Store Pages - Consolidated JavaScript
 * Handles: Profile panel collapse, drawer tabs, hero media player, and interactions
 * Architecture: Global state + reusable functions + initHeroPlayer for DOM rebinding
 */

// Prevent multiple initializations - only run initHeroPlayer once per page load
var tmPlayerInitialized = false;

jQuery(document).ready(function($) {
	var hasCompletedInitialHeroInit = false;

	function wasDocumentReloaded() {
		try {
			if (window.performance && typeof window.performance.getEntriesByType === "function") {
				var navigationEntries = window.performance.getEntriesByType("navigation");
				if (navigationEntries && navigationEntries.length && navigationEntries[0] && navigationEntries[0].type) {
					return navigationEntries[0].type === "reload";
				}
			}
			return !!(window.performance && window.performance.navigation && window.performance.navigation.type === 1);
		} catch (e) {
			return false;
		}
	}
	
	// ==========================================
	// MODULE-SCOPE STATE & HELPERS
	// These are always available to all functions
	// ==========================================
	
	var state = {
		isPlaying: true,
		muted: false,
		index: 0,
		timer: null,
		type: null,
		fullDuration: false,
		loopMode: false
	};

	var playlist = [];
	var remoteHideTimeout;
	var STORAGE_KEYS = {
		collapsed: "tm_profile_collapsed",
		muted: "tm_hero_muted",
		fullDuration: "tm_hero_full_duration",
		loopMode: "tm_hero_loop_mode",
		vendorLoop: "tm_vendor_loop"
	};
	var vendorLoopEnabled = readStoredBool(STORAGE_KEYS.vendorLoop, false);
	// Capture playerMode ONCE at module load time so isShowcaseMode() always
	// returns the correct answer even after window.vendorStoreData is overwritten
	// by a second wp_localize_script call (e.g. vendor-store-js from the theme).
	// window.tmPlayerMode is set by wp_add_inline_script(...,'before') in PHP and
	// is NEVER overwritten by anything — it is the authoritative source of truth.
	var _playerMode = window.tmPlayerMode
		? window.tmPlayerMode
		: ((window.vendorStoreData && vendorStoreData.playerMode)
			? vendorStoreData.playerMode
			: "default");
	console.log('[TM PLAYER v2.0.2] _playerMode:', _playerMode, '| window.tmPlayerMode:', window.tmPlayerMode, '| vendorStoreData.playerMode:', window.vendorStoreData ? window.vendorStoreData.playerMode : 'NO vendorStoreData');
	var defaultVendorAvatarSrc = (window.vendorStoreData && vendorStoreData.defaultAvatarUrl)
		? vendorStoreData.defaultAvatarUrl
		: "https://marketplace.castingagency.co/talent_avatar_250x250.webp";
	var isTheatreMode = false;
	var fullscreenListenerBound = false;
	var onboardingMode = !!(window.vendorStoreData && vendorStoreData.isOnboardingPage);
	var editSuspendState = {
		count: 0,
		wasPlaying: false,
		vendorLoopEnabled: null
	};
	var showcaseInteractionPause = false;
	// ── Advance pipeline state ──────────────────────────────────────────────────
	// Single advance timer replaces the old preBlackoutTimer/minTimer/maxTimer trio.
	// advanceBlackoutTimer is purely cosmetic: applies the CSS blackout class just
	// before loadItem fires so the cut looks intentional, then removes it 250 ms
	// later. It has NO gating role — no code checks it before proceeding.
	var advanceTimer = null;           // setTimeout handle: fires loadNext()
	var advanceBlackoutTimer = null;   // setTimeout handle: removes blackout CSS
	var advanceItemSrc = "";           // src of item the current timer was armed for
	// ────────────────────────────────────────────────────────────────────────────
	var transitionHoldUntil = 0;
	var transitionHoldTimer = null;
	var panelHoldUntil = 0;
	var panelHoldTimer = null;
	var transitionTimers = {
		collapse: null,
		blackout: null,
		swap: null,
		blackoutRemove: null,
		expand: null
	};
	var collapseAvatarTimer = null;
	var vendorPrefetch = {
		index: null,
		xhr: null,
		data: null
	};
	var mediaPrefetch = {
		index: null,
		type: null,
		src: null
	};
	var warmupVideo = null;
	var warmupVideoSrc = "";
	var warmupVideoTimer = null;
	var warmupAudio = null;
	var warmupAudioSrc = "";
	// A/B double-buffer for video: swaps happen on a pre-loaded element so play()
	// fires with no src/load operation — keeping the browser activation token intact.
	var heroVideoActive = null;   // DOM element: currently visible video slot
	var videoBufferNext = null;   // DOM element: preloading the next item
	var videoBufferPrev = null;   // DOM element: preloading the prev item
	
	// OPTIMIZATION: Loading indicator for slow connections
	var loadingIndicator = null;
	var loadingIndicatorTimer = null;
	var bufferNextSrc  = "";      // src currently loading/loaded in videoBufferNext
	var bufferPrevSrc  = "";      // src currently loading/loaded in videoBufferPrev
	// Smart TV loop-blob cache: when the user enables "Loop this media" on a Smart TV,
	// we fetch the file once into a Blob URL so currentTime=0+play() is always instant.
	var smartTvLoopBlobUrl  = null; // Object URL currently held for loop mode
	var smartTvLoopOrigSrc  = null; // Original HTTP src to restore when loop is turned off
	var smartTvLoopFetchCtrl = null; // AbortController for in-flight fetch (cancelled on release)
	var VIDEO_LOOP_SECONDS = 12;
	var FEATURED_SWAP_SECONDS = 60; // Featured vendors get up to 60 s in showcase before talent swap
	var VENDOR_CONTENT_CACHE_KEY = "tm_vendor_content_cache_v1_";
	var VENDOR_CONTENT_CACHE_MAX_AGE_MS = 12 * 60 * 60 * 1000;

	// YouTube IFrame API state
	var ytPlayer           = null;   // YT.Player instance
	var ytApiLoading       = false;
	var ytPendingId        = null;   // video ID waiting for API to become ready
	var ytCurrentId        = null;   // the videoId most recently loaded
	var ytProgressInterval  = null;   // setInterval handle for progress-bar polling
	var ytSeeking           = false;  // true while user is dragging the seek slider
	var ytSeekResumeTimeout = null;   // setTimeout to restart polling after a seek
	// YouTube end-screens appear in the last ~20 seconds of a video and cannot be
	// disabled via API params. For cinematic showcase mode we auto-advance when
	// this many seconds remain, so end-screen recommendation cards never appear.
	var YT_ENDSCREEN_SKIP_THRESHOLD = 20; // seconds
	// Chain onYouTubeIframeAPIReady so we don't clobber any earlier registration.
	(function() {
		var _prev = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function() {
			if (typeof _prev === "function") { _prev(); }
			ytApiLoading = false;
			if (ytPendingId) {
				var id = ytPendingId;
				ytPendingId = null;
				initYTPlayer(id);
			}
		};
	})();

	// ── URL parameter configuration ───────────────────────────────────────────
	// Params: ?tm_ids=4,16,17 | ?minduration=12 | ?talentloop=on|off | ?medialoop=on|off
	// URL params are read once at module load and override ALL other config sources
	// (localStorage, vendorStoreData, window.tmPlayerMode, etc.).
	var urlConfig = (function() {
		var cfg = {};
		try {
			var search = window.location.search;
			if (!search) return cfg;
			var params = {};
			search.slice(1).split('&').forEach(function(part) {
				if (!part) return;
				var eq = part.indexOf('=');
				var k = eq >= 0 ? part.slice(0, eq) : part;
				var v = eq >= 0 ? part.slice(eq + 1) : '';
				try { k = decodeURIComponent(k.replace(/\+/g, ' ')); } catch(e) {}
				try { v = decodeURIComponent(v.replace(/\+/g, ' ')); } catch(e) {}
				params[k] = v;
			});
			// tm_ids: comma-separated vendor IDs — filters the showcase to a specific set
			if (params['tm_ids']) {
				var ids = params['tm_ids'].split(',').map(function(s) { return parseInt(s.trim(), 10); }).filter(function(n) { return n > 0; });
				if (ids.length) { window.tmShowcaseIds = ids; cfg.tmIds = ids; }
			}
			// minduration: per-clip playback cap in seconds (overrides VIDEO_LOOP_SECONDS)
			if (params['minduration']) {
				var d = parseFloat(params['minduration']);
				if (d > 0) { VIDEO_LOOP_SECONDS = d; cfg.minDuration = d; }
			}
			// talentloop: on (default) | off — stop after the last vendor instead of looping
			if (params['talentloop'] !== undefined) {
				cfg.talentloop = (params['talentloop'].toLowerCase() !== 'off');
			}
			// medialoop: off (default) | on — loop the vendor's media playlist without advancing
			if (params['medialoop'] !== undefined) {
				cfg.medialoop = (params['medialoop'].toLowerCase() === 'on');
			}
			if (Object.keys(cfg).length) {
				console.log('[TM urlConfig]', JSON.stringify(cfg));
			}
		} catch(e) {}
		return cfg;
	}());

	// ── Global BOM fix ────────────────────────────────────────────────────────
	// Some PHP template files are saved with a UTF-8 BOM (\uFEFF). When that BOM
	// ends up in a JSON response, jQuery's default JSON parser throws a SyntaxError
	// and routes to the error callback even though the HTTP status is 200 and the
	// body is valid JSON. Override the 'text json' converter globally to strip it.
	$.ajaxSetup({
		converters: {
			'text json': function(text) {
				if (typeof text === 'string') { text = text.replace(/^\uFEFF+/, ''); }
				return JSON.parse(text);
			}
		}
	});

	function isShowcaseMode() {
		return _playerMode === "showcase";
	}
	function isCurrentVendorFeatured() {
		return !!(window.vendorMedia && window.vendorMedia.isFeatured);
	}
	// Detect Smart TV browsers (Samsung Tizen, LG WebOS, HbbTV, Android TV, etc.).
	// These devices enforce a hardware decoder limit (typically 1–2 simultaneous <video>
	// elements) so the A/B buffer system must be disabled for them.
	function isSmartTV() {
		var ua = navigator.userAgent || "";
		return /SmartTV|SMART-TV|Tizen|Web0S|webOS|WEBOS|HbbTV|Android\s*TV|GoogleTV|CrKey/i.test(ua);
	}
	// Detect real mobile/tablet hardware (UA + touch), regardless of viewport size.
	// Separate from isHandheldViewport() which is viewport-dimension only.
	// Used to reduce decoder contention on devices with shared hardware video decoders.
	var _isMobileDeviceCached = null;
	function isMobileDevice() {
		if (_isMobileDeviceCached !== null) return _isMobileDeviceCached;
		var ua = navigator.userAgent || "";
		// UA check: actual mobile/tablet OS strings (Android, iOS, etc.)
		var uaMatch = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i.test(ua);
		// Media query check: (pointer:coarse) AND (hover:none) matches phones and tablets
		// that have ONLY touch input. It does NOT match touchscreen laptops or the Surface
		// Studio family because those devices also have a mouse/pen (hover:hover, pointer:fine).
		// Using maxTouchPoints > 1 alone was wrong — Surface Studio has maxTouchPoints=10
		// but is a full desktop machine with no decoder contention or activation limits.
		var coarseNoHover = !!(window.matchMedia &&
			window.matchMedia("(pointer:coarse) and (hover:none)").matches);
		_isMobileDeviceCached = uaMatch || coarseNoHover;
		return _isMobileDeviceCached;
	}
	// Opt 3 (Smart TV): compare two video src values after normalising to absolute URLs.
	// el.src always returns the resolved absolute URL; item.src may be relative or a CDN
	// path, causing a naive string comparison to report a false difference every time.
	function tmSameSrc(elSrc, itemSrc) {
		if (!elSrc || !itemSrc) return false;
		if (elSrc === itemSrc) return true;
		try {
			var _a = new URL(elSrc, window.location.href);
			var _b = new URL(itemSrc, window.location.href);
			return _a.host === _b.host && _a.pathname === _b.pathname;
		} catch(e) {
			return elSrc.indexOf(itemSrc) !== -1 || itemSrc.indexOf(elSrc) !== -1;
		}
	}

	function readVendorContentCache(vendorId) {
		try {
			if (!window.localStorage) return null;
			var raw = localStorage.getItem(VENDOR_CONTENT_CACHE_KEY + String(vendorId));
			if (!raw) return null;
			var parsed = JSON.parse(raw);
			if (!parsed || !parsed.savedAt || !parsed.payload) return null;
			if (Date.now() - parsed.savedAt > VENDOR_CONTENT_CACHE_MAX_AGE_MS) return null;
			return parsed.payload;
		} catch (e) {
			return null;
		}
	}

	function writeVendorContentCache(vendorId, payload) {
		try {
			if (!window.localStorage) return;
			if (!vendorId || !payload) return;
			localStorage.setItem(VENDOR_CONTENT_CACHE_KEY + String(vendorId), JSON.stringify({
				savedAt: Date.now(),
				payload: payload
			}));
		} catch (e) {}
	}
	
	function initBiographyLightbox() {
		if (!$("#vendor-biography-section").length) {
			return;
		}

		if (!$("#mp-lightbox").length) {
			$("body").append('<div id="mp-lightbox" class="mp-lightbox-overlay"><div class="mp-lightbox-content"><button class="mp-lightbox-close" aria-label="Close">&times;</button><button class="mp-lightbox-prev" aria-label="Previous">&lsaquo;</button><button class="mp-lightbox-next" aria-label="Next">&rsaquo;</button><img src="" alt=""></div></div>');
		}

		var $lightbox = $("#mp-lightbox");
		var $lightboxImg = $lightbox.find("img");
		var galleryImages = [];
		var currentIndex = 0;

		function getFullImageUrl(thumbnailSrc) {
			return thumbnailSrc.replace(/-\d+x\d+\.(jpg|jpeg|png|gif|webp)$/i, '.$1');
		}

		function initGallery() {
			galleryImages = [];
			$("#vendor-biography-section .gallery .gallery-item a").each(function() {
				var $img = $(this).find("img");
				if ($img.length) {
					var thumbnailSrc = $img.attr("src");
					var fullSrc = getFullImageUrl(thumbnailSrc);
					galleryImages.push({
						url: fullSrc,
						alt: $img.attr("alt") || ""
					});
				}
			});
		}

		function openLightbox(index) {
			currentIndex = index;
			$lightboxImg.attr("src", galleryImages[currentIndex].url);
			$lightboxImg.attr("alt", galleryImages[currentIndex].alt);
			$lightbox.addClass("active");
			$("body").css("overflow", "hidden");
			$(".mp-lightbox-prev").toggle(galleryImages.length > 1);
			$(".mp-lightbox-next").toggle(galleryImages.length > 1);
		}

		function closeLightbox() {
			$lightbox.removeClass("active");
			$("body").css("overflow", "");
		}

		function showImage(index) {
			if (index < 0) index = galleryImages.length - 1;
			if (index >= galleryImages.length) index = 0;
			currentIndex = index;
			$lightboxImg.attr("src", galleryImages[currentIndex].url);
			$lightboxImg.attr("alt", galleryImages[currentIndex].alt);
		}

		initGallery();

		$(document).on("click", "#vendor-biography-section .gallery .gallery-item a", function(e) {
			e.preventDefault();
			var $img = $(this).find("img");
			if ($img.length) {
				var thumbnailSrc = $img.attr("src");
				var fullSrc = getFullImageUrl(thumbnailSrc);
				var index = galleryImages.findIndex(function(img) { return img.url === fullSrc; });
				if (index >= 0) {
					openLightbox(index);
				}
			}
		});

		$lightbox.on("click", ".mp-lightbox-close", function(e) {
			e.stopPropagation();
			closeLightbox();
		});

		$lightbox.on("click", function(e) {
			if ($(e.target).is(".mp-lightbox-overlay")) {
				closeLightbox();
			}
		});

		$lightbox.on("click", ".mp-lightbox-content", function(e) {
			e.stopPropagation();
		});

		$lightbox.on("click", ".mp-lightbox-prev", function(e) {
			e.stopPropagation();
			showImage(currentIndex - 1);
		});

		$lightbox.on("click", ".mp-lightbox-next", function(e) {
			e.stopPropagation();
			showImage(currentIndex + 1);
		});

		$(document).on("keydown", function(e) {
			if (!$lightbox.hasClass("active")) return;
			if (e.key === "Escape") {
				closeLightbox();
			} else if (e.key === "ArrowLeft") {
				showImage(currentIndex - 1);
			} else if (e.key === "ArrowRight") {
				showImage(currentIndex + 1);
			}
		});
	}

	function getFirstVideoSrc(vendorMedia) {
		if (!vendorMedia || !Array.isArray(vendorMedia.items)) return "";
		for (var i = 0; i < vendorMedia.items.length; i++) {
			var item = vendorMedia.items[i];
			if (item && item.type === "video" && item.src) {
				return item.src;
			}
		}
		return "";
	}

	function warmupVideoSource(src) {
		if (!src || warmupVideoSrc === src) return;
		if (isSmartTV()) return; // Avoid a 4th <video> element on decoder-limited devices
		warmupVideoSrc = src;
		if (!warmupVideo) {
			warmupVideo = document.createElement("video");
			warmupVideo.muted = true;
			warmupVideo.setAttribute("muted", "muted");
			warmupVideo.setAttribute("playsinline", "playsinline");
			warmupVideo.preload = "metadata";
			warmupVideo.style.position = "fixed";
			warmupVideo.style.top = "0";
			warmupVideo.style.left = "0";
			warmupVideo.style.width = "1px";
			warmupVideo.style.height = "1px";
			warmupVideo.style.opacity = "0";
			warmupVideo.style.pointerEvents = "none";
			document.body.appendChild(warmupVideo);
		}
		if (warmupVideoTimer) {
			clearTimeout(warmupVideoTimer);
			warmupVideoTimer = null;
		}
		try {
			warmupVideo.src = src;
			warmupVideo.load();
		} catch (e) {
			return;
		}
		var warmed = false;
		var finishWarmup = function() {
			if (warmed) return;
			warmed = true;
			try { warmupVideo.pause(); } catch (e) {}
		};
		warmupVideo.onplaying = finishWarmup;
		warmupVideo.oncanplay = finishWarmup;
		warmupVideoTimer = setTimeout(finishWarmup, 400);
		// Fix 3 (Mobile): skip attemptPlay on mobile — the touch gesture already
		// unlocked the audio context, so playing a hidden 4th video element only
		// steals decoder time from the active slot.
		if (!isMobileDevice()) {
			attemptPlay(warmupVideo, true);
		}
	}

	function warmupAudioSource(src) {
		if (!src || warmupAudioSrc === src) return;
		warmupAudioSrc = src;
		if (!warmupAudio) {
			warmupAudio = document.createElement("audio");
			warmupAudio.muted = true;
			warmupAudio.setAttribute("muted", "muted");
			warmupAudio.preload = "metadata";
			warmupAudio.style.position = "fixed";
			warmupAudio.style.top = "0";
			warmupAudio.style.left = "0";
			warmupAudio.style.width = "1px";
			warmupAudio.style.height = "1px";
			warmupAudio.style.opacity = "0";
			warmupAudio.style.pointerEvents = "none";
			document.body.appendChild(warmupAudio);
		}
		try {
			warmupAudio.src = src;
			warmupAudio.load();
		} catch (e) {}
	}

	// ==========================================
	// A/B VIDEO BUFFER SYSTEM
	// ==========================================

	function initVideoBuffers() {
		heroVideoActive = $(".hero-video-slot-a")[0] || $(".profile-banner-video")[0] || null;
		if (isSmartTV() || isMobileDevice()) {
			// Smart TV: hardware decoder limit of 1–2 simultaneous <video> elements.
			// Mobile: browser caps simultaneous media network streams at 1–2;
			//   explicit load() on buffer slots competes with the active slot’s download
			//   causing the active video to stall after 2–4 s of buffered data.
			//   Mobile Chrome also uses document-level activation tokens so the A/B
			//   buffer’s activation-preservation benefit is not needed on touch devices.
			videoBufferNext = null;
			videoBufferPrev = null;
			bufferNextSrc = "";
			bufferPrevSrc = "";
			return;
		}
		videoBufferNext = $(".hero-video-slot-b")[0] || null;
		videoBufferPrev = $(".hero-video-slot-c")[0] || null;
		bufferNextSrc = "";
		bufferPrevSrc = "";
		if (videoBufferNext) { videoBufferNext.muted = true; }
		if (videoBufferPrev) { videoBufferPrev.muted = true; }
	}

	// Make newEl visible on top of oldEl with a 120 ms crossfade, then hide oldEl.
	function setVideoSlotActive(newEl, oldEl) {
		if (!newEl) return;
		// Clear stale hero handlers from any slot before roles change. Hidden buffer
		// elements can finish loading or error after a swap; if they still carry the
		// previous active-slot handlers they can incorrectly flip overlay/play state.
		[heroVideoActive, videoBufferNext, videoBufferPrev].forEach(function(el) {
			if (el) {
				$(el).off("ended.tmhero loadedmetadata.tmhero playing.tmhero canplay.tmhero volumechange.tmhero error.tmhero");
			}
		});
		// Reset z-index on all known slots before swap
		[heroVideoActive, videoBufferNext, videoBufferPrev].forEach(function(el) {
			if (el) { $(el).css("zIndex", 1); }
		});
		$(newEl).css({ display: "block", zIndex: 2 });
		heroVideoActive = newEl;
		if (oldEl && oldEl !== newEl) {
			var capturedOld = oldEl;
			setTimeout(function() {
				$(capturedOld).css({ display: "none", zIndex: 1 });
			}, 120);
		}
	}

	// Load a src into a hidden buffer slot (always muted during preload).
	function preloadIntoBuffer(slotEl, src, item) {
		if (!slotEl || !src) return;
		$(slotEl).off("ended.tmhero loadedmetadata.tmhero playing.tmhero canplay.tmhero volumechange.tmhero error.tmhero");
		slotEl.muted = true;
		// Fix 2 (Mobile): use preload="metadata" so the browser keeps the src URL
		// ready without triggering background decode that competes with the active slot.
		// Desktop keeps "auto" for fastest possible swap from the A/B buffer.
		slotEl.preload = isMobileDevice() ? "metadata" : "auto";
		if (item && item.poster) {
			slotEl.setAttribute("poster", item.poster);
		} else {
			slotEl.removeAttribute("poster");
		}
		try {
			slotEl.src = src;
			slotEl.load();
		} catch (e) {}
	}

	// After loading item at currentIndex, preload next and prev video items into buffers.
	function scheduleBufferPreloads(currentIndex) {
		if (!playlist.length) return;
		// Single-element mode (Smart TV / mobile): no A/B buffer swaps.
		if (!videoBufferNext && !videoBufferPrev) {
			// Mobile (not SmartTV): no swap buffers, but we CAN pre-download the NEXT
			// video using the warmup element so it arrives in the HTTP cache before
			// it's needed. Without this, the browser starts a cold download exactly
			// when playback is requested, causing a 20-30 s stall on 4G for typical
			// video files (the "4th video buffers forever then works fine" pattern).
			//
			// Strategy: wait 3 s after the current video starts so it gets uncontested
			// bandwidth to reach a safe buffer depth, then download the next clip in
			// the background with preload="auto". When the current video ends and
			// loadItem() sets heroVideoActive.src, the browser finds it in HTTP cache
			// → instant start, no cold-download stall.
			if (isMobileDevice() && !isSmartTV()) {
				var nextMobileIdx = (currentIndex + 1) % playlist.length;
				var nextMobileItem = playlist[nextMobileIdx];
				if (nextMobileItem && nextMobileItem.type === "video" && nextMobileItem.src) {
					var mobilePrefetchSrc = nextMobileItem.src;
					setTimeout(function() {
						// Guard: if the user already moved to a different item, skip.
						if (state.index !== currentIndex) return;
						// Guard: already cached by a previous call.
						if (warmupVideoSrc === mobilePrefetchSrc) return;
						warmupVideoSrc = mobilePrefetchSrc;
						if (!warmupVideo) {
							warmupVideo = document.createElement("video");
							warmupVideo.muted = true;
							warmupVideo.setAttribute("muted", "muted");
							warmupVideo.setAttribute("playsinline", "playsinline");
							warmupVideo.style.cssText = "position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;pointer-events:none;";
							document.body.appendChild(warmupVideo);
						}
						// preload="auto" tells the browser to download the full file.
						// We do NOT call play() — background download is enough to fill
						// the HTTP cache without stealing the hardware decoder.
						warmupVideo.preload = "auto";
						warmupVideo.src = mobilePrefetchSrc;
						warmupVideo.load();
					}, 3000);
				}
			}
			return;
		}
		// Fix 4 (Mobile): delay buffer preloads so the active slot gets first uncontested
		// access to the hardware decoder. On desktop fire immediately for fastest swap.
		var delay = isMobileDevice() ? 500 : 0;
		var doPreload = function() {
			if (!playlist.length) return;
			var nextIdx = (currentIndex + 1) % playlist.length;
			var prevIdx = (currentIndex - 1 + playlist.length) % playlist.length;
			var nextItem = playlist[nextIdx];
			var prevItem = playlist[prevIdx];
			if (videoBufferNext && nextItem && nextItem.type === "video" && nextItem.src) {
				if (bufferNextSrc !== nextItem.src) {
					bufferNextSrc = nextItem.src;
					preloadIntoBuffer(videoBufferNext, nextItem.src, nextItem);
				}
			}
			if (videoBufferPrev && prevItem && prevItem.type === "video" && prevItem.src) {
				if (bufferPrevSrc !== prevItem.src) {
					bufferPrevSrc = prevItem.src;
					preloadIntoBuffer(videoBufferPrev, prevItem.src, prevItem);
				}
			}
		};
		if (delay > 0) {
			setTimeout(doPreload, delay);
		} else {
			doPreload();
		}
	}

	// Bind all hero video events to a specific video element and item.
	function bindVideoEvents(videoEl, item) {
		if (!videoEl) return;
		var $v = $(videoEl);
		$v.off("ended.tmhero loadedmetadata.tmhero playing.tmhero canplay.tmhero volumechange.tmhero error.tmhero")
			.on("ended.tmhero", function() {
				if (state.loopMode) {
					// Smart TV: native loop=true can fire spurious ended events after 2-3 cycles.
					// Manually seek+replay to avoid decoder restart flicker.
					if (isSmartTV()) { try { videoEl.currentTime = 0; videoEl.play(); } catch(e) {} }
					return;
				}
				// A natural video end always means "keep playing" — use userPausedMedia (set only
				// by an explicit user pause) as the bail-out flag instead of !state.isPlaying.
				// state.isPlaying can be false due to a browser autoplay rejection even though the
				// video actually played; forcing it true here lets loadNext() and canAutoplay()
				// succeed for the next item without needing a manual click.
				if (userPausedMedia) return;
				state.isPlaying = true;
				// Video ended naturally before advance timer: cancel timer and advance now.
				clearAdvanceTimer();
				loadNext();
			})
			.on("playing.tmhero", function() {
				// Advance timer is started in loadItem at the moment attemptPlay is called;
				// do not call scheduleAutoNextForItem here — playing fires multiple times
				// (seek, unmute, stall recovery) and would reset the elapsed countdown.
				// syncRemotePlaying is safe unconditionally: the overlay/remote should
				// reflect "playing" as soon as the element fires, regardless of whether
				// the cinematic blackout is still active (blackout is a separate CSS layer).
				enforcePreferredUnmute(videoEl);
				syncRemotePlaying(true);
			})
			.on("volumechange.tmhero", function() {
				syncMutedFromElement(videoEl);
			})
			.on("error.tmhero", function() {
				clearAdvanceTimer();
				showLoadingIndicator(false);
				state.isPlaying = false;
				syncRemotePlaying(false);
			})
			.on("loadedmetadata.tmhero", function() {
				if (videoEl.duration) {
					updateMeta(item, Math.round(videoEl.duration));
					onMediaDurationKnown(item, videoEl.duration);
				}
			});
	}

	function getDefaultVendorAvatarSrc() {
		if (defaultVendorAvatarSrc) return defaultVendorAvatarSrc;
		var fallback = $(".profile-img img").first().attr("src") || "";
		defaultVendorAvatarSrc = fallback;
		return defaultVendorAvatarSrc;
	}

	function updateTalentPanelOpenState() {
		var isMobileLandscape = $(document.body).hasClass("tm-mobile-landscape");
		var isMobilePortrait = $(document.body).hasClass("tm-mobile-portrait");
		var isExpanded = !$(".profile-info-head").hasClass("is-collapsed");
		$(document.body).toggleClass("tm-talent-panel-open", Boolean((isMobileLandscape || isMobilePortrait) && isExpanded));
	}

	function shouldKeepTalentPanelCollapsedOnHandheld() {
		return isHandheldViewport();
	}


	function setPanelHoldUntil(holdMs) {
		if (shouldKeepTalentPanelCollapsedOnHandheld()) {
			$(".profile-info-head").addClass("is-collapsed");
			updateTalentPanelOpenState();
			return;
		}
		if (!holdMs || holdMs <= 0) return;
		panelHoldUntil = Date.now() + holdMs;
		if (panelHoldTimer) {
			clearTimeout(panelHoldTimer);
		}
		panelHoldTimer = setTimeout(function() {
			panelHoldUntil = 0;
			$(".profile-info-head").removeClass("is-collapsed");
			updateTalentPanelOpenState();
		}, holdMs);
	}

	function clearTransitionTimers() {
		Object.keys(transitionTimers).forEach(function(key) {
			if (transitionTimers[key]) {
				clearTimeout(transitionTimers[key]);
				transitionTimers[key] = null;
			}
		});
		if (collapseAvatarTimer) {
			clearTimeout(collapseAvatarTimer);
			collapseAvatarTimer = null;
		}
		transitionSequenceActive = false;
	}

	function isShowcaseRotationPaused() {
		return isShowcaseMode() && showcaseInteractionPause;
	}

	function ensureResumeShowcaseControl() {
		var $container = $(".keyboard-nav-container").first();
		if (!$container.length) return $();
		var $slot = $(".tm-showcase-resume-slot").first();
		if (!$slot.length) {
			$slot = $('<div class="tm-showcase-resume-slot" aria-live="polite"></div>');
			$slot.insertBefore($container);
		}
		var $control = $slot.find(".tm-showcase-resume-control").first();
		if ($control.length) return $control;
		$control = $(
			'<button class="tm-showcase-resume-control" type="button" aria-live="polite" aria-label="Resume showcase autoplay" title="Resume showcase autoplay">'
			+ '<span class="tm-showcase-resume-control__pin" aria-hidden="true">&#9679;</span>'
			+ '<span class="tm-showcase-resume-control__label">Resume Showcase</span>'
			+ '</button>'
		);
		$slot.append($control);
		return $control;
	}

	function updateResumeShowcaseControl() {
		var $control = ensureResumeShowcaseControl();
		if (!$control.length) return;
		$control.toggleClass("is-visible", isShowcaseRotationPaused());
	}

	function pauseShowcaseRotation() {
		if (!isShowcaseMode()) return;
		showcaseInteractionPause = true;
		clearTransitionTimers();
		stopBlackout();
		updateResumeShowcaseControl();
	}

	function resumeShowcaseRotation() {
		if (!isShowcaseMode()) return;
		showcaseInteractionPause = false;
		updateResumeShowcaseControl();
		if (!state.isPlaying || isAutoplayBlocked()) return;
		clearTransitionTimers();
		clearAdvanceTimer();
		armAdvanceTimer(currentItem());
	}

	function setTransitionHold(ms) {
		transitionHoldUntil = Date.now() + ms;
		if (transitionHoldTimer) {
			clearTimeout(transitionHoldTimer);
		}
		transitionHoldTimer = setTimeout(function() {
			transitionHoldUntil = 0;
			// Only kick playback if media isn't already running. Calling playCurrent() on
			// an already-playing video re-triggers the Netflix muted-start trick, causing a
			// jarring audio cut and (on mobile) a black-flash from decoder restart.
			var alreadyPlaying = heroVideoActive && !heroVideoActive.paused && !heroVideoActive.ended;
			if (state.isPlaying && !alreadyPlaying) {
				playCurrent();
			}
		}, ms);
	}

	function startBlackout() {
		// Skip the blackout overlay entirely on mobile — the ::after compositor layer
		// causes the Samsung Vulkan GPU to render the video surface black and it
		// persists across subsequent auto-advances. Videos crossfade via opacity
		// transitions already so no visual gap occurs without the blackout.
		if (isMobileDevice()) return;
		$(".profile-frame").addClass("tm-vendor-blackout");
	}

	function stopBlackout() {
		$(".profile-frame").removeClass("tm-vendor-blackout");
	}

	function getNextVendorIndex() {
		if (!vendorList.length || currentVendorIndex < 0) return -1;
		var next = currentVendorIndex + 1;
		if (next >= vendorList.length) {
			next = vendorLoopEnabled ? -1 : 0;
		}
		return next;
	}

	function prefetchVendorByIndex(index) {
		if (index < 0 || !vendorList.length) return;
		if (vendorPrefetch.index === index && vendorPrefetch.data) return;
		if (vendorPrefetch.xhr) {
			vendorPrefetch.xhr.abort();
			vendorPrefetch.xhr = null;
		}
		vendorPrefetch.index = index;
		vendorPrefetch.data = null;
		var vendor = vendorList[index];
		if (!vendor || !vendor.id) return;
		var cached = readVendorContentCache(vendor.id);
		if (cached) {
			vendorPrefetch.data = cached;
			var warmSrcCached = getFirstVideoSrc(cached.data && cached.data.vendorMedia ? cached.data.vendorMedia : null);
			if (warmSrcCached) {
				warmupVideoSource(warmSrcCached);
			}
			return;
		}
		var request = getVendorContentRequest(vendor.id);
		vendorPrefetch.xhr = $.ajax({
			url: request.url,
			type: request.type,
			data: request.data || {}
		}).done(function(response) {
			if (response && response.success && response.data) {
				vendorPrefetch.data = response;
				writeVendorContentCache(vendor.id, response);
				var warmSrc = getFirstVideoSrc(response.data.vendorMedia);
				if (warmSrc) {
					warmupVideoSource(warmSrc);
				}
			}
		}).always(function() {
			vendorPrefetch.xhr = null;
		});
	}

	function shouldUseVendorContentRest() {
		// window.tmVendorStoreRestUrl is a dedicated global set by wp_add_inline_script
		// before player.js loads — it is never overwritten by vendor-store-js.
		// NOTE: Showcase mode always uses AJAX (not REST) so vendor-swap requests carry
		// browser cookies and are authenticated — required for server-side CTA rendering.
		if (isShowcaseMode()) return false;
		if (window.tmVendorStoreRestUrl) return true;
		if (!window.vendorStoreData) return false;
		if (vendorStoreData.canEdit || vendorStoreData.isOwner || vendorStoreData.isAdminEditing) {
			return false;
		}
		return !!vendorStoreData.vendorStoreRestUrl;
	}

	function getAjaxVendorContentRequest(vendorId) {
		var ajaxUrl = (window.vendorStoreData && window.vendorStoreData.ajaxurl)
			? window.vendorStoreData.ajaxurl
			: '/wp-admin/admin-ajax.php';
		return {
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'get_vendor_store_content',
				vendor_id: vendorId,
				context_mode: isShowcaseMode() ? 'showcase' : 'profile'
			}
		};
	}

	function getVendorContentRequest(vendorId) {
		if (shouldUseVendorContentRest()) {
			// Prefer the dedicated global (immune to vendorStoreData overwrites) then fall back.
			var restBase = window.tmVendorStoreRestUrl
				|| (window.vendorStoreData && vendorStoreData.vendorStoreRestUrl)
				|| '';
			return {
				url: restBase + '?vendor_id=' + encodeURIComponent(vendorId) + '&context_mode=' + encodeURIComponent(isShowcaseMode() ? 'showcase' : 'profile'),
				type: 'GET'
			};
		}
		return getAjaxVendorContentRequest(vendorId);
	}

	function canEditProfile() {
		if (!window.vendorStoreData) return false;
		if (typeof window.vendorStoreData.canEdit !== "undefined") {
			return !!window.vendorStoreData.canEdit;
		}
		return !!window.vendorStoreData.isOwner;
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	// ==========================================
	// OVERLAY EDITING HELPER FUNCTIONS
	// ==========================================
	
	// HELPER FUNCTION: Clean up editing mode classes
	function cleanupEditingMode($wrapper) {
		// Always target the profile-info-head container for overlay
		var $profileHead = $wrapper.closest('.profile-info-head');
		var $section = $wrapper.closest('.overlay-section');
		if ($profileHead.length) {
			$profileHead.removeClass('head-editing');
		} else {
		}
		if ($section.length) {
			$section.removeClass('section-editing');
		}
	}

	// ==========================================
	// FIELD EDITOR MODAL FUNCTIONS
	// ==========================================

	var currentFieldEditor = {
		wrapper: null,
		field: null,
		fieldType: null,
		originalData: null
	};

	function publicPersonSingularLabel() {
		return (window.vendorStoreUiData && vendorStoreUiData.personLabelSingular)
			? vendorStoreUiData.personLabelSingular
			: 'Talent';
	}

	function openFieldEditorModal(fieldType, $wrapper) {
		var $modal = $('.tm-field-editor-modal');
		var $dialog = $modal.find('.tm-field-editor-dialog');
		var $title = $dialog.find('.editor-title');
		var $body = $dialog.find('.editor-body');

		// Store context
		currentFieldEditor.wrapper = $wrapper;
		currentFieldEditor.field = $wrapper.data('field');
		currentFieldEditor.fieldType = fieldType;
		
		// Set title
		var personSingularLabel = publicPersonSingularLabel();
		var titles = {
			'store_categories': 'Edit Categories',
			'contact_emails': 'Edit Contact Emails',
			'contact_phones': 'Edit Contact Phones',
		'store_name': 'Edit ' + personSingularLabel + ' Name',
		'geo_location': 'Edit Location'
	};
	
	// Read help text from wrapper for all field types
	var helpText = $wrapper.data('help') || '';
	
	if (fieldType === 'attribute') {
		var editLabel = $wrapper.data('editLabel') || $wrapper.data('label');
		$title.text('Edit ' + (editLabel || 'Field'));
	} else {
		$title.text(titles[currentFieldEditor.field] || 'Edit Field');
	}
	
	// Add help icon to title if help text exists (for all field types)
	if (helpText) {
		var $helpIcon = $('<span class="help-icon-wrapper modal-help-wrapper" style="position: relative; display: inline-block; margin-left: 8px;"><button type="button" class="help-toggle-btn" aria-label="Show help" style="font-size: 16px; color: #888; cursor: pointer; background: none; border: none; padding: 0;" data-help-text="' + helpText + '"><i class="fas fa-question-circle" aria-hidden="true"></i></button></span>');
		$title.append($helpIcon);
	}
	
	// Load field-specific content
	$body.empty();
	
	if (fieldType === 'store_categories') {
		loadCategoriesEditor($body, $wrapper);
	} else if (fieldType === 'contact_emails') {
		loadContactEmailEditor($body, $wrapper);
	} else if (fieldType === 'contact_phones') {
		loadContactPhoneEditor($body, $wrapper);
	} else if (fieldType === 'store_name') {
		loadNameEditor($body, $wrapper);
	} else if (fieldType === 'geo_location') {
		loadLocationEditor($body, $wrapper);
	} else if (fieldType === 'attribute') {
		loadAttributeEditor($body, $wrapper);
	}
	
	// Show modal
	$modal.toggleClass('is-location-editor', fieldType === 'geo_location');
	$modal.addClass('is-open').attr('aria-hidden', 'false');
	$('body').toggleClass(
		'tm-birthdate-modal-open',
		fieldType === 'attribute' && currentFieldEditor.field === 'demo_birth_date'
	);
	
	// Focus first input
	setTimeout(function() {
		$body.find('input, select').first().focus();
	}, 100);
}

	function closeFieldEditorModal() {
		var $modal = $('.tm-field-editor-modal');
		
		// Remove help tooltips and icons from modal title
		$('.help-tooltip').remove();
		$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
		$modal.find('.editor-title .modal-help-wrapper').remove();
		$('body').removeClass('tm-birthdate-modal-open');

		$modal.removeClass('is-open is-location-editor is-onboard-modal').attr('aria-hidden', 'true');
		$modal.find('.editor-body').empty();
		$modal.find('.editor-save-btn').show().text('Save');
		$modal.find('.editor-cancel-btn').text('Cancel');
		currentFieldEditor = { wrapper: null, field: null, fieldType: null, originalData: null };
		resumeBackgroundAfterEditing();
	}

	function openOnboardModal() {
		var $modal = $('.tm-field-editor-modal');
		var $dialog = $modal.find('.tm-field-editor-dialog');
		var $title = $dialog.find('.editor-title');
		var $body = $dialog.find('.editor-body');
		var $saveBtn = $dialog.find('.editor-save-btn');
		var $cancelBtn = $dialog.find('.editor-cancel-btn');

		$title.text('Share Onboarding Link');
		$body.html('<div class="tm-onboard-modal">Loading onboarding link...</div>');
		$saveBtn.hide();
		$cancelBtn.text('Close');
		$modal.addClass('is-open is-onboard-modal').attr('aria-hidden', 'false');
	}

	function renderOnboardModal(data) {
		var $modal = $('.tm-field-editor-modal');
		var $body = $modal.find('.editor-body');
		var link = data && data.link ? data.link : '';
		var qr = data && data.qr ? data.qr : '';
		var expires = data && data.expires_at ? new Date(data.expires_at * 1000) : null;
		var expiresText = expires ? expires.toLocaleString() : '';
		var adminName = data && data.admin_name ? data.admin_name : '';
		var talentName = data && data.talent_name ? data.talent_name : '';
		var avatarUrl = data && data.admin_avatar_url ? data.admin_avatar_url : '';
		var avatarId = data && data.admin_avatar_id ? data.admin_avatar_id : '';
		var vendorAvatarUrl = data && data.vendor_avatar_url ? data.vendor_avatar_url : '';
		var vendorAvatarId = data && data.vendor_avatar_id ? data.vendor_avatar_id : '';
		var personSingularLower = publicPersonSingularLabel().toLowerCase();
		var defaultMessage = 'Dear $TalentName,\n\n$AdminName is inviting you to join Casting Agency Co and has already pre-filled your profile. Create an account to claim your ' + personSingularLower + ' profile, you will then be able to complete/publish it.';
		var message = data && data.admin_message ? data.admin_message : defaultMessage;

		var html = '';
		html += '<div class="tm-onboard-modal">';
		html += '<div class="tm-onboard-admin-grid">';
		html += '<div class="tm-onboard-admin-avatar">';
		html += '<div class="tm-onboard-avatar-stack">';
		html += '<div class="tm-onboard-avatar-preview tm-onboard-avatar-preview--admin">';
		if (avatarUrl) {
			html += '<img src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(adminName) + '" />';
		} else {
			html += '<div class="tm-onboard-avatar-fallback">No avatar</div>';
		}
		html += '</div>';
		html += '<div class="tm-onboard-avatar-preview tm-onboard-avatar-preview--vendor">';
		if (vendorAvatarUrl) {
			html += '<img src="' + escapeHtml(vendorAvatarUrl) + '" alt="' + escapeHtml(talentName) + '" />';
		} else {
			html += '<div class="tm-onboard-avatar-fallback">No avatar</div>';
		}
		html += '</div>';
		html += '</div>';
		html += '<div class="tm-onboard-avatar-actions">';
		html += '<button type="button" class="tm-onboard-avatar-btn">Change Admin Avatar</button>';
		html += '<button type="button" class="tm-onboard-vendor-avatar-btn">Change ' + escapeHtml(publicPersonSingularLabel()) + ' Avatar</button>';
		html += '</div>';
		html += '<input type="hidden" class="tm-onboard-avatar-id" value="' + escapeHtml(avatarId) + '" />';
		html += '<input type="hidden" class="tm-onboard-avatar-url" value="' + escapeHtml(avatarUrl) + '" />';
		html += '<input type="hidden" class="tm-onboard-vendor-avatar-id" value="' + escapeHtml(vendorAvatarId) + '" />';
		html += '<input type="hidden" class="tm-onboard-vendor-avatar-url" value="' + escapeHtml(vendorAvatarUrl) + '" />';
		html += '</div>';
		html += '<div class="tm-onboard-admin-message">';
		html += '<label for="tm-onboard-message">Message</label>';
		html += '<textarea id="tm-onboard-message" class="tm-onboard-message" rows="5">' + escapeHtml(message) + '</textarea>';
		html += '<div class="tm-onboard-message-hint">Use $TalentName and $AdminName to personalize.</div>';
		html += '</div>';
		html += '</div>';
		html += '<button type="button" class="tm-onboard-generate">Update Link</button>';
		html += '<div class="tm-onboard-link-row">';
		html += '<input type="text" class="tm-onboard-link" readonly value="' + link + '" />';
		html += '<button type="button" class="tm-onboard-copy">Copy Link</button>';
		html += '</div>';
		if (expiresText) {
			html += '<div class="tm-onboard-expiry">Expires: ' + expiresText + '</div>';
		}
		if (qr) {
			html += '<div class="tm-onboard-qr">' + qr + '</div>';
		}
		html += '</div>';

		$body.html(html);
	}

	function requestOnboardLink($button) {
		if (!window.vendorStoreData || !vendorStoreData.onboardNonce) {
			showNotification('Unable to create onboarding link.', 'error');
			return;
		}
		var vendorId = $('.tm-onboard-share-btn').data('vendorId') || vendorStoreData.userId || 0;
		var $modal = $('.tm-field-editor-modal');
		var adminMessage = $modal.find('.tm-onboard-message').val() || '';
		var avatarId = $modal.find('.tm-onboard-avatar-id').val() || '';
		var avatarUrl = $modal.find('.tm-onboard-avatar-url').val() || '';
		var vendorAvatarId = $modal.find('.tm-onboard-vendor-avatar-id').val() || '';
		var vendorAvatarUrl = $modal.find('.tm-onboard-vendor-avatar-url').val() || '';

		if ($button) {
			$button.prop('disabled', true).text('Updating...');
		}

		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'tm_onboard_share_link',
				nonce: vendorStoreData.onboardNonce,
				vendor_id: vendorId,
				admin_message: adminMessage,
				admin_avatar_id: avatarId,
				admin_avatar_url: avatarUrl,
				vendor_avatar_id: vendorAvatarId,
				vendor_avatar_url: vendorAvatarUrl
			}
		}).done(function(response) {
			if (response && response.success && response.data) {
				renderOnboardModal(response.data);
				if (response.data.store_url && response.data.store_url !== window.location.href) {
					window.history.replaceState(null, '', response.data.store_url);
				}
				return;
			}
			var message = (response && response.data && response.data.message) ? response.data.message : 'Unable to create onboarding link.';
			$('.tm-field-editor-modal .editor-body').html('<div class="tm-onboard-modal tm-onboard-error">' + message + '</div>');
		}).fail(function() {
			$('.tm-field-editor-modal .editor-body').html('<div class="tm-onboard-modal tm-onboard-error">Unable to create onboarding link.</div>');
		}).always(function() {
			if ($button) {
				$button.prop('disabled', false).text('Update Link');
			}
		});
	}

	function openOnboardClaimModal() {
		var $template = $('.tm-onboard-claim-template');
		if (!$template.length) return;

		var $modal = $('.tm-field-editor-modal');
		var $dialog = $modal.find('.tm-field-editor-dialog');
		var $title = $dialog.find('.editor-title');
		var $body = $dialog.find('.editor-body');
		var $saveBtn = $dialog.find('.editor-save-btn');
		var $cancelBtn = $dialog.find('.editor-cancel-btn');

		suspendBackgroundForEditing();

		$title.text('Claim Profile');
		$body.html($template.html());
		$saveBtn.hide();
		$cancelBtn.text('Close');
		$modal.addClass('is-open is-onboard-modal').attr('aria-hidden', 'false');
		initOnboardClaimGate($modal);
	}

	function initOnboardClaimGate($modal) {
		if (!$modal || !$modal.length) return;
		var $form = $modal.find('form.tm-onboard-claim--modal');
		if (!$form.length) return;
		var $privacy = $form.find('input[name="tm_accept_privacy"]');
		var $terms = $form.find('input[name="tm_accept_terms"]');
		var $email = $form.find('input[name="email"]');
		var $password = $form.find('input[name="password"]');
		var $submit = $form.find('.tm-onboard-claim-btn');

		$form.off('.tmOnboardGate');
		$privacy.off('.tmOnboardGate');
		$terms.off('.tmOnboardGate');
		$email.off('.tmOnboardGate');
		$password.off('.tmOnboardGate');
		$submit.off('.tmOnboardGate');

		function updateClaimGate() {
			var hasEmail = ($email.val() || '').trim().length > 0;
			var hasPassword = ($password.val() || '').trim().length > 0;
			var enabled = hasEmail && hasPassword && $privacy.prop('checked') && $terms.prop('checked');
			$submit.toggleClass('is-disabled', !enabled);
			$submit.attr('aria-disabled', enabled ? 'false' : 'true');
			if (!enabled) {
				$submit.removeAttr('disabled');
			}
		}

		function showClaimGateTooltip() {
			var personTermsLabel = (window.vendorStoreUiData && vendorStoreUiData.personTermsLabel)
				? String(vendorStoreUiData.personTermsLabel).toLowerCase()
				: 'talent terms';
			showCenteredTooltip('Please enter your email and password, then accept the privacy policy and ' + personTermsLabel + ' to claim your profile.', 3000);
		}

		updateClaimGate();
		$privacy.on('change.tmOnboardGate', updateClaimGate);
		$terms.on('change.tmOnboardGate', updateClaimGate);
		$email.on('input.tmOnboardGate', updateClaimGate);
		$password.on('input.tmOnboardGate', updateClaimGate);
		$submit.on('click.tmOnboardGate', function(e) {
			if ($submit.attr('aria-disabled') === 'true') {
				e.preventDefault();
				e.stopPropagation();
				showClaimGateTooltip();
			}
		});
		$form.on('submit.tmOnboardGate', function(e) {
			if ($submit.attr('aria-disabled') === 'true') {
				e.preventDefault();
				e.stopPropagation();
				showClaimGateTooltip();
			}
		});
	}

	function loadCategoriesEditor($body, $wrapper) {
		var $originalSelect = $wrapper.find('select[name="store_categories[]"]');
		var $select = $originalSelect.clone();
		$select.attr('id', 'modal-categories-select');
		$body.append($select);
	}

	function loadContactEmailEditor($body, $wrapper) {
		// Save original data
		currentFieldEditor.originalData = getContactListData($wrapper);
		
		var mainValue = currentFieldEditor.originalData.main || '';
		var list = currentFieldEditor.originalData.list || [];
		var radioName = $wrapper.find('.contact-edit-list').data('radio-name');
		
		// Add main summary
		var $summary = $('<div class="contact-main-summary"></div>');
		$summary.append('<span class="contact-label">Main Email:</span>');
		$summary.append('<span class="contact-value contact-email-main-value">' + (mainValue || 'Not set') + '</span>');
		$body.append($summary);
		
		// Add entries container
		var $entries = $('<div class="contact-edit-entries"></div>');
		var $list = $('<div class="contact-edit-list" data-type="email" data-radio-name="' + radioName + '"></div>');
		
		// Ensure 3 slots
		while (list.length < 3) list.push('');
		list = list.slice(0, 3);
		
		list.forEach(function(value, index) {
			var isMain = value && value === mainValue;
			var $row = buildContactRow('email', value, isMain, radioName);
			$list.append($row);
		});
		
		$entries.append($list);
		$body.append($entries);
		
		// Bind radio change to update summary
		$body.on('change', '.contact-main-radio', function() {
			var $row = $(this).closest('.contact-edit-row');
			var emailValue = $row.find('.contact-email-input').val() || 'Not set';
			$body.find('.contact-email-main-value').text(emailValue);
		});
	}

	function loadContactPhoneEditor($body, $wrapper) {
		// Save original data
		currentFieldEditor.originalData = getContactListData($wrapper);
		
		var mainValue = currentFieldEditor.originalData.main || '';
		var list = currentFieldEditor.originalData.list || [];
		var radioName = $wrapper.find('.contact-edit-list').data('radio-name');
		
		// Add main summary
		var $summary = $('<div class="contact-main-summary"></div>');
		$summary.append('<span class="contact-label">Main Phone:</span>');
		$summary.append('<span class="contact-value contact-phone-main-value">' + (mainValue || 'Not set') + '</span>');
		$body.append($summary);
		
		// Add entries container
		var $entries = $('<div class="contact-edit-entries"></div>');
		var $list = $('<div class="contact-edit-list" data-type="phone" data-radio-name="' + radioName + '"></div>');
		
		// Ensure 3 slots
		while (list.length < 3) list.push('');
		list = list.slice(0, 3);
		
		list.forEach(function(value, index) {
			var isMain = value && value === mainValue;
			var $row = buildContactRow('phone', value, isMain, radioName);
			$list.append($row);
		});
		
		$entries.append($list);
		$body.append($entries);
		
		// Bind radio change to update summary
		$body.on('change', '.contact-main-radio', function() {
			var $row = $(this).closest('.contact-edit-row');
			var phoneValue = $row.find('.contact-phone-input').val() || 'Not set';
			$body.find('.contact-phone-main-value').text(phoneValue);
		});
	}

	function loadNameEditor($body, $wrapper) {
		var currentValue = $wrapper.find('.field-value').text();
		var $input = $('<input type="text" name="store_name" value="' + currentValue + '" />');
		$body.append($input);
	}

	function loadLocationEditor($body, $wrapper) {
		// Get current location data
		var currentAddress = $wrapper.find('.location-search-input').val() || '';
		var $mapboxPanel = $wrapper.find('.inline-mapbox-panel').first();
		var lat = $mapboxPanel.data('lat') || '';
		var lng = $mapboxPanel.data('lng') || '';
		
		// Create location search input
		var $searchInput = $('<input type="text" id="vendor-location-search-modal" name="geo_location" class="edit-field-input location-search-input" placeholder="Start typing location..." />');
		$searchInput.val(currentAddress);
		
		// Create hidden input for location data
		var $hiddenInput = $('<input type="hidden" id="vendor-location-data-modal" name="location_data" value="" />');
		
		// Create mapbox panel
		var $mapboxPanelClone = $('<div class="inline-mapbox-panel" data-lat="' + lat + '" data-lng="' + lng + '"></div>');
		$mapboxPanelClone.append('<div class="inline-mapbox-search"></div>');
		$mapboxPanelClone.append('<div class="inline-mapbox-map"></div>');
		
		// Append to modal body
		$body.append($searchInput);
		$body.append($hiddenInput);
		$body.append($mapboxPanelClone);
		
		// Initialize Mapbox for the modal
		setTimeout(function() {
			initInlineLocationMap($body);
		}, 100);
	}

	var datepickerLoader = {
		loading: false,
		queue: []
	};

	function injectDatepickerCss(href) {
		if (!href) return;
		if (document.querySelector('link[data-tm-datepicker-css="1"]')) return;
		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		link.setAttribute('data-tm-datepicker-css', '1');
		document.head.appendChild(link);
	}

	function injectDatepickerScript(src, onDone) {
		if (!src) {
			onDone();
			return;
		}
		var selector = 'script[data-tm-datepicker-src="' + src + '"]';
		if (document.querySelector(selector)) {
			onDone();
			return;
		}
		var script = document.createElement('script');
		script.src = src;
		script.async = true;
		script.setAttribute('data-tm-datepicker-src', src);
		script.onload = onDone;
		script.onerror = onDone;
		document.head.appendChild(script);
	}

	function ensureDatepickerAssets(readyCallback) {
		if (jQuery.fn && jQuery.fn.datepicker) {
			readyCallback();
			return;
		}
		if (!window.vendorStoreData) {
			readyCallback();
			return;
		}
		datepickerLoader.queue.push(readyCallback);
		if (datepickerLoader.loading) return;
		datepickerLoader.loading = true;

		var cssUrl = vendorStoreData.jqueryUiCssUrl || '/wp-content/plugins/woocommerce-bookings/dist/jquery-ui-styles.css';
		injectDatepickerCss(cssUrl);

		var pending = 3;
		var done = function() {
			pending--;
			if (pending > 0) return;
			datepickerLoader.loading = false;
			if (!jQuery.fn || !jQuery.fn.datepicker) {
				datepickerLoader.queue = [];
				return;
			}
			var queue = datepickerLoader.queue.slice();
			datepickerLoader.queue = [];
			queue.forEach(function(fn) {
				try {
					fn();
				} catch (e) {}
			});
		};

		injectDatepickerScript(vendorStoreData.jqueryUiCoreUrl, done);
		injectDatepickerScript(vendorStoreData.jqueryUiWidgetUrl, done);
		injectDatepickerScript(vendorStoreData.jqueryUiDatepickerUrl, done);
	}

	function initDatepickerInput($input) {
		if (!$input || !$input.length) return;
		if (!jQuery.fn || !jQuery.fn.datepicker) return;
		if ($input.data('tm-datepicker')) return;
		$input.datepicker({
			dateFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear: true,
			yearRange: '1900:+0'
		});
		if ($input.val()) {
			try {
				$input.datepicker('setDate', $input.val());
			} catch (e) {}
		}
		$input.data('tm-datepicker', true);
	}

	function loadAttributeEditor($body, $wrapper) {
		var fieldName = $wrapper.data('field');
		var inputType = $wrapper.data('inputType') || 'select';
		var isMulti = $wrapper.data('multi') === 1 || $wrapper.data('multi') === true || $wrapper.data('multi') === '1';
		var options = $wrapper.data('options') || {};
		var helpText = $wrapper.data('help') || '';
		var editLabel = $wrapper.data('editLabel') || $wrapper.data('label') || '';
		var currentValue = '';
		var currentValues = [];

		if (inputType === 'date') {
			currentValue = $wrapper.attr('data-raw-value') || $wrapper.data('rawValue') || '';
		} else if (isMulti) {
			currentValues = $wrapper.data('values') || [];
			if (!Array.isArray(currentValues)) {
				currentValues = [currentValues];
			}
		} else {
			currentValue = $wrapper.data('value') || '';
		}

		if (inputType === 'date') {
			var $dateInput = $('<input type="text" class="edit-field-input tm-date-input" data-field="' + fieldName + '" />');
			$dateInput.val(currentValue);
			$body.append($dateInput);
			ensureDatepickerAssets(function() {
				initDatepickerInput($dateInput);
				if (currentValue) {
					$dateInput.val(currentValue);
					if (jQuery.fn && jQuery.fn.datepicker) {
						try {
							$dateInput.datepicker('setDate', currentValue);
						} catch (e) {}
					}
				}
			});
		} else if (inputType === 'text' || inputType === 'url' || inputType === 'email' || inputType === 'number') {
			var $textInput = $('<input type="' + inputType + '" class="edit-field-input" data-field="' + fieldName + '" />');
			$textInput.val(currentValue);
			$body.append($textInput);
		} else {
			var $select = $('<select class="edit-field-input" data-field="' + fieldName + '"></select>');
			if (isMulti) {
				$select.attr('multiple', true).attr('size', 8);
			}
			Object.keys(options).forEach(function(key) {
				if (!Object.prototype.hasOwnProperty.call(options, key)) return;
				var label = options[key];
				var $opt = $('<option></option>').attr('value', key).text(label);
				if (isMulti) {
					if (currentValues.indexOf(key) !== -1) {
						$opt.prop('selected', true);
					}
				} else if (currentValue === key) {
					$opt.prop('selected', true);
				}
				$select.append($opt);
			});
			$body.append($select);
		}
	}

	function syncDateAttributeDom($wrapper, value) {
		if (!$wrapper || !$wrapper.length) return;
		$wrapper.data('rawValue', value || '').attr('data-raw-value', value || '');
		$wrapper.data('value', value || '').attr('data-value', value || '');
		var $input = $wrapper.find('input.tm-date-input');
		if ($input.length) {
			$input.val(value || '');
			if (jQuery.fn && jQuery.fn.datepicker) {
				try {
					$input.datepicker('setDate', value || null);
				} catch (e) {}
			}
		}
	}
	
	var HANDHELD_QUERIES = [
		"(max-width: 600px) and (orientation: portrait)",
		"(max-width: 900px) and (orientation: landscape)",
		"(min-width: 601px) and (max-width: 1024px) and (orientation: portrait)",
		"(min-width: 901px) and (max-width: 1366px) and (orientation: landscape)"
	];

	function isHandheldViewport() {
		if (!window.matchMedia) return false;
		for (var i = 0; i < HANDHELD_QUERIES.length; i++) {
			if (window.matchMedia(HANDHELD_QUERIES[i]).matches) return true;
		}
		return false;
	}

	function isKeyboardEnabled() {
		return !isHandheldViewport();
	}

	function readStoredBool(key, fallback) {
		try {
			var val = window.localStorage ? localStorage.getItem(key) : null;
			if (val === null) return fallback;
			return val === "true";
		} catch (e) {
			return fallback;
		}
	}

	function writeStoredBool(key, value) {
		try {
			if (!window.localStorage) return;
			localStorage.setItem(key, value ? "true" : "false");
		} catch (e) {}
	}

	var userHasInteracted = false;
	// Tracks ONLY real user gestures (tap/click) — NOT the stored-preference early-set.
	// Used to skip the Netflix muted-flip on mobile after the user has granted play trust.
	var userHasMadeRealGesture = false;
	function markUserInteraction() {
		userHasInteracted = true;
		userHasMadeRealGesture = true;
		console.log('[TM PLAYER DEBUG] markUserInteraction called | userHasInteracted:', userHasInteracted, '| userHasMadeRealGesture:', userHasMadeRealGesture);
	}
	function allowMuteFallbackForPlayback() {
		if (state.muted) return false; // user chose silence — no fallback needed
		// On mobile: skip the Netflix muted-start trick (muted=true → play → muted=false)
		// once the user has made a real gesture (tapped Play).
		// Why: the muted→unmuted volumechange flip causes the Samsung Vulkan GPU compositor
		// to restart the hardware video decoder, resetting the video surface to black on
		// auto-advance (Galaxy S20, Z Fold, etc.).
		// After a real user tap, Android Chrome grants a document-level autoplay trust
		// token so play() with muted=false succeeds directly — no trick required.
		if (isMobileDevice() && userHasMadeRealGesture) return false;
		return true;
	}

	function applyMute() {
		var $heroAudio = $(".hero-audio").first();
		if (heroVideoActive) { heroVideoActive.muted = !!state.muted; }
		if ($heroAudio.length) { $heroAudio[0].muted = !!state.muted; }
	}

	function ensureShowcasePlayOverlay($heroBox) {
		if (!$heroBox || !$heroBox.length) return;
		var $overlay = $heroBox.find(".tm-showcase-play-overlay");
		if ($overlay.length) return $overlay;
		$overlay = $(
			"<button class=\"tm-showcase-play-overlay is-hidden\" type=\"button\" aria-label=\"Play\">" +
			"<span class=\"tm-showcase-play-icon\" aria-hidden=\"true\">" +
			"<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 384 512\" class=\"tm-icon tm-icon-play\" style=\"fill:currentColor\"><path d=\"M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z\"/></svg>" +
			"</span>" +
			"</button>"
		);
		// If the showcase is already running (user has clicked Play at least once),
		// start the overlay hidden. syncRemotePlaying() will un-hide it if media
		// genuinely fails to play; otherwise it stays invisible and autoplay continues.
		if (hasShowcaseStarted()) {
			$overlay.addClass("is-hidden");
		}
		$heroBox.append($overlay);
		$overlay.on("click", function() {
			console.log('[TM PLAYER DEBUG] Overlay clicked | state.isPlaying before:', state.isPlaying);
			markUserInteraction();
			state.muted = false;
			writeStoredBool(STORAGE_KEYS.muted, false);
			applyMute();
			syncRemoteMute();
			state.isPlaying = true;
			console.log('[TM PLAYER DEBUG] state.isPlaying set to true');
			playCurrent();
			try {
				if (window.sessionStorage) {
					sessionStorage.setItem("tm_showcase_started", "1");
					console.log('[TM PLAYER DEBUG] sessionStorage.tm_showcase_started set to "1"');
				}
			} catch (e) {
				console.log('[TM PLAYER DEBUG] sessionStorage.setItem ERROR:', e);
			}
			console.log('[TM PLAYER DEBUG] Overlay hidden, sessionStorage updated');
		});
		return $overlay;
	}

	function hasShowcaseStarted() {
		try {
			var started = !!(window.sessionStorage && sessionStorage.getItem("tm_showcase_started") === "1");
			console.log('[TM PLAYER DEBUG] hasShowcaseStarted | sessionStorage.getItem("tm_showcase_started"):', window.sessionStorage ? sessionStorage.getItem("tm_showcase_started") : 'NO sessionStorage', '| result:', started);
			return started;
		} catch (e) {
			console.log('[TM PLAYER DEBUG] hasShowcaseStarted ERROR:', e);
			return false;
		}
	}

	function restoreShowcaseInteractionState(isPageReload) {
		if (isPageReload || !hasShowcaseStarted()) return;
		userHasInteracted = true;
		userHasMadeRealGesture = true;
	}

	function ensureShowcaseKeyboardNavigation() {
		if (!isShowcaseMode()) return;
		if ($('.keyboard-nav-container').length) return;

		var $target = $('.profile-frame').first();
		if (!$target.length) return;

		$target.append(
			'<div class="tm-showcase-resume-slot" aria-live="polite"></div>'
			+
			'<div class="keyboard-nav-container" aria-label="Navigation controls">'
				+ '<div class="keyboard-nav-row keyboard-nav-top">'
					+ '<button class="keyboard-nav-btn keyboard-nav-up" type="button" aria-label="Previous media" title="Previous media (↑)">'
						+ '<span class="keyboard-nav-icon">▲</span>'
					+ '</button>'
				+ '</div>'
				+ '<div class="keyboard-nav-row keyboard-nav-bottom">'
					+ '<button class="keyboard-nav-btn keyboard-nav-left" type="button" aria-label="Previous talent" title="Previous talent (←)">'
						+ '<span class="keyboard-nav-icon">▲</span>'
					+ '</button>'
					+ '<button class="keyboard-nav-btn keyboard-nav-down" type="button" aria-label="Next media" title="Next media (↓)">'
						+ '<span class="keyboard-nav-icon">▼</span>'
					+ '</button>'
					+ '<button class="keyboard-nav-btn keyboard-nav-right" type="button" aria-label="Next talent" title="Next talent (→)">'
						+ '<span class="keyboard-nav-icon">▲</span>'
					+ '</button>'
					+ '<button class="keyboard-nav-btn keyboard-nav-loop" type="button" aria-label="Toggle talent loop" aria-pressed="false" title="Talent loop off (advance to next talent)">'
						+ '<span class="keyboard-nav-icon">↻</span>'
					+ '</button>'
				+ '</div>'
			+ '</div>'
		);
	}

	function unmuteForUserAction() {
		state.muted = false;
		hideUnmuteStrip();
		writeStoredBool(STORAGE_KEYS.muted, false);
		applyMute();
		syncRemoteMute();
	}


	function syncMutedFromElement(el) {
		if (!el) return;
		var elMuted = !!el.muted;
		if (userHasInteracted && !state.muted && elMuted) {
			// Keep user preference; don't persist browser-imposed mute.
			syncRemoteMute();
			return;
		}
		if (state.muted !== elMuted) {
			state.muted = elMuted;
			writeStoredBool(STORAGE_KEYS.muted, state.muted);
			syncRemoteMute();
		}
	}

	function enforcePreferredUnmute(el) {
		if (!el) return;
		if (!userHasInteracted || state.muted) return;
		if (!el.muted) return;
		try {
			el.muted = false;
			syncRemoteMute();
		} catch (e) {}
	}
	function clearMediaTimer() { if (state.timer) { clearTimeout(state.timer); state.timer = null; } }
	function clearAdvanceTimer() {
		if (advanceTimer) { clearTimeout(advanceTimer); advanceTimer = null; }
		if (advanceBlackoutTimer) { clearTimeout(advanceBlackoutTimer); advanceBlackoutTimer = null; }
		advanceItemSrc = "";
	}
	function isAutoplayBlocked() {
		return $("body").hasClass("tm-account-open")
			|| $("body").hasClass("tm-modal-open")
			|| $("#tm-account-modal").hasClass("is-open")
			|| $(".tm-field-editor-modal.is-open").length > 0
			|| $(".tm-location-modal.is-open").length > 0;
	}
	function canAutoplay() {
		return state.isPlaying && !isAutoplayBlocked() && Date.now() >= transitionHoldUntil;
	}
	// ── armAdvanceTimer ──────────────────────────────────────────────────────────
	// The single entry-point for the advance pipeline.
	// Called once per item, only from loadItem (never from playCurrent).
	//
	// What it does:
	//   1. Computes how long this item should play (playbackDuration or full duration).
	//   2. Arms ONE setTimeout that will call loadNext() when the time is up.
	//      The timer is identified by the item src so a stale callback from a
	//      previous item can detect it was superseded and bail out.
	//   3. A second setTimeout (advanceBlackoutTimer) applies a brief CSS blackout
	//      just before loadNext fires — purely cosmetic, zero gating role.
	//
	// Talent-swap mode (auto-swap showcase, primary video): we do NOT arm the
	// timer here — scheduleTalentSwapSequence owns the clock for that item.
	// ────────────────────────────────────────────────────────────────────────────
	function loadNext() {
		// Advance to next playlist item. Wraps at end.
		// Called only by the armAdvanceTimer callback or the video ended event.
		console.log('[TM PLAYER DEBUG] loadNext called | state.isPlaying:', state.isPlaying, '| state.loopMode:', state.loopMode, '| isAutoplayBlocked():', isAutoplayBlocked());
		if (!state.isPlaying || state.loopMode || isAutoplayBlocked()) {
			console.log('[TM PLAYER DEBUG] loadNext aborted - state.isPlaying:', state.isPlaying);
			return;
		}
		var nextIndex = state.index + 1;
		if (nextIndex >= playlist.length) {
			console.log('[TM loadNext] END OF PLAYLIST | vendorLoopEnabled:', vendorLoopEnabled, '| isShowcaseMode():', isShowcaseMode(), '| vendorList.length:', vendorList.length, '| currentVendorIndex:', currentVendorIndex);
			if (vendorLoopEnabled) {
				startBlackout();
				loadItem(0);
				advanceBlackoutTimer = setTimeout(stopBlackout, 250);
				return;
			}
			// — Auto-swap showcase (Loop OFF): trigger talent-swap sequence if vendorList is
			//   already loaded. If the list is still loading, wrap to item 0 as a safe fallback
			//   so the player never freezes while waiting for the async vendor list.
			if (isShowcaseMode() && vendorList.length && currentVendorIndex >= 0) {
				if (isShowcaseRotationPaused()) {
					startBlackout();
					loadItem(0);
					advanceBlackoutTimer = setTimeout(stopBlackout, 250);
					return;
				}
				// The advance timer already served the on-screen duration (VIDEO_LOOP_SECONDS /
				// natural video length). Do not add more wait here — schedule an immediate swap.
				// transitionSequenceActive guard prevents a double-fire (ended + armAdvanceTimer
				// both calling loadNext at the same moment) from resetting the timers.
				if (transitionSequenceActive) return;
				// talentloop=off: stop after the last vendor — don't wrap back to vendor 0.
				if (urlConfig.talentloop === false && currentVendorIndex >= vendorList.length - 1) return;
				scheduleTalentSwapSequence(0);
				return;
			}
			// Fallback (vendorList not loaded yet, or profile mode): wrap playlist.
			startBlackout();
			loadItem(0);
			advanceBlackoutTimer = setTimeout(stopBlackout, 250);
			return;
		}
		// Normal forward advance within playlist.
		startBlackout();
		loadItem(nextIndex);
		advanceBlackoutTimer = setTimeout(stopBlackout, 250);
	}

	function armAdvanceTimer(item) {
		clearAdvanceTimer();
		if (!item || !state.isPlaying || state.loopMode || isAutoplayBlocked()) return;
		// On mobile with vendor-loop active (profile page), the video plays to natural
		// end via the ended.tmhero event which calls loadNext() and wraps back to item 0.
		// Arming a timer here would cut the video short at VIDEO_LOOP_SECONDS (12s) before
		// it naturally finishes. Skip the timer and let ended drive the loop.
		if (isMobileDevice() && vendorLoopEnabled) return;
		// Images and videos: use playbackDuration (capped or full) as the countdown.
		var seconds = playbackDuration(item);
		if (!seconds || seconds <= 0) seconds = VIDEO_LOOP_SECONDS;
		var armedForSrc = item.src || "";
		advanceItemSrc = armedForSrc;
		advanceTimer = setTimeout(function() {
			advanceTimer = null;
			// Bail if the item changed since we were armed.
			if (advanceItemSrc !== armedForSrc) return;
			loadNext();
		}, seconds * 1000);
	}

	// When loadedmetadata fires with a real duration, re-arm the timer so the
	// advance fires at the correct time (not the stale VIDEO_LOOP_SECONDS default).
	function onMediaDurationKnown(item, durationSec) {
		if (!item || !durationSec || !isFinite(durationSec)) return;
		// Only reschedule if this item is still the current one.
		if ((item.src || "") !== advanceItemSrc) return;
		item.duration = Math.round(durationSec);
		// Re-arm: playbackDuration now has a real item.duration to work with.
		armAdvanceTimer(item);
	}

	function isLastPlaylistItem() {
		return playlist.length && state.index >= playlist.length - 1;
	}

	function shouldRunTalentSwap(item) {
		if (!item) return false;
		if (state.loopMode || vendorLoopEnabled || isAutoplayBlocked()) return false;
		if (isShowcaseRotationPaused()) return false;
		if (!vendorList.length || currentVendorIndex < 0) return false;
		// Profile page: never auto-swap talent — visitor stays focused on the talent they came for.
		if (!isShowcaseMode()) return false;
		// Showcase mode: swap only on the first (primary) video item.
		var primaryIndex = 0;
		var hasVideo = false;
		for (var i = 0; i < playlist.length; i++) {
			if (playlist[i] && playlist[i].type === "video") {
				primaryIndex = i;
				hasVideo = true;
				break;
			}
		}
		if (hasVideo) {
			return state.index === primaryIndex && item.type === "video";
		}
		return state.index === 0;
	}

	function setCollapsedLoadingState() {
		var $profileHead = $(".profile-info-head");
		if (!$profileHead.length) return;
		if (!$profileHead.hasClass("is-collapsed")) {
			$profileHead.addClass("is-collapsed");
			updateTalentPanelOpenState();
		}
		$(".collapsed-tab-name").text("Next Talent");
		scheduleDefaultAvatarSwap();
	}

	function scheduleDefaultAvatarSwap() {
		if (collapseAvatarTimer) {
			clearTimeout(collapseAvatarTimer);
			collapseAvatarTimer = null;
		}
		collapseAvatarTimer = setTimeout(function() {
			collapseAvatarTimer = null;
			var defaultAvatar = getDefaultVendorAvatarSrc();
			if (!defaultAvatar) return;
			var $img = $(".profile-img img").first();
			if (!$img.length || $img.attr("src") === defaultAvatar) return;
			var preload = new Image();
			preload.onload = function() {
				$img.attr("src", defaultAvatar);
			};
			preload.onerror = function() {
				$img.attr("src", defaultAvatar);
			};
			preload.src = defaultAvatar;
		}, 400);
	}

	function preloadImage(src, onDone) {
		if (!src) {
			if (onDone) onDone(false);
			return;
		}
		var img = new Image();
		img.onload = function() {
			if (onDone) onDone(true);
		};
		img.onerror = function() {
			if (onDone) onDone(false);
		};
		img.src = src;
	}

	function prefetchMediaByIndex(index) {
		if (!playlist.length) return;
		var targetIndex = index % playlist.length;
		if (targetIndex < 0) { targetIndex += playlist.length; }
		var item = playlist[targetIndex];
		if (!item || !item.src) return;
		if (mediaPrefetch.index === targetIndex && mediaPrefetch.src === item.src) return;
		mediaPrefetch.index = targetIndex;
		mediaPrefetch.type = item.type || "image";
		mediaPrefetch.src = item.src;
		if (item.type === "video") {
			warmupVideoSource(item.src);
		} else if (item.type === "audio") {
			warmupAudioSource(item.src);
		} else {
			preloadImage(item.src);
		}
		if (item.poster) {
			preloadImage(item.poster);
		}
	}

	function scheduleTalentSwapSequence(durationSeconds) {
		if (isShowcaseRotationPaused()) return;
		clearTransitionTimers();
		transitionSequenceActive = true;
		var collapseDelay = Math.max(0, (durationSeconds - 3) * 1000);
		var blackoutDelay = Math.max(0, (durationSeconds - 0.5) * 1000);
		var swapDelay = Math.max(0, durationSeconds * 1000);
		var nextIndex = getNextVendorIndex();

		transitionTimers.collapse = setTimeout(function() {
			if (!state.isPlaying || isAutoplayBlocked()) {
				clearTransitionTimers();
				stopBlackout();
				return;
			}
			setCollapsedLoadingState();
		}, collapseDelay);

		transitionTimers.blackout = setTimeout(function() {
			if (!state.isPlaying || isAutoplayBlocked()) {
				clearTransitionTimers();
				stopBlackout();
				return;
			}
			startBlackout();
			prefetchVendorByIndex(nextIndex);
		}, blackoutDelay);

		transitionTimers.swap = setTimeout(function() {
			console.log('[TM PLAYER DEBUG] swap timer fired | state.isPlaying:', state.isPlaying, '| isAutoplayBlocked():', isAutoplayBlocked());
			if (!state.isPlaying || isAutoplayBlocked()) {
				console.log('[TM PLAYER DEBUG] swap aborted - state.isPlaying:', state.isPlaying, '| isAutoplayBlocked():', isAutoplayBlocked());
				clearTransitionTimers();
				stopBlackout();
				return;
			}
			console.log('[TM PLAYER DEBUG] Starting vendor swap to index:', nextIndex);
			var preloaded = (vendorPrefetch.index === nextIndex && vendorPrefetch.data)
				? vendorPrefetch.data
				: null;
			navigateToVendor(1, {
				skipPreCollapse: true,
				keepCollapsed: true,
				holdAutoplayMs: 1000,
				expandDelayMs: 3000,
				preloadedResponse: preloaded,
				blackoutHoldMs: 1000
			});
		}, swapDelay);
	}

	// scheduleMediaSwapSequence is no longer used by the advance pipeline.
	// It is kept only for the playlist-editor reload path (saveMediaPlaylist calls loadItem directly).
	// The advance pipeline now calls loadNext() → loadItem() directly.
	function filenameFromSrc(src) {
		if (!src) return "â€”";
		try {
			var clean = src.split("?")[0];
			var parts = clean.split("/");
			return decodeURIComponent(parts[parts.length - 1]);
		} catch (e) {
			return src;
		}
	}
	function formatDuration(sec) {
		if (typeof sec !== "number" || !isFinite(sec) || sec <= 0) return "â€”";
		var m = Math.floor(sec / 60);
		var s = Math.round(sec % 60);
		return m + ":" + (s < 10 ? "0" + s : s);
	}

	function buildPlaylist() {
		console.log('[TM PLAYER DEBUG] buildPlaylist called | state.isPlaying before:', state.isPlaying, '| window.vendorMedia:', window.vendorMedia);
		var list = [];
		var showcaseMode = _playerMode === "showcase";
		console.log('[TM buildPlaylist] showcaseMode:', showcaseMode);
		if (window.vendorMedia && Array.isArray(window.vendorMedia.items)) {
			console.log('[TM PLAYER DEBUG] buildPlaylist | Found', window.vendorMedia.items.length, 'items in vendorMedia');
			list = window.vendorMedia.items.filter(function(item) { return item && item.src; });
		} else {
			console.log('[TM PLAYER DEBUG] buildPlaylist | NO items in vendorMedia');
		}
		console.log('[TM PLAYER DEBUG] buildPlaylist | Built playlist with', list.length, 'items');

		// If the vendor has any videos, prefer the first one for initial playback.
		var firstVideo = null;
		for (var i = 0; i < list.length; i++) {
			if (list[i] && list[i].type === "video") {
				firstVideo = list[i];
				break;
			}
		}

		if (!list.length && window.vendorMedia && window.vendorMedia.fallbackImage) {
			list.push({ type: "image", src: window.vendorMedia.fallbackImage, duration: 7 });
		}

		// Banner posters are rendered as <video poster="..."> (not <img src="...">).
		if (!list.length) {
			var poster = $(".profile-banner-video").first().attr("poster");
			if (poster) {
				list.push({ type: "image", src: poster, duration: 7 });
			}
		}

		if (!list.length) {
			var fallbackImg = $(".profile-info-img").first().attr("src");
			if (fallbackImg) {
				list.push({ type: "image", src: fallbackImg, duration: 7 });
			}
		}

		var priority = { image: 0, video: 1, youtube: 1, audio: 2 };
		list.sort(function(a, b) {
			return (priority[a.type] || 3) - (priority[b.type] || 3);
		});

		if (firstVideo) {
			var preferredIndex = list.indexOf(firstVideo);
			if (preferredIndex >= 0) {
				state.index = preferredIndex;
			}
		}

		if (showcaseMode && list.length && (state.index < 0 || state.index >= list.length)) {
			state.index = 0;
		}

		return list;
	}

	function vendorHasPlayableMedia(vendorMedia) {
		if (!vendorMedia) return false;
		if (Array.isArray(vendorMedia.items) && vendorMedia.items.some(function(item) { return item && item.src; })) {
			return true;
		}
		return !!vendorMedia.fallbackImage;
	}

	function currentItem() {
		if (!playlist.length) return null;
		return playlist[state.index];
	}

	// Helper functions for media control - used by loadItem and event handlers
	function updateMeta(item, overrideDuration) {
		var $heroMeta = $(".hero-media-meta").first();
		if (!$heroMeta.length || !item) return;
		var dur = typeof overrideDuration === "number" ? overrideDuration : item.duration;
		var title = (item.title && item.title.trim()) ? item.title.trim() : filenameFromSrc(item.src);
		if (title && title.length > 30) { title = title.substring(0, 30) + '\u2026'; }
		$heroMeta.find(".meta-title").text(title || "â€”");
		$heroMeta.find(".meta-type").text(item.type ? item.type.toUpperCase() : "â€”");
		$heroMeta.find(".meta-duration").text(formatDuration(dur));
		$heroMeta.find(".meta-mime").text(item.mime || "â€”");
	}

	function playbackDuration(item) {
		if (!item) return 7;
		var defaultSeconds = (item.type === "video") ? VIDEO_LOOP_SECONDS : 7;
		var base = (typeof item.duration === "number" && isFinite(item.duration) && item.duration > 0) ? item.duration : defaultSeconds;
		if (state.loopMode) return base;
		if (state.fullDuration) return base;
		var limit = (item.type === "video") ? VIDEO_LOOP_SECONDS : 7;
		return Math.min(base, limit);
	}

	function toggleHeroRemote(stateOn) {
		var $heroRemote = $(".hero-remote");
		if (!$heroRemote.length) return;
		if (stateOn) {
			// Never show hero-remote while the play overlay is visible (mutually exclusive),
			// EXCEPT on mobile when the user explicitly paused — show both.
			var $overlay = $(".tm-showcase-play-overlay");
			var isMobile = $(document.body).hasClass("tm-mobile-portrait")
				|| $(document.body).hasClass("tm-mobile-landscape");
			var allowOverlayRemote = isMobile && (userPausedMedia || $(document.body).hasClass("tm-mobile-landscape"));
			if ($overlay.length && !$overlay.hasClass("is-hidden") && !allowOverlayRemote) return;
			$heroRemote.addClass("is-visible");
		} else {
			$heroRemote.removeClass("is-visible");
		}
		updateMobileLandscapePlaybackFocusState();
	}

	function showImage(active, src) {
		var $heroImage = $(".hero-media-image").first();
		if (!$heroImage.length) return;
		if (active && src) {
			$heroImage.off(".tmheroimg");
			$heroImage.on("error.tmheroimg", function() {
				var fallback = (window.vendorMedia && window.vendorMedia.fallbackImage) ? window.vendorMedia.fallbackImage : null;
				var currentSrc = $heroImage.attr("src") || "";
				if (fallback && currentSrc !== fallback) {
					$heroImage.attr("src", fallback);
					return;
				}
				loadNext();
			});
			$heroImage.on("load.tmheroimg", function() { $heroImage.css("display", "block"); });
			$heroImage.attr("src", src);
		}
		$heroImage.css("display", active ? "block" : "none");
	}

	function showVideo(active) {
		if (!heroVideoActive) return;
		$(heroVideoActive).css("display", active ? "block" : "none");
	}

	// ── YouTube IFrame API helpers ────────────────────────────────────────────
	function showYTPlayer(active) {
		var $yt = $("#tm-yt-player");
		$yt.css("display", active ? "block" : "none");
		// Add/remove the is-youtube-mode class on hero-remote so the progress row shows/hides
		$(".hero-remote").toggleClass("is-youtube-mode", !!active);
		if (active) {
			// Re-enforce iframe dimensions each time we show the player — the YT API
			// may have initially created the iframe at 640×390 (default) if the
			// container was hidden at construction time.
			var iframe = $yt.find("iframe").first()[0];
			if (iframe) {
				iframe.style.width    = "100%";
				iframe.style.height   = "100%";
				iframe.style.position = "absolute";
				iframe.style.top      = "0";
				iframe.style.left     = "0";
			}
		}
	}

	function extractYoutubeId(url) {
		if (!url) return null;
		var m = url.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
		return m ? m[1] : null;
	}

	function loadYouTubeAPI() {
		if (window.YT && window.YT.Player) { return; }
		if (ytApiLoading) { return; }
		ytApiLoading = true;
		var tag = document.createElement("script");
		tag.src = "https://www.youtube.com/iframe_api";
		document.head.appendChild(tag);
	}

	function initYTPlayer(videoId) {
		if (!videoId) return;
		if (ytPlayer && typeof ytPlayer.loadVideoById === "function") {
			if (ytCurrentId === videoId) {
				// Same video — resume from current position (don't restart)
				try { ytPlayer.playVideo(); } catch(e) {}
			} else {
				// Different video — load fresh
				ytCurrentId = videoId;
				try { ytPlayer.loadVideoById(videoId); } catch(e) {}
			}
			return;
		}
		// YT.Player replaces the target element — recreate the div each time if needed.
		var $container = $("#tm-yt-player");
		if (!$container.length) { return; }
		// Make visible briefly so the browser resolves layout dimensions before YT.Player
		// reads the element size — otherwise it defaults to 640×390.
		$container.css({ display: "block", visibility: "hidden" });
		// Grab the resolved pixel dimensions from the containing block.
		var w = $container[0].offsetWidth  || $(".profile-frame")[0].offsetWidth  || window.innerWidth;
		var h = $container[0].offsetHeight || $(".profile-frame")[0].offsetHeight || window.innerHeight;
		$container.css({ visibility: "" });
		// Recreate a fresh inner div + cinematic overlay so YT.Player can replace
		// the inner div while the overlay stays as a sibling above the iframe.
		// The overlay intercepts all pointer interaction with YouTube's native UI
		// (title bar, end-cards, logo) — playback is driven via the JS API only.
		$container.html('<div id="tm-yt-player-inner"></div><div class="tm-yt-overlay" aria-hidden="true"></div>');
		ytCurrentId = videoId;
		ytPlayer = new YT.Player("tm-yt-player-inner", {
			videoId: videoId,
			width:  w,
			height: h,
			playerVars: {
				autoplay:       1,
				controls:       0,   // hide YouTube's native control bar entirely
				disablekb:      1,   // our player handles keyboard
				fs:             0,   // our player handles fullscreen
				iv_load_policy: 3,   // hide video annotations / info cards
				rel:            0,   // related videos from same channel only
				playsinline:    1,
				origin:         window.location.origin
			},
			events: {
				onReady: function(e) {
					// Force the iframe to fill its container regardless of the pixel size
					// YT.Player set on the element — CSS then stretches it to 100%×100%.
					var iframe = $container.find("iframe").first()[0];
					if (iframe) {
						iframe.style.width  = "100%";
						iframe.style.height = "100%";
						iframe.style.position = "absolute";
						iframe.style.top  = "0";
						iframe.style.left = "0";
					}
					if (!state.muted) { try { e.target.unMute(); } catch(ex) {} }
					try { e.target.playVideo(); } catch(ex) {}
				},
				onStateChange: function(e) {
					/* global YT */
					if (typeof YT === "undefined") return;
					if (e.data === YT.PlayerState.PLAYING) {
						// Sync play state and start progress polling
						startYTProgressPolling();
						syncRemotePlaying(true);
					} else if (e.data === YT.PlayerState.PAUSED) {
						// Stop polling — play state is managed by our own pauseCurrent()/playCurrent()
						stopYTProgressPolling();
					} else if (e.data === YT.PlayerState.ENDED) {
						stopYTProgressPolling();
						// Defer loadNext() via setTimeout to avoid calling stopMedia() /
						// ytPlayer.pauseVideo() re-entrantly inside the YT API callback,
						// which can corrupt the IFrame API state and stall playback.
						if (state.loopMode) {
							setTimeout(function() {
								try { e.target.seekTo(0); e.target.playVideo(); } catch(ex) {}
							}, 0);
						} else {
							setTimeout(function() {
								state.isPlaying = true;
								loadNext();
							}, 0);
						}
					}
				}
			}
		});
	}

	function playYoutubeId(videoId) {
		if (window.YT && window.YT.Player) {
			initYTPlayer(videoId);
		} else {
			ytPendingId = videoId;
			loadYouTubeAPI();
		}
	}

	// ── YouTube progress bar helpers ──────────────────────────────────────────

	function formatYTTime(seconds) {
		if (!isFinite(seconds) || seconds < 0) return "0:00";
		var s = Math.floor(seconds);
		var h = Math.floor(s / 3600);
		var m = Math.floor((s % 3600) / 60);
		var sec = s % 60;
		if (h > 0) {
			return h + ":" + (m < 10 ? "0" : "") + m + ":" + (sec < 10 ? "0" : "") + sec;
		}
		return m + ":" + (sec < 10 ? "0" : "") + sec;
	}

	function updateYTProgressBar() {
		if (ytSeeking) return;   // don't override the bar while user is dragging
		if (!ytPlayer || typeof ytPlayer.getCurrentTime !== "function") return;
		try {
			var cur = ytPlayer.getCurrentTime() || 0;
			var dur = ytPlayer.getDuration() || 0;
			// Auto-advance before end-screens appear: YouTube shows recommendation
			// cards in the last ~20 seconds. Seek to ENDED immediately so our
			// onStateChange.ENDED handler fires and loadNext() is called.
			if (dur > YT_ENDSCREEN_SKIP_THRESHOLD && (dur - cur) <= YT_ENDSCREEN_SKIP_THRESHOLD) {
				ytPlayer.seekTo(dur, true);
				return;
			}
			var $remote = $(".hero-remote");
			if (!$remote.length) return;
			var pct = dur > 0 ? (cur / dur) * 1000 : 0;
			var fillPct = dur > 0 ? (cur / dur * 100) : 0;
			$remote.find(".hero-yt-seek").val(pct);
			$remote.find(".hero-yt-progress-fill").css("width", fillPct + "%");
			$remote.find(".hero-yt-seek-thumb").css("left", fillPct + "%");
			$remote.find(".hero-yt-time-current").text(formatYTTime(cur));
			$remote.find(".hero-yt-time-duration").text(formatYTTime(dur));
		} catch(e) {}
	}

	function startYTProgressPolling() {
		stopYTProgressPolling();
		updateYTProgressBar();
		ytProgressInterval = setInterval(updateYTProgressBar, 333);
	}

	function stopYTProgressPolling() {
		if (ytProgressInterval) {
			clearInterval(ytProgressInterval);
			ytProgressInterval = null;
		}
		if (ytSeekResumeTimeout) {
			clearTimeout(ytSeekResumeTimeout);
			ytSeekResumeTimeout = null;
		}
	}

	// OPTIMIZATION: Loading indicator for slow/unstable connections
	function showLoadingIndicator(show, percent) {
		if (!show) {
			if (loadingIndicator) {
				loadingIndicator.remove();
				loadingIndicator = null;
			}
			return;
		}
		
		if (!loadingIndicator) {
			loadingIndicator = $('<div class="tm-loading-indicator"><div class="tm-loading-spinner"></div><div class="tm-loading-text">Loading media...</div></div>');
			$('.profile-banner-video').first().parent().append(loadingIndicator);
		}
		
		if (typeof percent === 'number') {
			var $loadingText = loadingIndicator.find('.tm-loading-text');
			if ($loadingText.length) {
				$loadingText.text('Loading media... ' + Math.round(percent) + '%');
			}
		}
	}
	
	function updateLoadingIndicator(percent) {
		showLoadingIndicator(true, percent);
	}

	function unbindSlowLoadingEvents(videoEl) {
		if (!videoEl || !videoEl.__tmSlowLoadingHandlers) return;
		videoEl.removeEventListener('progress', videoEl.__tmSlowLoadingHandlers.progress);
		videoEl.removeEventListener('canplay', videoEl.__tmSlowLoadingHandlers.canplay);
		videoEl.removeEventListener('playing', videoEl.__tmSlowLoadingHandlers.playing);
		videoEl.removeEventListener('error', videoEl.__tmSlowLoadingHandlers.error);
		delete videoEl.__tmSlowLoadingHandlers;
	}

	function bindSlowLoadingEvents(videoEl) {
		if (!videoEl) return;
		unbindSlowLoadingEvents(videoEl);
		videoEl.__tmSlowLoadingHandlers = {
			progress: function() {
				var buffered = videoEl.buffered;
				if (buffered.length > 0 && videoEl.duration) {
					var bufferPercent = (buffered.end(0) / videoEl.duration) * 100;
					updateLoadingIndicator(bufferPercent);
				}
			},
			canplay: function() {
				showLoadingIndicator(false);
			},
			playing: function() {
				showLoadingIndicator(false);
			},
			error: function() {
				showLoadingIndicator(false);
			}
		};
		videoEl.addEventListener('progress', videoEl.__tmSlowLoadingHandlers.progress);
		videoEl.addEventListener('canplay', videoEl.__tmSlowLoadingHandlers.canplay);
		videoEl.addEventListener('playing', videoEl.__tmSlowLoadingHandlers.playing);
		videoEl.addEventListener('error', videoEl.__tmSlowLoadingHandlers.error);
	}

	function showEq(active) {
		var $heroEq = $(".hero-audio-eq").first();
		if (!$heroEq.length) return;
		$heroEq.toggleClass("is-active", !!active);
	}

	function persistPlaybackPrefs() {
		writeStoredBool(STORAGE_KEYS.muted, !!state.muted);
		writeStoredBool(STORAGE_KEYS.fullDuration, !!state.fullDuration);
		writeStoredBool(STORAGE_KEYS.loopMode, !!state.loopMode);
		writeStoredBool(STORAGE_KEYS.vendorLoop, !!vendorLoopEnabled);
	}

	// Smart TV loop-blob helpers -----------------------------------------------
	// Fetch src once into a Blob URL so every loop iteration is served from memory,
	// eliminating the 2-3 s network/decoder stall that occurs even with preload="auto".
	function acquireSmartTVLoopBlob(src) {
		if (!src) return;
		// Already cached for this src — nothing to do.
		if (smartTvLoopOrigSrc === src && smartTvLoopBlobUrl) return;
		// Release any previous cache first.
		releaseSmartTVLoopBlob();
		smartTvLoopOrigSrc = src;
		var hasFetch = typeof window.fetch === "function";
		var hasXHR   = typeof XMLHttpRequest !== "undefined";
		if (!hasFetch && !hasXHR) return;
		if (hasFetch && typeof AbortController !== "undefined") {
			smartTvLoopFetchCtrl = new AbortController();
		}
		var onBlob = function(blob) {
			// Bail out if the user already switched media or turned off loop.
			if (smartTvLoopOrigSrc !== src) return;
			if (!state.loopMode || !heroVideoActive) return;
			smartTvLoopBlobUrl = URL.createObjectURL(blob);
			// Swap to the Blob URL so all subsequent loop iterations read from memory.
			var wasPaused = heroVideoActive.paused;
			var curTime   = heroVideoActive.currentTime || 0;
			heroVideoActive.src = smartTvLoopBlobUrl;
			heroVideoActive.load();
			heroVideoActive.currentTime = curTime;
			if (!wasPaused && state.isPlaying) {
				attemptPlay(heroVideoActive, false);
			}
		};
		if (hasFetch) {
			var opts = smartTvLoopFetchCtrl ? { signal: smartTvLoopFetchCtrl.signal } : {};
			window.fetch(src, opts)
				.then(function(r) { return r.blob(); })
				.then(onBlob)
				["catch"](function() {});
		} else {
			// XHR fallback for older Smart TV browsers without fetch.
			var xhr = new XMLHttpRequest();
			xhr.open("GET", src, true);
			xhr.responseType = "blob";
			xhr.onload = function() { if (xhr.status === 200) onBlob(xhr.response); };
			xhr.onerror = function() {};
			xhr.send();
			smartTvLoopFetchCtrl = { abort: function() { xhr.abort(); } };
		}
	}
	function releaseSmartTVLoopBlob() {
		if (smartTvLoopFetchCtrl) {
			try { smartTvLoopFetchCtrl.abort(); } catch(e) {}
			smartTvLoopFetchCtrl = null;
		}
		if (smartTvLoopBlobUrl) {
			try { URL.revokeObjectURL(smartTvLoopBlobUrl); } catch(e) {}
			smartTvLoopBlobUrl = null;
		}
		// Restore original HTTP src on the active element so normal navigation works.
		if (smartTvLoopOrigSrc && heroVideoActive) {
			if (heroVideoActive.src !== smartTvLoopOrigSrc) {
				heroVideoActive.src = smartTvLoopOrigSrc;
			}
		}
		smartTvLoopOrigSrc = null;
	}
	// ---------------------------------------------------------------------------

	function applyLoopMode(item) {
		var $heroAudio = $(".hero-audio").first();
		var isVideo = item && item.type === "video" && heroVideoActive;
		var isAudio = item && item.type === "audio" && $heroAudio.length;
		if (isVideo) {
			// Opt 4 (Smart TV): when looping is active, force preload="auto" so the browser
			// keeps the full file in its decode buffer across iterations. Without this,
			// the TV evicts buffered data between loops causing stutter.
			if (isSmartTV() && state.loopMode) {
				heroVideoActive.preload = "auto";
				// Fetch the video into a Blob URL so every loop iteration is served from
				// memory — eliminates the 2-3 s stall that preload="auto" alone can’t prevent
				// because the TV browser evicts buffered segments after the ended event.
				acquireSmartTVLoopBlob(item ? item.src : (heroVideoActive.src || null));
			} else if (isSmartTV() && !state.loopMode) {
				// Loop turned off — free the Blob from memory and restore the HTTP src.
				releaseSmartTVLoopBlob();
			}
			heroVideoActive.loop = !!state.loopMode;
			if (!state.loopMode) { heroVideoActive.removeAttribute("loop"); }
		}
		if (isAudio) {
			$heroAudio.prop("loop", !!state.loopMode);
			if (!state.loopMode) { $heroAudio.removeAttr("loop"); }
		}
	}

	function syncRemotePlaying(forceVal) {
		var $heroRemote = $(".hero-remote");
		var playing = typeof forceVal === "boolean" ? forceVal : state.isPlaying;
		if (isAutoplayBlocked()) {
			playing = false;
		}
		$heroRemote.toggleClass("is-playing", !!playing);
		syncPlayOverlay();
	}

	function syncRemoteMute() {
		var $heroRemote = $(".hero-remote");
		$heroRemote.toggleClass("is-muted", !!state.muted);
	}

	// Show/hide the play overlay and enforce mutual exclusion with hero-remote.
	// Overlay is visible whenever the media is not actively playing.
	// Exception (mobile/tablet + user-initiated pause): show BOTH overlay and
	// hero-remote so the user can see controls without dismissing the overlay.
	var userPausedMedia = false;  // set true only when the user explicitly pauses
	var browserForcedMute = false; // set true when the strip is visible (browser suppressed audio)
	var unmuteStripTimer = null;

	function ensureUnmuteStrip($heroBox) {
		if (!$heroBox || !$heroBox.length) return;
		// Reset state from any previous talent before creating a fresh element.
		if (unmuteStripTimer) { clearTimeout(unmuteStripTimer); unmuteStripTimer = null; }
		browserForcedMute = false;
		if ($heroBox.find(".tm-unmute-strip").length) return;
		var $strip = $(
			"<div class=\"tm-unmute-strip is-hidden\" role=\"button\" tabindex=\"0\" aria-label=\"Tap to unmute\">" +
			"<span class=\"tm-unmute-icon\" aria-hidden=\"true\"><i class=\"fas fa-volume-mute\"></i></span>" +
			"<span class=\"tm-unmute-label\">Tap to unmute</span>" +
			"</div>"
		);
		$heroBox.append($strip);
		$strip.on("click keydown", function(e) {
			if (e.type === "keydown" && e.key !== "Enter" && e.key !== " ") return;
			markUserInteraction();
			browserForcedMute = false;
			state.muted = false;
			writeStoredBool(STORAGE_KEYS.muted, false);
			if (heroVideoActive) { heroVideoActive.muted = false; }
			var $heroAudio = $(".hero-audio").first();
			if ($heroAudio.length) { $heroAudio[0].muted = false; }
			syncRemoteMute();
			hideUnmuteStrip();
		});
	}

	function showUnmuteStrip() {
		if (state.muted) return; // user explicitly muted — strip would be confusing
		// Once the user has explicitly tapped Play (userHasInteracted=true), the
		// Netflix muted→unmuted flip succeeds silently and audio is already live.
		// Showing the strip at that point is a false alarm — suppress it.
		if (userHasInteracted) return;
		var $strip = $(".tm-unmute-strip");
		if (!$strip.length) return;
		browserForcedMute = true;
		$strip.removeClass("is-hidden");
		if (unmuteStripTimer) { clearTimeout(unmuteStripTimer); }
		unmuteStripTimer = setTimeout(hideUnmuteStrip, 8000);
	}

	function hideUnmuteStrip() {
		if (unmuteStripTimer) { clearTimeout(unmuteStripTimer); unmuteStripTimer = null; }
		browserForcedMute = false;
		$(".tm-unmute-strip").addClass("is-hidden");
	}

	function updateMobileLandscapePlaybackFocusState() {
		var $body = $(document.body);
		var isLandscape = $body.hasClass("tm-mobile-landscape");
		var overlayVisible = $(".tm-showcase-play-overlay").length > 0 && !$(".tm-showcase-play-overlay").hasClass("is-hidden");
		var remoteVisible = $(".hero-remote").hasClass("is-visible") || $(".hero-remote").hasClass("is-audio-mode");
		var blockedByOtherFocus = $body.hasClass("tm-talent-panel-open") || $body.hasClass("tm-mobile-drawer-open");
		var shouldFocus = isLandscape && !blockedByOtherFocus && overlayVisible && remoteVisible;
		$body.toggleClass("tm-mobile-landscape-play-focus", shouldFocus);
	}

	function syncPlayOverlay() {
		var $overlay = $(".tm-showcase-play-overlay");
		if (!$overlay.length) return;
		var showOverlay = !state.isPlaying || isAutoplayBlocked();
		
		// UNIFIED BEHAVIOR: Single source of truth for UI visibility
		if (showOverlay) {
			// Video PAUSED: Show play overlay, hide hero-remote
			$overlay.removeClass("is-hidden");
			$(".hero-remote").removeClass("is-visible");
			console.log('[TM PLAYER DEBUG] syncPlayOverlay | Video PAUSED - Show overlay, hide remote');
		} else {
			// Video PLAYING: Hide play overlay, hero-remote will show on hover
			$overlay.addClass("is-hidden");
			// Don't auto-show hero-remote - it should only appear on hover
			console.log('[TM PLAYER DEBUG] syncPlayOverlay | Video PLAYING - Hide overlay, remote on hover only');
		}
		updateMobileLandscapePlaybackFocusState();
	}

	function disableBackgroundMedia() {
		console.log('[TM PLAYER DEBUG] disableBackgroundMedia called - setting state.isPlaying = false');
		state.isPlaying = false;
		state.loopMode = false;
		state.fullDuration = false;
		vendorLoopEnabled = false;
		releaseSmartTVLoopBlob();
		clearMediaTimer();
		stopMedia();
		updateVendorLoopButton();
		$(".hero-remote").remove();
	}

	function attemptPlay(el, allowMuteFallback) {
		if (!el) return;
		try {
			// Netflix technique: when the user wants sound, always start muted (browsers never
			// block muted autoplay), then flip .muted = false on the already-running element.
			// Browsers treat a .muted change on a running element as a property assignment —
			// NOT a new play() call — so no activation check fires.
			// Result: ~99% unmuted autoplay on Chrome/Edge desktop after the first user gesture.
			var wantSound = allowMuteFallback && !state.muted;
			if (wantSound) { el.muted = true; } // start muted so play() is guaranteed to succeed
			var p = el.play();
			if (p && typeof p.then === "function") {
				p.then(function() {
					if (wantSound) {
						el.muted = false; // flip on already-running element — no new activation check
						syncRemoteMute();
						if (isMobileDevice()) {
							// Mobile browsers may suppress audio even after the .muted flip.
							// Show the strip as a one-tap affordance to confirm unmute.
							showUnmuteStrip();
						}
					}
				}, function() {
					// Even muted autoplay was blocked (iOS strict mode, sandboxed iframe, etc.).
					if (!allowMuteFallback) return;
					// In showcase mode, after the user has clicked Play once, don't pop the
					// overlay on a transient rejection — the advance timer keeps the loop running.
					if (isShowcaseMode() && hasShowcaseStarted()) return;
					console.log('[TM PLAYER DEBUG] attemptPlay failure - setting state.isPlaying = false');
					state.isPlaying = false;
					syncRemotePlaying(false); // restore play overlay so user can tap to start
				});
			}
		} catch (e) {}
	}

	function stopMedia() {
		var $heroAudio = $(".hero-audio").first();
		clearMediaTimer();
		clearAdvanceTimer();
		if (heroVideoActive) { heroVideoActive.pause(); }
		if ($heroAudio.length) { $heroAudio[0].pause(); }
		if (ytPlayer && typeof ytPlayer.pauseVideo === "function") { try { ytPlayer.pauseVideo(); } catch(e) {} }
		stopYTProgressPolling();
		showYTPlayer(false);
		showEq(false);
	}

	function loadItem(targetIndex) {
		console.log('[TM PLAYER DEBUG] loadItem called | targetIndex:', targetIndex, '| playlist.length:', playlist.length);
		var $heroAudio = $(".hero-audio").first();
		var $heroRemote = $(".hero-remote");
		
		if (!playlist.length) {
			console.log('[TM PLAYER DEBUG] loadItem - NO PLAYLIST, stopping media');
			stopMedia();
			return;
		}
		clearTransitionTimers();
		clearMediaTimer();
		clearAdvanceTimer();
		targetIndex = targetIndex % playlist.length;
		if (targetIndex < 0) { targetIndex += playlist.length; }
		state.index = targetIndex;
		var item = currentItem();
		if (!item) return;
		state.type = item.type || "video";
		stopMedia();
		applyMute();
		if ($heroRemote.length) {
			var keepVisible = $heroRemote.is(":hover");
			if (state.type === "audio") {
				$heroRemote.addClass("is-audio-mode is-visible");
			} else {
				$heroRemote.removeClass("is-audio-mode");
				if (keepVisible) {
					$heroRemote.addClass("is-visible");
				} else {
					$heroRemote.removeClass("is-visible");
				}
			}
		}
		if (state.type === "video") {
			showImage(false);
			// Check if target src is already preloaded in a buffer slot
			var currentActiveEl = heroVideoActive;
			var useBufferEl = null;
			var consumedKey = null;
			if (videoBufferNext && bufferNextSrc && bufferNextSrc === item.src) {
				useBufferEl = videoBufferNext;
				consumedKey = "next";
			} else if (videoBufferPrev && bufferPrevSrc && bufferPrevSrc === item.src) {
				useBufferEl = videoBufferPrev;
				consumedKey = "prev";
			}
			if (useBufferEl && useBufferEl !== currentActiveEl) {
				// === A/B BUFFER SWAP ===
				// Target src is already loaded — no src change needed during the gesture.
				// Calling play() on a pre-loaded element keeps the activation token intact,
				// allowing unmuted playback reliably across Chrome, Edge, and mobile.
				useBufferEl.muted = !!state.muted;
				useBufferEl.currentTime = 0;
				if (item.poster) { useBufferEl.setAttribute("poster", item.poster); }
				else { useBufferEl.removeAttribute("poster"); }
				// Ring-buffer rotation: each slot takes the next role in the ring.
				var freedEl    = currentActiveEl;
				var oldNextEl  = videoBufferNext;
				var oldPrevEl  = videoBufferPrev;
				if (consumedKey === "next") {
					videoBufferNext = oldPrevEl;  // old prev → new next buffer (reload w/ N+2)
					videoBufferPrev = freedEl;    // freed active → new prev buffer (reload w/ N)
				} else {
					videoBufferPrev = oldNextEl;  // old next → new prev buffer (reload w/ N-2)
					videoBufferNext = freedEl;    // freed active → new next buffer (reload w/ N)
				}
				bufferNextSrc = "";
				bufferPrevSrc = "";
				// Swap visibility with a brief 120 ms crossfade
				setVideoSlotActive(useBufferEl, currentActiveEl);
				// heroVideoActive now points to useBufferEl
				bindVideoEvents(heroVideoActive, item);
				updateMeta(item);
				applyLoopMode(item);
				if (canAutoplay()) {
					attemptPlay(heroVideoActive, allowMuteFallbackForPlayback());
					armAdvanceTimer(item);
				}
				showEq(false);
				scheduleBufferPreloads(targetIndex);
			} else {
				// === FALLBACK: traditional src-change ===
				// Used on first load, or if the buffer wasn't preloaded in time.
				showVideo(true);
				if (heroVideoActive) {
					// OPTIMIZATION: For slow connections, use metadata preload to show poster immediately
					// and allow progressive buffering without blocking initial display
					var isSlowConnection = isSlowNetworkConnection();
					var preloadValue = isSlowConnection ? 'metadata' : ((isSmartTV() || isMobileDevice()) ? 'auto' : 'metadata');
					$(heroVideoActive).attr("preload", preloadValue);
					
					// Show loading indicator for slow connections
					if (isSlowConnection) {
						showLoadingIndicator(true);
					}
					
					heroVideoActive.loop = false;
					heroVideoActive.removeAttribute("loop");
					if (item.poster) { $(heroVideoActive).attr("poster", item.poster); }
					else { $(heroVideoActive).removeAttr("poster"); }
					// Normalise URLs before comparing: el.src is always absolute while item.src
					// may be relative, making !== always true on Smart TVs and mobile browsers.
					var _srcChanged = (isSmartTV() || isMobileDevice())
						? !tmSameSrc(heroVideoActive.src, item.src)
						: heroVideoActive.src !== item.src;
					if (_srcChanged) {
						heroVideoActive.src = item.src;
						heroVideoActive.load();
					}
					
					if (isSlowConnection) {
						bindSlowLoadingEvents(heroVideoActive);
					} else {
						unbindSlowLoadingEvents(heroVideoActive);
						showLoadingIndicator(false);
					}
					
					updateMeta(item);
					applyLoopMode(item);
					if (canAutoplay()) {
						attemptPlay(heroVideoActive, allowMuteFallbackForPlayback());
						armAdvanceTimer(item);
					}
					bindVideoEvents(heroVideoActive, item);
				}
				showEq(false);
				scheduleBufferPreloads(targetIndex);
			}
		}
		else if (state.type === "image") {
			showVideo(false);
			showImage(true, item.src);
			showEq(false);
			var imgDur = playbackDuration(item);
			updateMeta(item, imgDur);
			if (canAutoplay() && !state.loopMode) {
				armAdvanceTimer(item);
			}
			syncRemotePlaying(state.isPlaying);
		}
		else if (state.type === "youtube") {
			// Do NOT call showVideo(false) — rely solely on z-index to cover the video
			// slot. Hiding heroVideoActive causes the A/B swap return path to show a
			// black frame before the slot z-index is restored.
			stopBlackout(); // clear any lingering transition blackout immediately
			showImage(false);
			showEq(false);
			showYTPlayer(true);
			var ytId = item.youtube_id || extractYoutubeId(item.src);
			if (ytId) { playYoutubeId(ytId); }
			updateMeta(item);
			syncRemotePlaying(true);
			// Preload next/prev non-YouTube items so the A/B buffer is warm when
			// playback returns to a hosted video after this YouTube item.
			scheduleBufferPreloads(targetIndex);
		}
		else { // audio
			showVideo(false);
			showImage(false);
			if ($heroAudio.length) {
				if ($heroAudio.attr("src") !== item.src) {
					$heroAudio.attr("src", item.src);
					$heroAudio[0].load();
				}
				updateMeta(item);
				$heroAudio.off("ended.tmhero play.tmhero pause.tmhero volumechange.tmhero loadedmetadata.tmhero");
				$heroAudio.on("ended.tmhero", function() {
					if (state.loopMode) return;
					// Same logic as video: natural end always advances; respect only explicit pause.
					if (userPausedMedia) return;
					state.isPlaying = true;
					// Audio ended naturally before timer: advance now.
					clearAdvanceTimer();
					loadNext();
				});
				$heroAudio.on("play.tmhero", function() {
					showEq(true);
					enforcePreferredUnmute($heroAudio[0]);
					syncRemotePlaying(true);
				});
				$heroAudio.on("pause.tmhero", function() { showEq(false); syncRemotePlaying(false); });
				$heroAudio.on("volumechange.tmhero", function() { syncMutedFromElement($heroAudio[0]); });
				$heroAudio.on("loadedmetadata.tmhero", function() {
					if ($heroAudio[0].duration) {
						updateMeta(item, Math.round($heroAudio[0].duration));
						onMediaDurationKnown(item, $heroAudio[0].duration);
					}
				});
				applyLoopMode(item);
				if (canAutoplay()) {
					attemptPlay($heroAudio[0], allowMuteFallbackForPlayback());
					showEq(true);
					armAdvanceTimer(item);
				}
			}
		}

		syncRemoteMute();

		if (playlist.length > 1) {
			var nextIndex = state.index + 1;
			if (nextIndex >= playlist.length) {
				nextIndex = 0;
			}
			prefetchMediaByIndex(nextIndex);
		}
	}

	function playCurrent() {
		state.isPlaying = true;
		userPausedMedia = false;
		if (isAutoplayBlocked()) {
			syncRemotePlaying(false);
			return;
		}
		var item = currentItem();
		if (!item) return;
		var $heroAudio = $(".hero-audio").first();
		applyMute();
		if (item.type === "video" && heroVideoActive) {
			applyLoopMode(item);
			attemptPlay(heroVideoActive, allowMuteFallbackForPlayback());
			// Arm advance timer now — it wasn't armed in loadItem because
			// canAutoplay() was false (state.isPlaying was false at that point).
			if (!advanceTimer && !advanceItemSrc) {
				armAdvanceTimer(item);
			}
		}
		else if (item.type === "audio" && $heroAudio.length) {
			applyLoopMode(item);
			attemptPlay($heroAudio[0], allowMuteFallbackForPlayback());
			showEq(true);
			if (!advanceTimer && !advanceItemSrc) {
				armAdvanceTimer(item);
			}
		}
		else if (item.type === "image") {
			if (!advanceTimer && !advanceItemSrc) {
				armAdvanceTimer(item);
			}
			syncRemotePlaying(true);
			return;
		}
		else if (item.type === "youtube") {
			var ytIdResume = item.youtube_id || extractYoutubeId(item.src);
			if (ytIdResume) {
				if (ytPlayer && typeof ytPlayer.playVideo === "function" && ytCurrentId === ytIdResume) {
					// Resume the same video from where we paused
					try { ytPlayer.playVideo(); } catch(e) {}
				} else {
					playYoutubeId(ytIdResume);
				}
			}
			syncRemotePlaying(true);
			return;
		}
		// Do not synchronously downgrade playback state here for video/audio.
		// On vendor swaps the browser often resolves play() asynchronously after the
		// new source is attached; checking paused immediately can incorrectly flip
		// state.isPlaying back to false and resurrect the play overlay between vendors.
		// Real failure paths already flow through attemptPlay rejection, media error,
		// pause handlers, and autoplay-block checks.
		syncRemotePlaying(true);
	}

	function pauseCurrent(fromUser) {
		state.isPlaying = false;
		if (fromUser) { userPausedMedia = true; }
		var $heroAudio = $(".hero-audio").first();
		if (heroVideoActive) { heroVideoActive.pause(); }
		if ($heroAudio.length) { $heroAudio[0].pause(); }
		if (ytPlayer && typeof ytPlayer.pauseVideo === "function") { try { ytPlayer.pauseVideo(); } catch(e) {} }
		stopYTProgressPolling();
		clearTransitionTimers();
		stopBlackout();
		clearMediaTimer();
		clearAdvanceTimer();
		showEq(false);
		syncRemotePlaying(false);
	}

	function suspendBackgroundForEditing() {
		if (editSuspendState.count === 0) {
			editSuspendState.wasPlaying = !!state.isPlaying;
			editSuspendState.vendorLoopEnabled = vendorLoopEnabled;
			pauseCurrent();
			if (vendorLoopEnabled) {
				vendorLoopEnabled = false;
				writeStoredBool(STORAGE_KEYS.vendorLoop, false);
				updateVendorLoopButton();
			}
		}
		editSuspendState.count += 1;
	}

	function resumeBackgroundAfterEditing() {
		if (editSuspendState.count <= 0) return;
		editSuspendState.count -= 1;
		if (editSuspendState.count > 0) return;
		if (editSuspendState.vendorLoopEnabled !== null) {
			vendorLoopEnabled = !!editSuspendState.vendorLoopEnabled;
			writeStoredBool(STORAGE_KEYS.vendorLoop, vendorLoopEnabled);
			updateVendorLoopButton();
		}
		if (editSuspendState.wasPlaying) {
			playCurrent();
		}
		editSuspendState.wasPlaying = false;
		editSuspendState.vendorLoopEnabled = null;
	}

	window.tmSuspendBackgroundForEditing = suspendBackgroundForEditing;
	window.tmResumeBackgroundAfterEditing = resumeBackgroundAfterEditing;
	window.tmPauseShowcaseRotation = pauseShowcaseRotation;
	window.tmResumeShowcaseRotation = resumeShowcaseRotation;
	window.tmIsShowcaseRotationPaused = isShowcaseRotationPaused;

	function toggleMute() {
		state.muted = !state.muted;
		if (!state.muted) { hideUnmuteStrip(); } // unmuting via button — dismiss the strip
		applyMute();
		syncRemoteMute();
		writeStoredBool(STORAGE_KEYS.muted, state.muted);
	}

	function requestFullscreen() {
		var el = document.documentElement;
		if (el && el.requestFullscreen) {
			return el.requestFullscreen();
		}
		return Promise.resolve();
	}

	function exitFullscreen() {
		if (document.fullscreenElement && document.exitFullscreen) {
			return document.exitFullscreen();
		}
		return Promise.resolve();
	}

	function updateFullscreenButton() {
		var $btn = $(".hero-global-fullscreen");
		if (!$btn.length) return;
		var isFs = !!document.fullscreenElement;
		var label = isFs ? "Exit full screen (Esc)" : "Full screen (F11, Esc to exit)";
		$btn.attr("aria-pressed", isFs ? "true" : "false");
		$btn.attr("aria-label", label);
		$btn.attr("title", label);
		$btn.toggleClass("is-active", isFs);
	}

	function updateTheatreButton() {
		var $btn = $(".hero-global-theatre");
		if (!$btn.length) return;
		var label = isTheatreMode ? "Exit theatre mode (Esc)" : "Theatre mode (Esc to exit)";
		$btn.attr("aria-pressed", isTheatreMode ? "true" : "false");
		$btn.attr("aria-label", label);
		$btn.attr("title", label);
		$btn.toggleClass("is-active", isTheatreMode);
	}

	var fullscreenHintTimer = null;
	function showFullscreenHint() {
		if (!isHandheldViewport()) return;
		var $hint = $(".tm-fullscreen-hint");
		if (!$hint.length) {
			$hint = $("<div class=\"tm-fullscreen-hint\" role=\"status\" aria-live=\"polite\">Tap anywhere to exit</div>");
			$(document.body).append($hint);
		}
		$hint.addClass("is-visible");
		clearTimeout(fullscreenHintTimer);
		fullscreenHintTimer = setTimeout(function() {
			$hint.removeClass("is-visible");
		}, 5000);
	}

	function setTheatreMode(enabled) {
		isTheatreMode = !!enabled;
		$("body").toggleClass("theatre-mode", isTheatreMode);
		updateTheatreButton();
	}

	function bindFullscreenListeners() {
		if (fullscreenListenerBound) return;
		fullscreenListenerBound = true;
		document.addEventListener("fullscreenchange", function() {
			var isFs = !!document.fullscreenElement;
			$(document.body).toggleClass("tm-fullscreen", isFs);
			if (!isFs && isTheatreMode) {
				setTheatreMode(false);
			}
			updateFullscreenButton();
			if (!isFs) {
				updateTheatreButton();
			}
		});
	}

	function updateVendorLoopButton() {
		var $btn = $(".keyboard-nav-loop");
		if (!$btn.length) return;
		if (!isShowcaseMode() && !isMobileDevice()) {
			// Loop button is a showcase-only concept — hide it on direct profile pages
			// (but keep visible on mobile/tablet, where it acts as a media-loop toggle).
			$btn.hide();
			return;
		}
		$btn.show();
		$btn.toggleClass("is-active", vendorLoopEnabled);
		$btn.attr("aria-pressed", vendorLoopEnabled ? "true" : "false");
		$btn.attr("title", vendorLoopEnabled
			? "Talent loop on (repeat this talent media)"
			: "Talent loop off (advance to next talent)");
		updateResumeShowcaseControl();
	}

	// goNext: manual forward navigation (remote buttons, keyboard, swipe-down).
	// The advance pipeline now uses loadNext() directly — goNext is for user actions.
	function goNext() {
		if (!playlist.length) return;
		clearTransitionTimers();
		clearAdvanceTimer();
		stopBlackout();
		var nextIndex = state.index + 1;
		if (nextIndex >= playlist.length) {
			if (vendorList.length && currentVendorIndex >= 0) {
				if (lastManualSwipeAt && Date.now() - lastManualSwipeAt < 1500) {
					pauseCurrent();
					return;
				}
				navigateToVendor(1);
				return;
			}
			loadItem(0);
			return;
		}
		loadItem(nextIndex);
	}

	// Manual swipe down should wrap media, not jump vendors at the end.
	function goNextManual() {
		if (!playlist.length) return;
		clearTransitionTimers();
		var nextIndex = state.index + 1;
		if (nextIndex >= playlist.length) {
			loadItem(0);
			return;
		}
		loadItem(nextIndex);
	}

	function goPrev() {
		if (!playlist.length) return;
		clearTransitionTimers();
		loadItem(state.index - 1);
	}

	// ==========================================
	// INITIALIZATION & EVENT SETUP
	// ==========================================

	function initStorePageControls() {
		// Player-plugin-only initialization.
		// Profile panel collapse, drawer tabs, biography lightbox, responsive
		// layout classes, and all other store-page UI are exclusively owned by
		// vendor-store.js in the child theme. Player suspension when those UI
		// elements are open flows back here via window.tmSuspendBackgroundForEditing
		// / window.tmResumeBackgroundAfterEditing (exposed at module init below).
		bindFullscreenListeners();
		updateFullscreenButton();
		updateTheatreButton();
		// CRITICAL: Only initialize player once - guard against duplicate calls
		if (!tmPlayerInitialized) {
			tmPlayerInitialized = true;
			initHeroPlayer();
		}
		updateVendorLoopButton();
	}

	function initHeroPlayer() {
		var $heroRemote = $(".hero-remote");
		var $heroBox = $(".profile-info-box");
		var $heroImage = $(".hero-media-image").first();
		var $heroAudio = $(".hero-audio").first();
		var $heroEq = $(".hero-audio-eq").first();

		// Clear any pending auto-advance timer from a previously loaded vendor.
		clearMediaTimer();

		// Create missing media elements if needed
		if (!$heroImage.length && $heroBox.length) {
			$heroImage = $("<img class=\"hero-media-image\" alt=\"Store media\" />");
			$heroBox.append($heroImage);
		}

		if (!$heroAudio.length && $heroBox.length) {
			$heroAudio = $("<audio class=\"hero-audio\" preload=\"metadata\"></audio>");
			$heroBox.append($heroAudio);
		}

		if (!$heroEq.length && $heroBox.length) {
			$heroEq = $("<div class=\"hero-audio-eq\" aria-hidden=\"true\"><span class=\"eq-bar\"></span><span class=\"eq-bar\"></span><span class=\"eq-bar\"></span><span class=\"eq-bar\"></span><span class=\"eq-bar\"></span></div>");
			$heroBox.append($heroEq);
		}

		var $globalMute = $(".hero-global-mute").first();
		if (!$globalMute.length && $heroBox.length) {
			$globalMute = $("<button class=\"hero-global-mute\" type=\"button\" aria-label=\"Toggle sound\">" +
				"<span class=\"mute-icon mute-on\" title=\"Sound on\"><i class=\"fas fa-volume-up\" aria-hidden=\"true\"></i></span>" +
				"<span class=\"mute-icon mute-off\" title=\"Muted\"><i class=\"fas fa-volume-mute\" aria-hidden=\"true\"></i></span>" +
				"</button>");
			$heroBox.append($globalMute);
		}

		var $heroMeta = $(".hero-media-meta").first();
		if (!$heroMeta.length && $heroBox.length) {
			$heroMeta = $("<div class=\"hero-media-meta\" aria-live=\"polite\" role=\"status\">"
				+ "<span class=\"meta-label\">Title</span>"
				+ "<span class=\"meta-value meta-title\">&mdash;</span>"
				+ "<span class=\"meta-divider\">&bull;</span>"
				+ "<span class=\"meta-value meta-type\">&mdash;</span>"
				+ "<span class=\"meta-divider\">&bull;</span>"
				+ "<span class=\"meta-value meta-duration\">&mdash;</span>"
				+ "</div>");
			if ($heroRemote.length) {
				$heroRemote.append($heroMeta);
			} else if ($heroBox.length) {
				$heroBox.append($heroMeta);
			}
		}

		var $toggleFull = $heroRemote.find(".hero-toggle-full");
		var $toggleLoop = $heroRemote.find(".hero-toggle-loop");

		// CRITICAL: Preserve state.isPlaying across initHeroPlayer calls in showcase mode
		console.log('[TM PLAYER DEBUG] initHeroPlayer START | state.isPlaying before reset:', state.isPlaying);
		var wasPlaying = state.isPlaying;
		state.isPlaying = true;
		console.log('[TM PLAYER DEBUG] initHeroPlayer | state.isPlaying after initial reset:', state.isPlaying);
		state.muted = readStoredBool(STORAGE_KEYS.muted, false);
		state.index = 0;
		// state.timer is managed via clearMediaTimer()/scheduleAutoNext(); don't null it without clearing.
		state.type = null;
		state.fullDuration = readStoredBool(STORAGE_KEYS.fullDuration, false);
		state.loopMode = readStoredBool(STORAGE_KEYS.loopMode, false);
		// Preserve playing state if user has already interacted
		if (wasPlaying && hasShowcaseStarted()) {
			console.log('[TM PLAYER DEBUG] initHeroPlayer | Preserving state.isPlaying = true (wasPlaying && hasShowcaseStarted)');
			state.isPlaying = true;
		} else {
			console.log('[TM PLAYER DEBUG] initHeroPlayer | NOT preserving - wasPlaying:', wasPlaying, '| hasShowcaseStarted():', hasShowcaseStarted());
		}
		console.log('[TM PLAYER DEBUG] initHeroPlayer END | state.isPlaying:', state.isPlaying);
		var playerMode = _playerMode;
		var showcaseMode = playerMode === "showcase";
		// Detect F5 / Ctrl+R page reloads only for the document's first player init.
		// AJAX vendor swaps re-run initHeroPlayer() inside the same document; the browser
		// keeps reporting the original navigation type for that document, so reading it on
		// every re-init incorrectly marks soft swaps as reloads and drops autoplay.
		var isPageReload = !hasCompletedInitialHeroInit && wasDocumentReloaded();
		restoreShowcaseInteractionState(isPageReload);
		ensureShowcaseKeyboardNavigation();
		if (showcaseMode) {
			// Showcase: always reset loop/swap flags so talent rotation is ready from the start.
			// On the very first page load the session hasn't started yet — pause and show the
			// play overlay to collect the one required user gesture that unlocks autoplay.
			// On every subsequent talent swap hasShowcaseStarted() returns true, so we keep
			// state.isPlaying = true and let canAutoplay() + the Netflix technique take over.
			state.loopMode = false;
			// Always force vendorLoopEnabled off in showcase mode — talent rotation must never
			// be suppressed by a stale localStorage preference from a previous profile-page visit.
			vendorLoopEnabled = false;
			try { localStorage.removeItem(STORAGE_KEYS.vendorLoop); } catch(e) {}
			// URL params override the forced showcase defaults.
			// medialoop=on: loop the vendor's media playlist in place (don't advance to next vendor).
			if (urlConfig.medialoop === true) { state.loopMode = true; }
			// CRITICAL: Preserve state.isPlaying across vendor swaps
			// Only reset to false on actual page reload, not on vendor swap
			if (isPageReload) {
				state.isPlaying = false;
			} else if (!hasShowcaseStarted()) {
				// First page load: reset to defaults and wait for user gesture
				state.isPlaying = false;
			}
			// (On a talent swap hasShowcaseStarted() is true — isPlaying stays true from above.)
			showcaseInteractionPause = false;
		} else {
			// Profile / default page: silently force vendor media to loop within the same talent.
			// This prevents the auto-advance path from ever crossing into the next talent.
			// Manual talent navigation via the arrow buttons still works (those call navigateToVendor directly).
			vendorLoopEnabled = true;
			// On the very first page load (no session token yet) start paused so the play overlay
			// is the first user action — this grants the browser autoplay trust token that makes
			// subsequent soft-navigations (AJAX vendor swap) autoplay cleanly without the
			// Netflix muted-flip and the "Tap to unmute" strip on mobile.
			if (!hasShowcaseStarted() || isPageReload) {
				state.isPlaying = false;
			}
		}

		if (onboardingMode) {
			disableBackgroundMedia();
			state.isPlaying = false;
		}
		hasCompletedInitialHeroInit = true;

		// Initialize A/B video buffer system (must run before buildPlaylist/loadItem)
		console.log('[TM PLAYER DEBUG] initHeroPlayer - before initVideoBuffers | state.isPlaying:', state.isPlaying);
		initVideoBuffers();

		// Rebuild playlist from vendor media data
		console.log('[TM PLAYER DEBUG] initHeroPlayer - before buildPlaylist | state.isPlaying:', state.isPlaying);
		playlist = buildPlaylist();
		console.log('[TM PLAYER DEBUG] initHeroPlayer - after buildPlaylist | state.isPlaying:', state.isPlaying);

		// Apply initial settings
		if ($toggleFull.length) { $toggleFull.prop("checked", state.fullDuration); }
		if ($toggleLoop.length) { $toggleLoop.prop("checked", state.loopMode); }

		// Attach button event handlers (use .off once to prevent duplicates)
		$heroRemote.off("click.tmhero");
		$heroRemote.on("click.tmhero", ".hero-play", function() {
			markUserInteraction();
			unmuteForUserAction();
			state.isPlaying = true;
			playCurrent();
		});
		$heroRemote.on("click.tmhero", ".hero-pause", function() { markUserInteraction(); pauseCurrent(true); });
		$heroRemote.on("click.tmhero", ".hero-next", function() { markUserInteraction(); pauseShowcaseRotation(); unmuteForUserAction(); state.isPlaying = true; goNextManual(); });
		$heroRemote.on("click.tmhero", ".hero-prev", function() { markUserInteraction(); pauseShowcaseRotation(); unmuteForUserAction(); state.isPlaying = true; goPrev(); });

		$heroBox.off("click.tmhero", ".hero-global-mute");
		$heroBox.on("click.tmhero", ".hero-global-mute", function() { markUserInteraction(); toggleMute(); });

		$heroRemote.off("change.tmhero");
		$heroRemote.on("change.tmhero", ".hero-toggle-full", function() {
			state.fullDuration = $(this).is(":checked");
			writeStoredBool(STORAGE_KEYS.fullDuration, state.fullDuration);
			persistPlaybackPrefs();
			loadItem(state.index);
		});
		$heroRemote.on("change.tmhero", ".hero-toggle-loop", function() {
			state.loopMode = $(this).is(":checked");
			writeStoredBool(STORAGE_KEYS.loopMode, state.loopMode);
			persistPlaybackPrefs();
			loadItem(state.index);
		});

		function keepHeroRemoteVisible() {
			if (!$heroRemote.length) return;
			if ($heroRemote.hasClass("is-audio-mode")) return;
			clearTimeout(remoteHideTimeout);
			toggleHeroRemote(true);
			// Auto-hide after 3 seconds of no hover
			remoteHideTimeout = setTimeout(function() {
				if ($heroBox.is(":hover") || $heroRemote.is(":hover")) return;
				toggleHeroRemote(false);
			}, 3000);
		}
		updateResumeShowcaseControl();

		$heroRemote.on("click.tmhero", function() {
			keepHeroRemoteVisible();
		});
		$heroRemote.on("change.tmhero", function() {
			keepHeroRemoteVisible();
		});

		// Load the first item from playlist
		// Always create the play overlay — shown on all page types whenever media is paused.
		ensureShowcasePlayOverlay($heroBox);
		// Create the "Tap to unmute" strip — shown on mobile when the browser forces muted autoplay.
		ensureUnmuteStrip($heroBox);
		applyMute();
		syncRemoteMute();
		loadItem(state.index);
		syncRemotePlaying(state.isPlaying); // also calls syncPlayOverlay() for initial state
		if (onboardingMode) {
			pauseCurrent();
			clearMediaTimer();
		}

		// Hover controls for hero box
		function inCenterZone(evt) {
			var box = $heroBox[0];
			if (!box) return false;
			var rect = box.getBoundingClientRect();
			var x = evt.clientX;
			var y = evt.clientY;
			var xMin = rect.left + rect.width * 0.35;
			var xMax = rect.left + rect.width * 0.65;
			var yMin = rect.top + rect.height * 0.35;
			var yMax = rect.top + rect.height * 0.65;
			return x >= xMin && x <= xMax && y >= yMin && y <= yMax;
		}

		$heroBox.off("mousemove.tmhero").on("mousemove.tmhero", function(evt) {
			if (!$heroRemote.length) return;
			if ($heroRemote.hasClass("is-audio-mode")) return;
			clearTimeout(remoteHideTimeout);
			// Keep visible if mouse is directly over the remote (e.g. on progress bar)
			if (inCenterZone(evt) || $heroRemote.is(":hover")) {
				toggleHeroRemote(true);
			} else {
				remoteHideTimeout = setTimeout(function() {
					if ($heroRemote.is(":hover")) return;
					toggleHeroRemote(false);
				}, 80);
			}
		});

		// Touch equivalent of mousemove: any tap on the player shows the remote.
		// mousemove is never fired by touch on mobile, so without this the remote
		// is inaccessible on phones and tablets.
		$heroBox.off("touchstart.tmhero").on("touchstart.tmhero", function() {
			if (!$heroRemote.length) return;
			if ($heroRemote.hasClass("is-audio-mode")) return;
			keepHeroRemoteVisible();
		});

			$heroBox.off("mouseleave.tmhero").on("mouseleave.tmhero", function() {
			if (!$heroRemote.length) return;
			if ($heroRemote.hasClass("is-audio-mode")) return;
			// Auto-hide after 3 seconds of no hover, but not if mouse is on the remote itself
			remoteHideTimeout = setTimeout(function() {
				if ($heroRemote.is(":hover")) return;
				toggleHeroRemote(false);
			}, 3000);
		});
	}

	// ==========================================
	// KEYBOARD NAVIGATION CONTROLS - Persistent
	// ==========================================

	var vendorList = [];
	var currentVendorIndex = -1;
	var isVendorSwitching = false;
	var lastManualSwipeAt = 0;
	var VENDOR_NAV_CACHE_KEY = "tm_vendor_nav_list_v3_showcase";
	var VENDOR_NAV_CACHE_MAX_AGE_MS = 12 * 60 * 60 * 1000; // 12 hours
	var VENDOR_PAYLOAD_PRELOAD_KEY = "tm_vendor_payloads_preloaded_v1";
	var vendorPayloadPreload = {
		started: false,
		index: 0,
		queue: [],
		timer: null
	};

	function isSlowNetworkConnection() {
		var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if (!connection) return false;
		if (connection.saveData) return true;
		return connection.effectiveType === 'slow-2g'
			|| connection.effectiveType === '2g'
			|| connection.effectiveType === '3g';
	}

	function getVendorPayloadPreloadLimit() {
		if (!vendorList.length) return 0;
		if (isSlowNetworkConnection()) {
			return Math.min(vendorList.length, 2);
		}
		return Math.min(vendorList.length, 6);
	}

	function findVendorIndexById(list, vendorId) {
		vendorId = parseInt(vendorId, 10);
		if (!Array.isArray(list) || !isFinite(vendorId) || vendorId <= 0) return -1;
		for (var i = 0; i < list.length; i++) {
			if (list[i] && parseInt(list[i].id, 10) === vendorId) return i;
		}
		return -1;
	}

	function hydrateVendorListFromCache() {
		try {
			if (!window.localStorage) return false;
			var raw = localStorage.getItem(VENDOR_NAV_CACHE_KEY);
			if (!raw) return false;
			var parsed = JSON.parse(raw);
			if (!parsed || !Array.isArray(parsed.vendors) || !parsed.vendors.length) return false;
			if (parsed.savedAt && (Date.now() - parsed.savedAt) > VENDOR_NAV_CACHE_MAX_AGE_MS) return false;
			vendorList = parsed.vendors;
			var idx = findVendorIndexById(vendorList, window.currentVendorId);
			currentVendorIndex = idx >= 0 ? idx : (typeof currentVendorIndex === "number" ? currentVendorIndex : 0);
			updateVendorNavButtons();
			startVendorPayloadPreload();
			return true;
		} catch (e) {
			return false;
		}
	}

	function shouldPreloadVendorPayloads() {
		return shouldUseVendorContentRest() && vendorList.length > 0 && !isSlowNetworkConnection();
	}

	function markVendorPayloadPreloadDone() {
		try {
			if (window.sessionStorage) {
				sessionStorage.setItem(VENDOR_PAYLOAD_PRELOAD_KEY, "1");
			}
		} catch (e) {}
	}

	function hasVendorPayloadPreloadRun() {
		try {
			return window.sessionStorage && sessionStorage.getItem(VENDOR_PAYLOAD_PRELOAD_KEY) === "1";
		} catch (e) {
			return false;
		}
	}

	function startVendorPayloadPreload() {
		if (vendorPayloadPreload.started) return;
		if (!shouldPreloadVendorPayloads()) return;
		if (hasVendorPayloadPreloadRun()) return;
		vendorPayloadPreload.started = true;
		vendorPayloadPreload.index = 0;
		vendorPayloadPreload.queue = [];

		var preloadCount = getVendorPayloadPreloadLimit();
		var startIndex = currentVendorIndex >= 0 ? currentVendorIndex : findVendorIndexById(vendorList, window.currentVendorId);
		for (var offset = 1; offset <= preloadCount; offset += 1) {
			var queueIndex = vendorList.length ? ((startIndex + offset) % vendorList.length) : -1;
			if (queueIndex < 0 || !vendorList[queueIndex] || !vendorList[queueIndex].id) continue;
			vendorPayloadPreload.queue.push(vendorList[queueIndex]);
		}
		if (!vendorPayloadPreload.queue.length) {
			vendorPayloadPreload.started = false;
			markVendorPayloadPreloadDone();
			return;
		}

		var runNext = function() {
			if (!vendorPayloadPreload.started) return;
			if (vendorPayloadPreload.index >= vendorPayloadPreload.queue.length) {
				vendorPayloadPreload.started = false;
				markVendorPayloadPreloadDone();
				return;
			}
			var vendor = vendorPayloadPreload.queue[vendorPayloadPreload.index++];
			if (!vendor || !vendor.id) {
				vendorPayloadPreload.timer = setTimeout(runNext, 60);
				return;
			}
			var request = getVendorContentRequest(vendor.id);
			$.ajax({
				url: request.url,
				type: request.type,
				data: request.data || {}
			}).always(function() {
				vendorPayloadPreload.timer = setTimeout(runNext, 180);
			});
		};

		vendorPayloadPreload.timer = setTimeout(runNext, 450);
	}

	function persistVendorListToCache(list) {
		try {
			if (!window.localStorage) return;
			localStorage.setItem(VENDOR_NAV_CACHE_KEY, JSON.stringify({
				vendors: list || [],
				savedAt: Date.now()
			}));
		} catch (e) {}
	}

	function isVendorNavigationBlocked() {
		return $("body").hasClass("tm-modal-open")
			|| $(".tm-field-editor-modal.is-open").length > 0
			|| $(".tm-location-modal.is-open").length > 0
			|| $(".profile-info-content.view-mode-editing").length > 0
			|| $(".overlay-section.section-editing").length > 0;
	}

	function loadVendorList(options, done) {
		options = options || {};
		done = typeof done === "function" ? done : null;
		var forceFetch = !!options.forceFetch;

		// If we already have a list in memory and we're not forcing a refresh, don't re-fetch.
		if (!forceFetch && vendorList && vendorList.length) {
			if (done) done(true);
			return;
		}

		// If not forcing fetch, try cache first for instant nav state.
		if (!forceFetch) {
			hydrateVendorListFromCache();
			if (vendorList && vendorList.length) {
				if (done) done(true);
				return;
			}
		}

		// If the showcase was opened with a pre-filtered vendor list (passed as
		// window.tmShowcaseIds via ?tm_ids= URL param), use those IDs directly so
		// we never rely on the AJAX vendor-list endpoint for the rotation order.
		if (window.tmShowcaseIds && Array.isArray(window.tmShowcaseIds) && window.tmShowcaseIds.length) {
			if (!vendorList || !vendorList.length) {
				vendorList = window.tmShowcaseIds.map(function(id) { return { id: id }; });
				currentVendorIndex = findVendorIndexById(vendorList, window.currentVendorId);
				if (currentVendorIndex < 0) currentVendorIndex = 0;
				updateVendorNavButtons();
				startVendorPayloadPreload();
			}
			if (done) done(true);
			return;
		}

		var ajaxUrl = (window.vendorStoreData && window.vendorStoreData.ajaxurl)
			? window.vendorStoreData.ajaxurl
			: '/wp-admin/admin-ajax.php';

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'get_vendor_navigation_list',
				current_vendor_id: window.currentVendorId || 0
			},
			success: function(response) {
				if (response.success && response.data) {
					vendorList = response.data.vendors || [];
					// Prefer server index, but fall back to local match in case current_vendor_id was 0.
					currentVendorIndex = (typeof response.data.current_index === "number")
						? response.data.current_index
						: findVendorIndexById(vendorList, window.currentVendorId);
					if (currentVendorIndex < 0) {
						currentVendorIndex = findVendorIndexById(vendorList, window.currentVendorId);
						if (currentVendorIndex < 0) currentVendorIndex = 0;
					}
					persistVendorListToCache(vendorList);
					updateVendorNavButtons();
					startVendorPayloadPreload();
					if (done) done(true);
					return;
				}
				if (done) done(false);
			},
			error: function() {
				console.warn('Failed to load vendor list for navigation');
				if (done) done(false);
			}
		});
	}

	function updateVendorNavButtons() {
		var $leftBtn = $(".keyboard-nav-left");
		var $rightBtn = $(".keyboard-nav-right");

		if (!vendorList.length) {
			$leftBtn.prop("disabled", true);
			$rightBtn.prop("disabled", true);
			return;
		}

		$leftBtn.prop("disabled", currentVendorIndex <= 0);
		$rightBtn.prop("disabled", currentVendorIndex >= vendorList.length - 1);
		updateVendorLoopButton();
	}

	function navigateToVendor(direction, options) {
		options = options || {};
		if (isVendorNavigationBlocked()) return;
		if (isVendorSwitching) return;
		if (!vendorList.length) {
			loadVendorList({ forceFetch: true }, function(ok) {
				if (ok) navigateToVendor(direction);
			});
			return;
		}

		var targetIndex = currentVendorIndex + direction;
		var didWrap = false;
		if (targetIndex < 0 || targetIndex >= vendorList.length) {
			if (!vendorLoopEnabled && vendorList.length) {
				targetIndex = targetIndex < 0 ? vendorList.length - 1 : 0;
				didWrap = true;
			} else {
				return;
			}
		}

		var targetVendor = vendorList[targetIndex];
		if (!targetVendor || !targetVendor.id) return;

		var transitionState = {
			wasCollapsed: false,
			avatarSrc: "",
			collapsedAvatarSrc: "",
			collapsedName: ""
		};
		var $profileHead = $(".profile-info-head");
		if ($profileHead.length) {
			transitionState.wasCollapsed = $profileHead.hasClass("is-collapsed");
			transitionState.avatarSrc = $(".profile-img img").first().attr("src") || "";
			transitionState.collapsedAvatarSrc = $(".profile-img img").first().attr("src") || "";
			transitionState.collapsedName = $(".collapsed-tab-name").first().text() || "";
			if (!options.skipPreCollapse) {
				if (!transitionState.wasCollapsed) {
					$profileHead.addClass("is-collapsed");
					updateTalentPanelOpenState();
				}
				$(".collapsed-tab-name").text("Next Talent");
				scheduleDefaultAvatarSwap();
			}
		}

		isVendorSwitching = true;
		$(".keyboard-nav-btn").addClass("is-loading");
		clearMediaTimer();
		$(".profile-frame").addClass("tm-vendor-transitioning");

		if (didWrap) {
			var $frame = $(".profile-frame");
			$frame.addClass("tm-vendor-wrap-cue");
			setTimeout(function() {
				$frame.removeClass("tm-vendor-wrap-cue");
			}, 550);
		}

		// Media fade handled via .tm-vendor-transitioning class.

		var ajaxUrl = (window.vendorStoreData && window.vendorStoreData.ajaxurl)
			? window.vendorStoreData.ajaxurl
			: '/wp-admin/admin-ajax.php';

		function clearVendorSwitchingState() {
			isVendorSwitching = false;
			$(".keyboard-nav-btn").removeClass("is-loading");
			$(".profile-frame").removeClass("tm-vendor-transitioning");
			transitionSequenceActive = false;
		}

		function restoreTransitionState() {
			stopBlackout();
			if (transitionState.avatarSrc) {
				$(".profile-img img").attr("src", transitionState.avatarSrc);
			}
			if (transitionState.collapsedAvatarSrc) {
				$(".profile-img img").attr("src", transitionState.collapsedAvatarSrc);
			}
			if (transitionState.collapsedName) {
				$(".collapsed-tab-name").text(transitionState.collapsedName);
			}
			if (!transitionState.wasCollapsed && !shouldKeepTalentPanelCollapsedOnHandheld()) {
				$(".profile-info-head").removeClass("is-collapsed");
				updateTalentPanelOpenState();
			}
		}

		function finishSwapSuccess(response, index, vendor) {
			if (!response || !response.data || !response.data.html) {
				return false;
			}
			
			// CRITICAL: Hide hero-remote immediately on vendor swap start
			// It should only appear on hover, not automatically
			console.log('[TM PLAYER DEBUG] finishSwapStart: Hiding hero-remote');
			var $heroRemote = $(".hero-remote");
			if ($heroRemote.length) {
				$heroRemote.removeClass("is-visible");
			}
			
			var nextAvatarSrc = "";
			try {
				var $temp = $("<div>").html(response.data.html);
				nextAvatarSrc = $temp.find(".profile-img img").first().attr("src") || "";
			} catch (e) {
				nextAvatarSrc = "";
			}
			if (nextAvatarSrc) {
				preloadImage(nextAvatarSrc);
			}
			var media = response.data.vendorMedia || null;
			if (index !== 0 && !vendorHasPlayableMedia(media)) {
				requestVendorByIndex(0);
				return true;
			}

			console.log('[TM PLAYER DEBUG] navigateToVendor | Replacing profile-info-box with fresh vendor markup');
			var $newContainer = $("<div>").html(response.data.html);
			var $newProfileBox = $newContainer.find('.profile-info-box').first();
			var $currentProfileBox = $('.profile-info-box').first();
			if (!$newProfileBox.length || !$currentProfileBox.length) {
				return false;
			}

			window.currentVendorId = response.data.vendor_id;
			currentVendorIndex = index;
			if (window.vendorStoreData) {
				window.vendorStoreData.userId = response.data.vendor_id;
			}
			window.vendorMedia = response.data.vendorMedia || null;

			$currentProfileBox.replaceWith($newProfileBox);
			updateVendorNavButtons();

			if (options.keepCollapsed) {
				var $head = $('.profile-info-head').first();
				if ($head.length) {
					$head.addClass('is-collapsed');
					updateTalentPanelOpenState();
				}
				if (nextAvatarSrc) {
					var $img = $('.profile-img img').first();
					if ($img.length) {
						var defaultAvatar = getDefaultVendorAvatarSrc();
						if (defaultAvatar) {
							$img.attr('src', defaultAvatar);
						}
						preloadImage(nextAvatarSrc, function() {
							$img.attr('src', nextAvatarSrc);
						});
					}
				}
			}

			if (vendor.url && !isShowcaseMode()) {
				window.history.pushState(
					{ vendorId: response.data.vendor_id },
					response.data.store_name,
					vendor.url
				);
			}

			if (options.blackoutHoldMs) {
				transitionTimers.blackoutRemove = setTimeout(function() {
					stopBlackout();
				}, options.blackoutHoldMs);
			} else {
				stopBlackout();
			}

			$(".profile-frame").removeClass("tm-vendor-transitioning");
			initHeroPlayer();
			if (options.holdAutoplayMs) {
				setTransitionHold(options.holdAutoplayMs);
			}
			if (isShowcaseMode()) {
				setTimeout(function() {
					stopBlackout();
					$(".profile-frame").removeClass("tm-vendor-transitioning");
				}, 200);
			}

			if (isShowcaseMode() && state.isPlaying && hasShowcaseStarted()) {
				playCurrent();
			}
			
			// CRITICAL: Hide hero-remote after swap - it should only appear on hover
			// The hero-remote should remain hidden until the user hovers over the video area
			console.log('[TM PLAYER DEBUG] Post-swap: Hiding hero-remote (will show on hover)');
			var $heroRemote = $(".hero-remote");
			if ($heroRemote.length) {
				$heroRemote.removeClass("is-visible");
			}
			
			if (isShowcaseMode() && options.expandDelayMs) {
				// Showcase: the swap sequence always pre-collapses the panel so
				// transitionState.wasCollapsed is always true — bypass that guard and
				// always schedule the expand. After expandDelayMs the panel opens and
				// stays open until the next swap's collapse timer fires.
				setPanelHoldUntil(options.holdAutoplayMs
					? (options.holdAutoplayMs + options.expandDelayMs)
					: options.expandDelayMs);
			} else if (!transitionState.wasCollapsed && !options.keepCollapsed && !shouldKeepTalentPanelCollapsedOnHandheld()) {
				$(".profile-info-head").removeClass("is-collapsed");
				updateTalentPanelOpenState();
			} else if (!transitionState.wasCollapsed && options.expandDelayMs) {
				setPanelHoldUntil(options.holdAutoplayMs
					? (options.holdAutoplayMs + options.expandDelayMs)
					: options.expandDelayMs);
			}

			clearVendorSwitchingState();
			vendorPrefetch.data = null;
			vendorPrefetch.index = null;
			return true;
		}

		function requestVendorByIndex(index) {
			var vendor = vendorList[index];
			if (!vendor || !vendor.id) {
				clearVendorSwitchingState();
				restoreTransitionState();
				return;
			}

			var request = getVendorContentRequest(vendor.id);
			var usedRest = (request.type === 'GET');

			if (options.preloadedResponse && options.preloadedResponse.success) {
				finishSwapSuccess(options.preloadedResponse, index, vendor);
				return;
			}

			// Abort the transition cleanly and let the showcase advance to the next vendor.
			function abortAndAdvance(reason) {
				console.warn('[TM] ' + reason + ' — skipping vendor ' + vendor.id + ' (index ' + index + ')');
				clearVendorSwitchingState();
				restoreTransitionState();
				// In showcase mode keep the loop alive: skip to the next vendor directly.
				if (isShowcaseMode() && vendorList.length > 1) {
					var nextIndex = index + 1 < vendorList.length ? index + 1 : 0;
					console.log('[TM] Showcase: advancing to index ' + nextIndex + ' after skip');
					// requestVendorByIndex is a closure — call it directly to bypass
					// navigateToVendor's delta arithmetic and blocked-state guards.
					isVendorSwitching = true;
					setTimeout(function() { requestVendorByIndex(nextIndex); }, 1500);
				}
			}

			$.ajax({
				url: request.url,
				type: request.type,
				timeout: 12000,
				data: request.data || {},
				success: function(response) {
					if (response.success && response.data && response.data.html) {
						writeVendorContentCache(vendor.id, response);
						finishSwapSuccess(response, index, vendor);
						return;
					}
					console.error('[TM] Server error for vendor ' + vendor.id + ':', response);
					abortAndAdvance('Server returned error');
				},
				error: function(xhr) {
					// REST failed — retry with AJAX (admin-ajax.php) before giving up.
					if (usedRest) {
						console.warn('[TM] REST failed for vendor ' + vendor.id + ' (HTTP ' + xhr.status + '), retrying via AJAX. URL: ' + request.url);
						var ajaxReq = getAjaxVendorContentRequest(vendor.id);
						console.log('[TM] AJAX fallback URL:', ajaxReq.url, 'data:', JSON.stringify(ajaxReq.data));
						$.ajax({
							url: ajaxReq.url,
							type: ajaxReq.type,
							timeout: 12000,
							data: ajaxReq.data || {},
							success: function(response) {
								if (response.success && response.data && response.data.html) {
									writeVendorContentCache(vendor.id, response);
									finishSwapSuccess(response, index, vendor);
									return;
								}
								console.error('[TM] AJAX server error for vendor ' + vendor.id + ':', response);
								abortAndAdvance('AJAX server error');
							},
							error: function(xhr2) {
								var responseText = xhr2.responseText || 'No response text';
								console.error('[TM] AJAX also failed for vendor ' + vendor.id + ' (HTTP ' + xhr2.status + '). Response: ' + responseText.substring(0, 200));
								abortAndAdvance('Both REST and AJAX failed');
							}
						});
						return;
					}
					console.error('[TM] AJAX error for vendor ' + vendor.id + ' (HTTP ' + xhr.status + '). Response: ' + getSafeErrorResponse(xhr));
					abortAndAdvance('AJAX failed');
				}
			});
		}

		setTimeout(function() {
			requestVendorByIndex(targetIndex);
		}, 300);
	}

	// Helper function to safely get error response text
	function getSafeErrorResponse(xhr) {
		var responseText = xhr.responseText || 'No response text';
		return responseText.substring(0, 200);
	}

	// Keyboard navigation button click handlers
	$(document).on("click", ".keyboard-nav-up", function() {
		markUserInteraction();
		pauseShowcaseRotation();
		unmuteForUserAction();
		state.isPlaying = true;
		goPrev();
	});

	$(document).on("click", ".keyboard-nav-down", function() {
		markUserInteraction();
		pauseShowcaseRotation();
		unmuteForUserAction();
		state.isPlaying = true;
		goNextManual();
	});

	$(document).on("click", ".keyboard-nav-left", function() {
		// Vendor switching is an explicit click; don't block it behind handheld viewport detection.
		if (isVendorNavigationBlocked()) return;
		markUserInteraction();
		unmuteForUserAction();
		state.isPlaying = true;
		navigateToVendor(-1);
	});

	$(document).on("click", ".keyboard-nav-right", function() {
		// Vendor switching is an explicit click; don't block it behind handheld viewport detection.
		if (isVendorNavigationBlocked()) return;
		markUserInteraction();
		unmuteForUserAction();
		state.isPlaying = true;
		navigateToVendor(1);
	});

	$(document).on("click", ".keyboard-nav-loop", function() {
		markUserInteraction();
		vendorLoopEnabled = !vendorLoopEnabled;
		writeStoredBool(STORAGE_KEYS.vendorLoop, vendorLoopEnabled);
		updateVendorLoopButton();
		clearTransitionTimers();
		clearMediaTimer();
		clearAdvanceTimer();
		stopBlackout();
		if (state.isPlaying) {
			armAdvanceTimer(currentItem());
		}
	});

	$(document).on("click", ".tm-showcase-resume-control", function() {
		markUserInteraction();
		resumeShowcaseRotation();
	});

	function isTouchNavigationTarget(target) {
		var $target = $(target);
		return $target.closest(".profile-frame").length > 0
			&& $target.closest(".profile-bottom-drawer").length === 0
			&& $target.closest(".hero-remote, .tm-cinematic-header").length === 0
			&& !$target.is("input, textarea, select, button, a")
			&& $target.closest("input, textarea, select, button, a").length === 0;
	}

	var touchStartX = 0;
	var touchStartY = 0;
	var touchStartTime = 0;
	var touchStartAllowed = false;
	var touchAxis = "";
	var touchLastX = 0;
	var touchLastY = 0;

	$(document).on("touchstart.tmnav", function(e) {
		if (!isHandheldViewport()) return;
		if (!e.originalEvent || !e.originalEvent.touches) return;
		if (e.originalEvent.touches.length !== 1) return;
		touchStartAllowed = isTouchNavigationTarget(e.target);
		if (!touchStartAllowed) return;
		var touch = e.originalEvent.touches[0];
		touchStartX = touch.clientX;
		touchStartY = touch.clientY;
		touchLastX = touch.clientX;
		touchLastY = touch.clientY;
		touchStartTime = Date.now();
		touchAxis = "";
	});

	$(document).on("touchmove.tmnav", function(e) {
		if (!isHandheldViewport()) return;
		if (!touchStartTime || !touchStartAllowed) return;
		if (!e.originalEvent || !e.originalEvent.touches) return;
		var touch = e.originalEvent.touches[0];
		var dx = touch.clientX - touchStartX;
		var dy = touch.clientY - touchStartY;
		var absX = Math.abs(dx);
		var absY = Math.abs(dy);
		touchLastX = touch.clientX;
		touchLastY = touch.clientY;
		if (touchAxis) return;
		if (absX < 10 && absY < 10) return;
		touchAxis = absX > absY ? "x" : "y";
		if (touchAxis) {
			e.preventDefault();
		}
	});

	$(document).on("touchend.tmnav", function(e) {
		if (!isHandheldViewport()) return;
		if (!touchStartTime) return;
		if (!touchStartAllowed) return;
		var touch = (e.originalEvent && e.originalEvent.changedTouches)
			? e.originalEvent.changedTouches[0]
			: null;
		if (!touch) return;

		var endX = touchLastX || touch.clientX;
		var endY = touchLastY || touch.clientY;
		var dx = endX - touchStartX;
		var dy = endY - touchStartY;
		var absX = Math.abs(dx);
		var absY = Math.abs(dy);
		var elapsed = Date.now() - touchStartTime;

		touchStartTime = 0;
		touchStartAllowed = false;
		touchLastX = 0;
		touchLastY = 0;

		if (elapsed > 600) return;
		// Lock axis and thresholds to avoid diagonal misfires on mobile.
		var minDist = 50;
		var dominance = 1.3;

		if (touchAxis === "x") {
			if (absX < minDist) return;
			e.preventDefault();
			markUserInteraction();
			unmuteForUserAction();
			state.isPlaying = true;
			if (dx > 0) {
				navigateToVendor(1);
			} else {
				navigateToVendor(-1);
			}
			return;
		}

		if (touchAxis === "y") {
			if (absY < minDist) return;
			e.preventDefault();
			markUserInteraction();
			unmuteForUserAction();
			state.isPlaying = true;
			if (dy > 0) {
				// Swipe down = next media (wrap to first via goNextManual).
				lastManualSwipeAt = Date.now();
				goNextManual();
			} else {
				// Swipe up = previous media.
				lastManualSwipeAt = Date.now();
				goPrev();
			}
			return;
		}

		if (absX >= absY * dominance && absX >= minDist) {
			e.preventDefault();
			markUserInteraction();
			if (dx > 0) {
				navigateToVendor(1);
			} else {
				navigateToVendor(-1);
			}
			return;
		}
		if (absY >= absX * dominance && absY >= minDist) {
			e.preventDefault();
			markUserInteraction();
			if (dy > 0) {
				// Swipe down fallback when axis wasn't locked.
				lastManualSwipeAt = Date.now();
				goNextManual();
			} else {
				// Swipe up fallback when axis wasn't locked.
				lastManualSwipeAt = Date.now();
				goPrev();
			}
		}
	});

	$(document).on("click", ".hero-global-fullscreen", function() {
		if (isTheatreMode) {
			setTheatreMode(false);
		}
		if (document.fullscreenElement) {
			exitFullscreen();
		} else {
			requestFullscreen();
			showFullscreenHint();
		}
	});

	$(document).on("click", ".hero-global-theatre", function() {
		if (isTheatreMode) {
			setTheatreMode(false);
			exitFullscreen();
			return;
		}
		setTheatreMode(true);
		requestFullscreen();
		showFullscreenHint();
	});

	function closeResolutionMenu() {
		$(".hero-global-controls").removeClass("is-resolution-open");
		$(".hero-global-resolution").attr("aria-expanded", "false");
	}

	$(document).on("click", ".hero-global-resolution", function(e) {
		e.preventDefault();
		e.stopPropagation();
		var $controls = $(this).closest(".hero-global-controls");
		var isOpen = $controls.hasClass("is-resolution-open");
		closeResolutionMenu();
		if (!isOpen) {
			$controls.addClass("is-resolution-open");
			$(this).attr("aria-expanded", "true");
		}
	});

	$(document).on("click", ".profile-info-box, .profile-banner-video, .hero-media-image", function(e) {
		if (!isTheatreMode || !isHandheldViewport()) return;
		if ($(e.target).closest(".hero-global-controls, .hero-remote").length) return;
		setTheatreMode(false);
		exitFullscreen();
	});

	$(document).on("click", function(e) {
		if (!isHandheldViewport()) return;
		if (!document.fullscreenElement && !isTheatreMode) return;
		if ($(e.target).closest(".hero-global-controls, .hero-remote").length) return;
		if (isTheatreMode) {
			setTheatreMode(false);
		}
		exitFullscreen();
	});

	$(document).on("click", function(e) {
		if ($(e.target).closest(".hero-global-controls").length) return;
		closeResolutionMenu();
	});

	$(document).on("click", function(e) {
		if (!$(document.body).hasClass("tm-mobile-landscape-play-focus")) return;
		if ($(e.target).closest(".hero-remote, .tm-showcase-play-overlay, .hero-global-controls").length) return;
		toggleHeroRemote(false);
		updateMobileLandscapePlaybackFocusState();
	});

	// Global keyboard event listeners
	$(document).on("keydown", function(e) {
		// Hardware keyboard shortcuts must remain active regardless of responsive
		// viewport classification. The handheld viewport rules intentionally cover
		// some laptop/tablet widths for layout purposes, but using them here blocks
		// real ArrowUp/ArrowDown/Space input on physical keyboards.
		var isFormField = $(e.target).is("input, textarea, select");
		var inHeroControls = $(e.target).closest(".hero-remote").length > 0;

		if (e.key === "Escape") {
			closeResolutionMenu();
			if (isTheatreMode) {
				setTheatreMode(false);
				exitFullscreen();
			}
			return;
		}

		if (e.code === "Space" || e.key === " " || e.key === "Space" || e.key === "Spacebar") {
			if (!inHeroControls && isFormField) return;
			e.preventDefault();
			markUserInteraction();
			if (state.isPlaying) {
				pauseCurrent(true);
			} else {
				unmuteForUserAction();
				state.isPlaying = true;
				playCurrent();
				$(".tm-showcase-play-overlay").addClass("is-hidden");
				try {
					if (window.sessionStorage) {
						sessionStorage.setItem("tm_showcase_started", "1");
					}
				} catch (e) {}
			}
			return;
		}

		if (e.code === "KeyM" || e.key === "m" || e.key === "M") {
			if (!inHeroControls && isFormField) return;
			e.preventDefault();
			markUserInteraction();
			toggleMute();
			return;
		}

		if (isFormField) return;

		switch(e.key) {
			case "ArrowUp":
				e.preventDefault();
				markUserInteraction();
				unmuteForUserAction();
				state.isPlaying = true;
				goPrev();
				break;
			case "ArrowDown":
				e.preventDefault();
				markUserInteraction();
				unmuteForUserAction();
				state.isPlaying = true;
				goNextManual();
				break;
			case "ArrowLeft":
				e.preventDefault();
				markUserInteraction();
				unmuteForUserAction();
				state.isPlaying = true;
				navigateToVendor(-1);
				break;
			case "ArrowRight":
				e.preventDefault();
				markUserInteraction();
				unmuteForUserAction();
				state.isPlaying = true;
				navigateToVendor(1);
				break;
		}
	});

	// Initialize on page load
	// 1) hydrate from cache for instant nav state
	hydrateVendorListFromCache();
	// 2) refresh once per hard page load and persist
	loadVendorList({ forceFetch: true });
	initStorePageControls();

	// Mobile cinematic header menu toggle
	function closeHeaderMenu() {
		var $header = $(".tm-cinematic-header").first();
		if (!$header.length) return;
		$header.removeClass("is-menu-open");
		$(".tm-header-toggle").attr("aria-expanded", "false");
	}

	$(document).on("click", ".tm-header-toggle", function() {
		var $header = $(".tm-cinematic-header").first();
		var isOpen = $header.hasClass("is-menu-open");
		$header.toggleClass("is-menu-open", !isOpen);
		$(this).attr("aria-expanded", (!isOpen).toString());
	});

	$(document).on("click", ".tm-header-nav a", function() {
		if (isHandheldViewport()) {
			closeHeaderMenu();
		}
	});

	$(document).on("click", function(event) {
		var $header = $(".tm-cinematic-header").first();
		if (!$header.length || !$header.hasClass("is-menu-open")) {
			return;
		}
		if ($(event.target).closest(".tm-cinematic-header").length) {
			return;
		}
		closeHeaderMenu();
	});

	$(window).on("resize.tm-header-toggle", function() {
		if (!isHandheldViewport()) {
			closeHeaderMenu();
		}
	});

	// ==========================================
	// INLINE EDITING FOR VENDOR ATTRIBUTES
	// Allow vendors to edit their profile attributes directly on the public page
	// ==========================================
	
	if (typeof vendorStoreData !== 'undefined' && canEditProfile()) {
		console.log('âœï¸ Inline editing enabled for owner');
		
		// Add visual indicator for editable mode
		$('.vendor-custom-attributes-wrapper').addClass('owner-viewing');
		
		// Legacy System B handlers removed - attributes now use the standardized modal editor
	}
	
	// ============================================
	// HELP TOOLTIP TOGGLE (Mobile-Friendly)
	// ============================================
	
	// Toggle help tooltip on click/tap for multi-select fields
	$(document).on('click', '.help-toggle-btn', function(e) {
		e.stopPropagation();
		
		var $btn = $(this);
		var wasOpen = $btn.hasClass('is-tooltip-open');
		var $wrapper = $btn.closest('.help-icon-wrapper');
		var helpText = $btn.data('help-text');
		
		// Always close any open tooltips first
		$('.help-tooltip').remove();
		$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
		
		// If this icon was already open, toggle it closed
		if (wasOpen) {
			return;
		}
		
		// Check if this tooltip is inside a modal (unified modal, location modal, or media modal)
		var isInsideModal = $btn.closest('.tm-field-editor-dialog').length > 0 || $btn.closest('.tm-location-modal').length > 0 || $btn.closest('.media-modal').length > 0;
		var forceViewportTooltip = $btn.closest('#social-section').length > 0 || $btn.closest('.contact-channel-row').length > 0;
		
		// For modal tooltips, append to body to escape stacking context
		// For regular tooltips, use wrapper positioning
		var $tooltipContainer;
		if (isInsideModal || forceViewportTooltip) {
			$tooltipContainer = $('body');
			$wrapper.addClass('modal-help-wrapper');
		} else {
			$tooltipContainer = $wrapper;
		
			if (!$tooltipContainer.length) {
				$tooltipContainer = $wrapper;
			}
		}
		
		// Create and show tooltip
		var $tooltip = $('<span class="help-tooltip"></span>').text(helpText);
		
		$tooltipContainer.append($tooltip);
		$btn.addClass('is-tooltip-open');
		
		// Position tooltip (show above icon)
		setTimeout(function() {
			$tooltip.addClass('visible');
		}, 10);
	});
	
	// Close tooltip when clicking outside
	$(document).on('click', function(e) {
		// Don't close if clicking on help icon, wrapper, or tooltip overlay
		if (!$(e.target).closest('.help-icon-wrapper, .vendor-cta-buttons .help-tooltip').length) {
			$('.help-tooltip').remove();
			$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
		}
	});
	
	$(document).on('click', '.tm-location-modal__backdrop, .tm-location-modal__close, .tm-location-modal .cancel-field-btn', function() {
		$('.help-tooltip').remove();
		$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
	});
	
	// =================================================================
	// Profile Info Inline Editing (Avatar, Name, Location)
	// =================================================================

	function applyMediaEditorHelp(helpText) {
		var $title = $('.media-modal .media-frame-title').first();
		if (!$title.length) return;
		$title.find('.media-help-wrapper').remove();
		if (!helpText) return;
		var $helpIcon = $('<span class="help-icon-wrapper media-help-wrapper"><button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="' + helpText + '" style="background: none; border: none; padding: 0; cursor: pointer;"><i class="fas fa-question-circle" aria-hidden="true"></i></button></span>');
		$title.append($helpIcon);
	}

	function bindMediaEditorHelp(mediaFrame, helpText) {
		if (!mediaFrame || !mediaFrame.on) return;
		var applyHelp = function() {
			applyMediaEditorHelp(helpText);
		};
		mediaFrame.on('open', applyHelp);
		mediaFrame.on('content:render', applyHelp);
		mediaFrame.on('router:render', applyHelp);
		mediaFrame.on('toolbar:render', applyHelp);
	}

	function getMediaSelectionIds(selection) {
		if (!selection || !selection.models) return [];
		return selection.models.map(function(model) {
			return model && model.id ? model.id : null;
		}).filter(function(id) {
			return !!id;
		});
	}

	function saveMediaPlaylist(playlistType, ids) {
		if (!ids.length) {
			showNotification('Please select at least one item', 'error');
			return;
		}

		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_media_playlist',
				nonce: vendorStoreData.nonce,
				user_id: vendorStoreData.userId,
				playlist_type: playlistType,
				ids: ids
			},
			success: function(response) {
				if (response.success && response.data && response.data.vendorMedia) {
					window.vendorMedia = response.data.vendorMedia;
					playlist = buildPlaylist();
					state.index = 0;
					loadItem(0);
					showNotification(response.data.message || 'Playlist updated successfully', 'success');
				} else {
					showNotification(response.data && response.data.message ? response.data.message : 'Failed to update playlist', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	}

	function clearMediaPlaylist(playlistType) {
		if (!window.confirm('Clear this playlist?')) {
			return;
		}

		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_media_playlist',
				nonce: vendorStoreData.nonce,
				user_id: vendorStoreData.userId,
				playlist_type: playlistType,
				ids: [],
				clear: 1
			},
			success: function(response) {
				if (response.success && response.data && response.data.vendorMedia) {
					window.vendorMedia = response.data.vendorMedia;
					playlist = buildPlaylist();
					state.index = 0;
					loadItem(0);
					showNotification(response.data.message || 'Playlist cleared successfully', 'success');
				} else {
					showNotification(response.data && response.data.message ? response.data.message : 'Failed to clear playlist', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	}

	function openMediaPlaylistEditor(playlistType) {
		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('WordPress media library is not available');
			return;
		}

		var configMap = {
			image: {
			title: 'Image Playlist',
			buttonText: 'Save Playlist',
			helpText: 'Upload and/or Select one or more files then click on "Save Playlist". NB: You can only have one Image Playlist',
			state: 'gallery',
			libraryType: 'image'
		},
		video: {
			title: 'Video Playlist',
			buttonText: 'Save Playlist',
			helpText: 'Upload and/or Select one or more files then click on "Save Playlist". NB: You can only have one Video Playlist',
			state: 'video-playlist',
			libraryType: 'video'
		},
		audio: {
			title: 'Audio Playlist',
			state: 'playlist',
			libraryType: 'audio'
		}
	};

	var config = configMap[playlistType];
	if (!config) return;

		var mediaFrame = wp.media({
			title: config.title,
			button: {
				text: config.buttonText
			},
			multiple: true,
			library: {
				type: config.libraryType
			},
		frame: 'post',
		state: config.state
	});

	bindMediaEditorHelp(mediaFrame, config.helpText);
	suspendBackgroundForEditing();

	// Function to update button texts
	function updatePlaylistButtonTexts() {
		// Change "Create a new gallery/playlist" to "Add to playlist"
		$('.media-modal .media-button-gallery, .media-modal .media-button-video-playlist, .media-modal .media-button-playlist').each(function() {
			if ($(this).text() !== 'Add to playlist') {
				$(this).text('Add to playlist');
			}
		});
		
		// Change "Insert gallery/video playlist/audio playlist" to "Save playlist"
		$('.media-modal .media-button-insert').each(function() {
			if ($(this).text() !== 'Save playlist') {
				$(this).text('Save playlist');
			}
		});
	}

	var routerObserver = null;

	function ensureClearPlaylistButton() {
		var $frame = mediaFrame && mediaFrame.$el ? mediaFrame.$el : $('.media-modal .media-frame').first();
		if (!$frame.length) return false;
		var $router = $frame.find('.media-frame-router').first();
		if (!$router.length) return false;
		if ($router.find('.tm-clear-playlist-btn').length) {
			return true;
		}
		$router.find('.tm-media-router-actions').remove();
		var $actions = $('<div class="tm-media-router-actions"></div>');
		var $btn = $('<button type="button" class="button button-secondary tm-clear-playlist-btn">Clear Playlist</button>');
		$btn.on('click', function(e) {
			e.preventDefault();
			clearMediaPlaylist(playlistType);
		});
		$actions.append($btn);
		$router.append($actions);
		return true;
	}

	function ensureClearPlaylistButtonWithRetry(attempt) {
		var tries = typeof attempt === 'number' ? attempt : 0;
		if (ensureClearPlaylistButton()) return;
		if (tries >= 8) return;
		setTimeout(function() {
			ensureClearPlaylistButtonWithRetry(tries + 1);
		}, 80);
	}

	mediaFrame.on('open', function() {
		$('body').addClass('tm-media-editor-open tm-media-playlist-editor tm-media-playlist-' + playlistType);
		applyMediaEditorHelp(config.helpText);
		ensureClearPlaylistButtonWithRetry(0);

		if (mediaFrame.$el && mediaFrame.$el.length) {
			if (routerObserver) {
				routerObserver.disconnect();
			}
			routerObserver = new MutationObserver(function() {
				ensureClearPlaylistButton();
			});
			routerObserver.observe(mediaFrame.$el[0], { childList: true, subtree: true });
		}
		
		// Activate the playlist state
		var stateObj = mediaFrame.state(config.state);
		if (stateObj) {
			mediaFrame.setState(config.state);
			
			// Listen to selection changes to keep updating button texts
			var selection = stateObj.get('selection');
			if (selection) {
				selection.on('add remove reset', function() {
					setTimeout(updatePlaylistButtonTexts, 10);
				});
			}
		}
		
		// Remove menu elements from DOM
		$('.media-modal .media-frame-menu').remove();
		$('.media-modal .media-frame-menu-heading').remove();
		$('.media-modal .media-frame-menu-toggle').remove();
		
		// Override title and button text
		$('.media-modal .media-frame-title h1').text(config.title);
		updatePlaylistButtonTexts();
		
		// Update button texts when content changes
		setTimeout(function() {
			updatePlaylistButtonTexts();
			ensureClearPlaylistButtonWithRetry(0);
		}, 100);
	});

	mediaFrame.on('router:render', function() {
		setTimeout(function() {
			ensureClearPlaylistButtonWithRetry(0);
		}, 10);
	});

	mediaFrame.on('content:render', function() {
		setTimeout(function() {
			ensureClearPlaylistButtonWithRetry(0);
		}, 10);
	});

	// Update button texts when state changes (switching tabs)
	mediaFrame.on('content:activate', function() {
		setTimeout(updatePlaylistButtonTexts, 50);
	});
	mediaFrame.on('toolbar:create:select', function() {
		setTimeout(updatePlaylistButtonTexts, 50);
	});
	mediaFrame.on('toolbar:render:select', function() {
		setTimeout(function() {
			updatePlaylistButtonTexts();
			ensureClearPlaylistButtonWithRetry(0);
		}, 50);
	});

	mediaFrame.on('update', function(selection) {
		var ids = getMediaSelectionIds(selection);
		saveMediaPlaylist(playlistType, ids);
	});

	mediaFrame.on('select', function() {
		var selection = mediaFrame.state().get('selection');
		var ids = getMediaSelectionIds(selection);
		if (ids.length) {
			saveMediaPlaylist(playlistType, ids);
		}
	});

	mediaFrame.on('close', function() {
		$('body').removeClass('tm-media-editor-open tm-media-playlist-editor tm-media-playlist-image tm-media-playlist-video tm-media-playlist-audio');
		$('.media-help-wrapper').remove();
		$('.help-tooltip').remove();
		$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
		if (routerObserver) {
			routerObserver.disconnect();
			routerObserver = null;
		}
		resumeBackgroundAfterEditing();
	});

	mediaFrame.open();
}

	function saveYoutubePlaylist(urls) {
		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_save_youtube_urls',
				nonce: vendorStoreData.nonce,
				user_id: vendorStoreData.userId,
				urls: urls
			},
			success: function(response) {
				if (response.success && response.data && response.data.vendorMedia) {
					window.vendorMedia = response.data.vendorMedia;
					playlist = buildPlaylist();
					state.index = 0;
					loadItem(0);
					showNotification(response.data.message || 'YouTube playlist updated', 'success');
				} else {
					showNotification(response.data && response.data.message ? response.data.message : 'Failed to update YouTube playlist', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	}

	function openYoutubePlaylistEditor() {
		// Build current URLs list from vendorMedia items
		var currentUrls = [];
		if (window.vendorMedia && Array.isArray(window.vendorMedia.items)) {
			window.vendorMedia.items.forEach(function(item) {
				if (item && item.type === 'youtube' && item.src) {
					currentUrls.push(item.src);
				}
			});
		}
		var currentText = currentUrls.join('\n');

		var $overlay = $('<div class="tm-yt-editor-overlay" role="dialog" aria-modal="true" aria-label="Edit YouTube Playlist"></div>');
		var $modal = $('<div class="tm-yt-editor-modal"></div>');
		$modal.html(
			'<div class="tm-yt-editor-header">' +
				'<h2 class="tm-yt-editor-title">YouTube Playlist</h2>' +
				'<button type="button" class="tm-yt-editor-close" aria-label="Close">&times;</button>' +
			'</div>' +
			'<div class="tm-yt-editor-body">' +
				'<p class="tm-yt-editor-help">Paste one YouTube URL per line. Supports youtube.com/watch?v=..., youtu.be/..., and shorts URLs.</p>' +
				'<textarea class="tm-yt-urls-textarea" rows="8" placeholder="https://www.youtube.com/watch?v=...">' + $('<div>').text(currentText).html() + '</textarea>' +
			'</div>' +
			'<div class="tm-yt-editor-footer">' +
				'<button type="button" class="button tm-yt-clear-btn">Clear All</button>' +
				'<div class="tm-yt-editor-actions">' +
					'<button type="button" class="button tm-yt-cancel-btn">Cancel</button>' +
					'<button type="button" class="button button-primary tm-yt-save-btn">Save Playlist</button>' +
				'</div>' +
			'</div>'
		);
		$overlay.append($modal);
		$('body').append($overlay);
		suspendBackgroundForEditing();
		$modal.find('.tm-yt-urls-textarea').focus();

		function closeEditor() {
			$overlay.remove();
			resumeBackgroundAfterEditing();
		}

		$overlay.on('click', '.tm-yt-editor-close, .tm-yt-cancel-btn', closeEditor);
		$overlay.on('click', function(e) {
			if ($(e.target).is($overlay)) { closeEditor(); }
		});
		$overlay.on('click', '.tm-yt-clear-btn', function() {
			if (window.confirm('Clear all YouTube videos from this playlist?')) {
				saveYoutubePlaylist([]);
				closeEditor();
			}
		});
		$overlay.on('click', '.tm-yt-save-btn', function() {
			var lines = $modal.find('.tm-yt-urls-textarea').val().split('\n');
			var urls = lines.map(function(s) { return s.trim(); }).filter(Boolean);
			saveYoutubePlaylist(urls);
			closeEditor();
		});
		// Close on Escape key
		$(document).one('keydown.tmyteditor', function(e) {
			if (e.key === 'Escape') { closeEditor(); $(document).off('keydown.tmyteditor'); }
		});
	}

// Avatar Edit - WordPress Media Library
	$(document).on('click', '.edit-avatar-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var $profileImg = $(this).closest('.profile-img');
		var vendorId = $profileImg.data('vendor-id');
		
		// Check if wp.media is available
		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('WordPress media library is not available');
			return;
		}
		
		// Create media frame
		var mediaFrame = wp.media({
			title: 'Select Avatar Image',
			button: {
				text: 'Use as Avatar'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		bindMediaEditorHelp(mediaFrame, 'Click to select a media then click on use as Avatar.');

		suspendBackgroundForEditing();

		// Ensure the library shows the vendor's uploads
		mediaFrame.on('open', function() {
			$('body').addClass('tm-media-editor-open');
			applyMediaEditorHelp('Click to select a media then click on use as Avatar.');
			var library = mediaFrame.state().get('library');
			if (library && library.props) {
				library.props.set('orderby', 'date');
				library.props.set('order', 'DESC');
				library.more();
			}
		});
		
		// When image is selected
		mediaFrame.on('select', function() {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var avatarId = attachment.id;
			var avatarUrl = attachment.url;
			
			// Update avatar via AJAX
			$.ajax({
				url: vendorStoreData.ajax_url,
				type: 'POST',
				data: {
					action: 'vendor_update_avatar',
					nonce: vendorStoreData.nonce,
					user_id: vendorId,
					avatar_id: avatarId
				},
				success: function(response) {
					if (response.success) {
						// Update all avatar images on the page
						$('.profile-img img').attr('src', response.data.avatar_url);
						
						// Show success message
						showNotification('Avatar updated successfully', 'success');
					} else {
						showNotification(response.data.message || 'Failed to update avatar', 'error');
					}
				},
				error: function() {
					showNotification('Network error', 'error');
				}
			});
		});

		mediaFrame.on('close', function() {
			$('body').removeClass('tm-media-editor-open');
			$('.media-help-wrapper').remove();
			$('.help-tooltip').remove();
			$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
			resumeBackgroundAfterEditing();
		});
		
		// Open media frame
		mediaFrame.open();
	});

	// Banner Edit - WordPress Media Library
	$(document).on('click', '.edit-banner-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();

		var $profileBox = $(this).closest('.profile-info-box');
		var vendorId = $profileBox.data('vendor-id');

		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('WordPress media library is not available');
			return;
		}

		var mediaFrame = wp.media({
			title: 'Select Banner Image',
			button: {
				text: 'Use as Banner'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		bindMediaEditorHelp(mediaFrame, 'Click to select a media then click on use as Banner.');

		suspendBackgroundForEditing();

		mediaFrame.on('open', function() {
			$('body').addClass('tm-media-editor-open');
			applyMediaEditorHelp('Click to select a media then click on use as Banner.');
			var library = mediaFrame.state().get('library');
			if (library && library.props) {
				library.props.set('orderby', 'date');
				library.props.set('order', 'DESC');
				library.more();
			}
		});

		mediaFrame.on('select', function() {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var bannerId = attachment.id;
			var bannerUrl = attachment.url;

			$.ajax({
				url: vendorStoreData.ajax_url,
				type: 'POST',
				data: {
					action: 'vendor_update_banner',
					nonce: vendorStoreData.nonce,
					user_id: vendorId,
					banner_id: bannerId
				},
				success: function(response) {
					if (response.success) {
						// Update poster on active slot and all buffer slots
						var newPoster = response.data.banner_url || bannerUrl;
						if (heroVideoActive) { $(heroVideoActive).attr("poster", newPoster); }
						if (videoBufferNext)  { videoBufferNext.setAttribute("poster", newPoster); }
						if (videoBufferPrev)  { videoBufferPrev.setAttribute("poster", newPoster); }
						$profileBox.find('.dummy-image').hide();
						showNotification('Banner updated successfully', 'success');
					} else {
						showNotification(response.data.message || 'Failed to update banner', 'error');
					}
				},
				error: function() {
					showNotification('Network error', 'error');
				}
			});
		});

		mediaFrame.on('close', function() {
			$('body').removeClass('tm-media-editor-open');
			$('.media-help-wrapper').remove();
			$('.help-tooltip').remove();
			$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
			resumeBackgroundAfterEditing();
		});

		mediaFrame.open();
	});

	// Media playlist editors (image/video/audio)
	$(document).on('click', '.edit-media-gallery-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		openMediaPlaylistEditor('image');
	});

	$(document).on('click', '.edit-media-video-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		openMediaPlaylistEditor('video');
	});

	$(document).on('click', '.edit-media-audio-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		openMediaPlaylistEditor('audio');
	});

	$(document).on('click', '.edit-media-youtube-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		openYoutubePlaylistEditor();
	});

	// Cinematic overlay tap/click on YouTube player — toggle play/pause.
	// The overlay covers the entire iframe and prevents any interaction with
	// YouTube's native UI (title, end-cards, logo). All playback is JS-API-driven.
	$(document).on('click', '.tm-yt-overlay', function(e) {
		e.stopPropagation();
		if (state.isPlaying) {
			pauseCurrent(true);
		} else {
			playCurrent();
		}
	});

	// YouTube seek bar — separate visual scrub (input) from actual seek (change/mouseup).
	// Stopping the poll during drag prevents the interval from overwriting the slider position.
	$(document).on('mousedown touchstart', '.hero-yt-seek', function(e) {
		e.stopPropagation();
		ytSeeking = true;
		stopYTProgressPolling(); // pause polling so getCurrentTime() doesn't fight the drag
	});

	// Visual-only updates while dragging — move the fill bar, thumb dot and time label without seeking
	$(document).on('input', '.hero-yt-seek', function(e) {
		e.stopPropagation();
		if (!ytPlayer) return;
		try {
			var durV = ytPlayer.getDuration() || 0;
			if (durV <= 0) return;
			var pct = parseFloat($(this).val()) / 1000;
			var fillPctV = pct * 100;
			$(".hero-yt-progress-fill").css("width", fillPctV + "%");
			$(".hero-yt-seek-thumb").css("left", fillPctV + "%");
			$(".hero-yt-time-current").text(formatYTTime(pct * durV));
		} catch(exV) {}
	});

	// Actual seek fires on mouseup (change) — then resume polling after YouTube catches up
	$(document).on('change', '.hero-yt-seek', function(e) {
		e.stopPropagation();
		ytSeeking = false;
		if (!ytPlayer || typeof ytPlayer.seekTo !== "function") return;
		try {
			var dur = ytPlayer.getDuration() || 0;
			if (dur <= 0) return;
			var targetSec = (parseFloat($(this).val()) / 1000) * dur;
			ytPlayer.seekTo(targetSec, true);
			// Wait 500ms for YouTube's getCurrentTime() to reflect the new position
			// before restarting the polling interval
			ytSeekResumeTimeout = setTimeout(function() {
				ytSeekResumeTimeout = null;
				if (state.isPlaying) { startYTProgressPolling(); }
				else { updateYTProgressBar(); }
			}, 500);
		} catch(ex) {}
	});
	
	// Store Name & Location Edit - Show/Hide Edit Form OR Open Modal
	$(document).on('click', '.editable-field .edit-field-btn', function(e) {
		console.log('ðŸ”§ Edit button clicked!');
		e.preventDefault();
		e.stopPropagation();

		suspendBackgroundForEditing();
		
		var $wrapper = $(this).closest('.editable-field');
		console.log('ðŸ“¦ Wrapper found:', $wrapper.length, 'Field:', $wrapper.data('field'));
		
		var fieldName = $wrapper.data('field');
		
		// Location uses unified modal system for consistency
		if ($wrapper.hasClass('location-wrapper')) {
			openFieldEditorModal('geo_location', $wrapper);
			return;
		}
		
		// Use new universal modal for other fields
		if (fieldName === 'store_categories') {
			openFieldEditorModal('store_categories', $wrapper);
		} else if (fieldName === 'contact_emails') {
			openFieldEditorModal('contact_emails', $wrapper);
		} else if (fieldName === 'contact_phones') {
			openFieldEditorModal('contact_phones', $wrapper);
		} else if (fieldName === 'store_name') {
			openFieldEditorModal('store_name', $wrapper);
		} else if ($wrapper.data('editor') === 'attribute') {
			openFieldEditorModal('attribute', $wrapper);
		}
	});

	// Modal backdrop click handler
	$(document).on('click', '.tm-field-editor-backdrop', function(e) {
		e.preventDefault();
		closeFieldEditorModal();
	});

	// Modal cancel button handler
	$(document).on('click', '.tm-field-editor-dialog .editor-cancel-btn', function(e) {
		e.preventDefault();
		closeFieldEditorModal();
	});

	// Modal save button handler
	$(document).on('click', '.tm-field-editor-dialog .editor-save-btn', function(e) {
		e.preventDefault();
		
		if (!currentFieldEditor.wrapper) {
			console.error('No current field editor context');
			return;
		}
		
		var $modal = $('.tm-field-editor-modal');
		var $body = $modal.find('.editor-body');
		var fieldName = currentFieldEditor.field;
		var $wrapper = currentFieldEditor.wrapper;
		
		console.log('ðŸ’¾ Saving field:', fieldName);
		
		if (fieldName === 'store_categories') {
			saveCategoriesField($body, $wrapper);
		} else if (fieldName === 'contact_emails') {
			saveContactEmailField($body, $wrapper);
		} else if (fieldName === 'contact_phones') {
			saveContactPhoneField($body, $wrapper);
		} else if (fieldName === 'store_name') {
			saveNameField($body, $wrapper);
		} else if (fieldName === 'geo_location') {
			saveLocationField($body, $wrapper);
		} else if (currentFieldEditor.fieldType === 'attribute') {
			saveAttributeField($body, $wrapper);
		}
	});

	$(document).on('click', '.tm-onboard-share-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();

		suspendBackgroundForEditing();
		openOnboardModal();

		requestOnboardLink();
	});

	$(document).on('click', '.tm-onboard-generate', function(e) {
		e.preventDefault();
		e.stopPropagation();
		requestOnboardLink($(this));
	});

	$(document).on('click', '.tm-onboard-avatar-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();

		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			showNotification('Media library unavailable.', 'error');
			return;
		}

		var frame = wp.media({
			title: 'Select Admin Avatar',
			button: { text: 'Use this avatar' },
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.url || '';
			var id = attachment.id || '';
			var $modal = $('.tm-field-editor-modal');
			$modal.find('.tm-onboard-avatar-id').val(id);
			$modal.find('.tm-onboard-avatar-url').val(url);
			if (url) {
				$modal.find('.tm-onboard-avatar-preview--admin').html('<img src="' + url + '" alt="Admin avatar" />');
			}
		});

		frame.open();
	});

	$(document).on('click', '.tm-onboard-vendor-avatar-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();

		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			showNotification('Media library unavailable.', 'error');
			return;
		}

		var frame = wp.media({
			title: 'Select Vendor Avatar',
			button: { text: 'Use this avatar' },
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.url || '';
			var id = attachment.id || '';
			var $modal = $('.tm-field-editor-modal');
			$modal.find('.tm-onboard-vendor-avatar-id').val(id);
			$modal.find('.tm-onboard-vendor-avatar-url').val(url);
			if (url) {
				$modal.find('.tm-onboard-avatar-preview--vendor').html('<img src="' + url + '" alt="Vendor avatar" />');
			}
		});

		frame.open();
	});

	$(document).on('click', '.tm-onboard-copy', function() {
		var $input = $('.tm-onboard-link');
		if (!$input.length) return;
		$input[0].select();
		$input[0].setSelectionRange(0, $input.val().length);
		try {
			document.execCommand('copy');
			showNotification('Link copied', 'success');
		} catch (e) {
			showNotification('Copy failed. Please copy manually.', 'error');
		}
	});

	if ($('.tm-onboard-claim-template').length) {
		openOnboardClaimModal();
	}

	// ESC key handler for modal
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && $('.tm-field-editor-modal').hasClass('is-open')) {
			closeFieldEditorModal();
		}
	});

	$(document).on("tm-account:open", function() {
		suspendBackgroundForEditing();
	});

	$(document).on("tm-account:close", function() {
		resumeBackgroundAfterEditing();
	});

	function syncAccountModalPlayback() {
		var isOpen = $('body').hasClass('tm-account-open') || $('#tm-account-modal').hasClass('is-open');
		if (isOpen) {
			suspendBackgroundForEditing();
		} else {
			resumeBackgroundAfterEditing();
		}
	}

	if (typeof MutationObserver !== 'undefined') {
		var observer = new MutationObserver(function() {
			syncAccountModalPlayback();
		});
		observer.observe(document.body, { attributes: true, attributeFilter: ['class'], subtree: false });
	}

	syncAccountModalPlayback();

	function retryAccountModalPlayback() {
		var attempts = 0;
		var maxAttempts = 12;
		var interval = setInterval(function() {
			attempts += 1;
			syncAccountModalPlayback();
			if (attempts >= maxAttempts) {
				clearInterval(interval);
			}
		}, 100);
	}

	$(document).on('tm-account:open', function() {
		retryAccountModalPlayback();
	});

	function saveCategoriesField($body, $wrapper) {
		var $select = $body.find('select[multiple]');
		var selectedValues = $select.val() || [];
		var selectedText = $select.find('option:selected').map(function() {
			return $(this).text();
		}).get().join(', ');
		var trimmedText = selectedText;
		if (selectedText.length > 30) {
			trimmedText = selectedText.substring(0, 30) + '...';
		}
		
		// Update display
		$wrapper.find('.field-value').text(trimmedText || 'Not set');
		$wrapper.find('.field-value').attr('title', selectedText);
		
		// Save via AJAX (reuse existing save logic)
		saveFieldToServer($wrapper, 'store_categories', selectedValues, selectedText);
		
		closeFieldEditorModal();
	}

	function saveContactEmailField($body, $wrapper) {
		var list = [];
		$body.find('.contact-email-input').each(function() {
			var val = $(this).val().trim();
			if (val) list.push(val);
		});
		
		var mainVal = '';
		var $checkedRow = $body.find('.contact-main-radio:checked').closest('.contact-edit-row');
		if ($checkedRow.length) {
			mainVal = $checkedRow.find('.contact-email-input').val().trim();
		}
		
		// Update display
		updateContactDisplay($wrapper, list, mainVal);
		
		// Save via AJAX
		saveContactField($wrapper, 'contact_emails', list, mainVal);
		
		closeFieldEditorModal();
	}

	function saveContactPhoneField($body, $wrapper) {
		var list = [];
		$body.find('.contact-phone-input').each(function() {
			var val = $(this).val().trim();
			if (val) list.push(val);
		});
		
		var mainVal = '';
		var $checkedRow = $body.find('.contact-main-radio:checked').closest('.contact-edit-row');
		if ($checkedRow.length) {
			mainVal = $checkedRow.find('.contact-phone-input').val().trim();
		}
		
		// Update display
		updateContactDisplay($wrapper, list, mainVal);
		
		// Save via AJAX
		saveContactField($wrapper, 'contact_phones', list, mainVal);
		
		closeFieldEditorModal();
	}

	function saveNameField($body, $wrapper) {
		var newName = $body.find('input[name="store_name"]').val().trim();
		
		// Update display
		$wrapper.find('.field-value').text(newName);
		
		// Save via AJAX
		saveFieldToServer($wrapper, 'store_name', newName, newName);
		
		closeFieldEditorModal();
	}

	function parseLocationCenter(locationData) {
		if (!locationData) return null;
		try {
			var parsed = JSON.parse(locationData);
			if (parsed && parsed.center && parsed.center.length === 2) {
				var lng = parseFloat(parsed.center[0]);
				var lat = parseFloat(parsed.center[1]);
				if (isFinite(lat) && isFinite(lng)) {
					return { lat: lat, lng: lng };
				}
			}
		} catch (e) {}
		return null;
	}

	function syncLocationDom($wrapper, newLocation, locationData, response) {
		if (!$wrapper || !$wrapper.length) return;
		var $input = $wrapper.find('.location-search-input');
		if ($input.length) {
			$input.val(newLocation || '');
			$input.attr('value', newLocation || '');
		}
		if (response && response.data && response.data.location_data) {
			locationData = response.data.location_data;
		}
		var $dataInput = $wrapper.find('input[name="location_data"]');
		if ($dataInput.length) {
			$dataInput.val(locationData || '');
		}
		var center = parseLocationCenter(locationData);
		if (!center) return;
		var $panel = $wrapper.find('.inline-mapbox-panel').first();
		if (!$panel.length) return;
		$panel.data('lat', center.lat);
		$panel.data('lng', center.lng);
		$panel.attr('data-lat', center.lat);
		$panel.attr('data-lng', center.lng);
	}

	function saveLocationField($body, $wrapper) {
		var $input = $body.find('input.location-search-input');
		var $dataInput = $body.find('input[name="location_data"]');
		var newLocation = $input.val().trim();
		var locationData = $dataInput.val() || '';
		
		// Also check Mapbox geocoder input if location is empty
		if (!newLocation) {
			var geocoderValue = $body.find('.mapboxgl-ctrl-geocoder--input').val();
			if (geocoderValue && geocoderValue.trim()) {
				newLocation = geocoderValue.trim();
			}
		}
		
		var vendorId = $('.profile-img').data('vendor-id');
		
		if (!newLocation) {
			showNotification('Location cannot be empty', 'error');
			return;
		}
		
		// Save via AJAX
		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_location',
				nonce: vendorStoreData.nonce,
				user_id: vendorId,
				geo_address: newLocation,
				location_data: locationData
			},
			success: function(response) {
				if (response.success) {
					// Update display
					if (response.data.geo_display) {
						$wrapper.find('.field-value').html(response.data.geo_display);
					} else {
						$wrapper.find('.field-value').text(response.data.geo_address);
					}
					syncLocationDom($wrapper, newLocation, locationData, response);
					
					showNotification('Location updated successfully', 'success');
					closeFieldEditorModal();
				} else {
					showNotification(response.data || 'Failed to update location', 'error');
				}
			},
			error: function() {
				showNotification('Error updating location', 'error');
			}
		});
	}

	function saveAttributeField($body, $wrapper) {
		var fieldName = $wrapper.data('field');
		var inputType = $wrapper.data('inputType') || 'select';
		var isMulti = $wrapper.data('multi') === 1 || $wrapper.data('multi') === true || $wrapper.data('multi') === '1';
		var $input = $body.find('.edit-field-input').first();
		var fieldValue = '';
		var displayText = 'Not set';

		if (inputType === 'date') {
			fieldValue = $input.val() ? $input.val().trim() : '';
			if (fieldName === 'demo_birth_date' && fieldValue) {
				var birthDate = new Date(fieldValue);
				var today = new Date();
				var age = today.getFullYear() - birthDate.getFullYear();
				var monthDiff = today.getMonth() - birthDate.getMonth();
				if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
					age--;
				}
				displayText = age + ' years';
			} else if (fieldValue) {
				displayText = fieldValue;
			}
			$wrapper.data('rawValue', fieldValue).attr('data-raw-value', fieldValue);
		} else if (isMulti) {
			fieldValue = $input.val() || [];
			var labels = [];
			$input.find('option:selected').each(function() {
				labels.push($(this).text());
			});
			displayText = labels.join(', ') || 'Not set';
			$wrapper.data('values', fieldValue);
			$wrapper.attr('data-values', JSON.stringify(fieldValue));
		} else {
			fieldValue = $input.val();
			if ($input.is('select')) {
				displayText = $input.find('option:selected').text() || 'Not set';
			} else if (fieldValue) {
				displayText = fieldValue;
			}
			$wrapper.data('value', fieldValue || '');
			$wrapper.attr('data-value', fieldValue || '');
		}

		$wrapper.find('.field-value').text(displayText);

		var vendorId = (window.vendorStoreData && window.vendorStoreData.userId)
			? window.vendorStoreData.userId
			: parseInt($('.profile-info-box').data('vendorId'), 10) || 0;

		$.ajax({
			url: vendorStoreData.ajaxurl,
			method: 'POST',
			data: {
				action: 'vendor_save_attribute',
				nonce: vendorStoreData.editNonce,
				user_id: vendorId,
				field: fieldName,
				value: fieldValue
			},
			success: function(response) {
				if (response.success) {
					if (inputType === 'date') {
						var savedValue = (response.data && typeof response.data.value !== 'undefined')
							? response.data.value
							: fieldValue;
						syncDateAttributeDom($wrapper, savedValue);
					}
					showNotification(response.data?.message || 'Field updated', 'success');
				} else {
					showNotification('Failed to save field', 'error');
				}
			},
			error: function(jqXHR) {
				if (jqXHR.status === 200) {
					// HTTP 200 = server saved the data, but PHP output corrupted the JSON response.
					console.warn('âš ï¸ Response parse issue for field:', fieldName, '- treating as success (HTTP 200)');
					showNotification(response && response.data && response.data.message ? response.data.message : 'Field updated', 'success');
				} else {
					showNotification('Connection error', 'error');
				}
			}
		});

		closeFieldEditorModal();
	}

	function updateContactDisplay($wrapper, list, mainVal) {
		var displayVal = mainVal || (list.length > 0 ? list[0] : 'Not set');
		var extraCount = mainVal ? list.length - 1 : Math.max(0, list.length - 1);
		
		$wrapper.find('.contact-value').text(displayVal);
		
		var $extraCount = $wrapper.find('.contact-extra-count');
		if (extraCount > 0) {
			$extraCount.text('(+' + extraCount + ')').show();
		} else {
			$extraCount.hide();
		}
	}

	// Helper function to save field data via AJAX
	function saveFieldToServer($wrapper, fieldName, fieldValue, displayValue) {
		var vendorId = $('.profile-img').data('vendor-id') || $('.profile-info-box').data('vendor-id');
		var ajaxData;

		if (fieldName === 'store_categories') {
			ajaxData = {
				action: 'vendor_save_attribute',
				nonce: (window.vendorStoreData && window.vendorStoreData.editNonce) ? window.vendorStoreData.editNonce : '',
				user_id: vendorId,
				field: 'store_categories',
				value: fieldValue
			};
		} else if (fieldName === 'store_name') {
			ajaxData = {
				action: 'vendor_update_store_name',
				nonce: (window.vendorStoreData && window.vendorStoreData.nonce) ? window.vendorStoreData.nonce : '',
				user_id: vendorId,
				store_name: fieldValue
			};
		} else {
			ajaxData = {
				action: 'vendor_save_attribute',
				nonce: (window.vendorStoreData && window.vendorStoreData.editNonce) ? window.vendorStoreData.editNonce : '',
				user_id: vendorId,
				field: fieldName,
				value: fieldValue
			};
		}
		
		$.ajax({
			url: (window.vendorStoreData && window.vendorStoreData.ajaxurl) ? window.vendorStoreData.ajaxurl : '/wp-admin/admin-ajax.php',
			type: 'POST',
			data: ajaxData,
			success: function(response) {
				if (response.success) {
					console.log('âœ… Field saved:', fieldName, response);
					if (typeof fieldName === 'string' && fieldName.indexOf('social_') === 0) {
						showNotification('URL saved. Refreshing metrics...', 'success');
						var platform = getSocialPlatformFromField(fieldName);
						if (platform) {
							updateSocialStatusLabel(platform, 'fetching data... may take few minutes');
							renderSocialStats(platform, { fetching: true, error: false, has_url: true, metrics: {} });
							startSocialPolling(platform);
						}
						var $input = $wrapper.find('.social-url-input');
						if ($input.length) {
							$input.data('original', $input.val() || '');
						}
					} else {
						showNotification('Updated successfully', 'success');
					}
				} else {
					console.error('âŒ Field save failed:', fieldName, response);
					showNotification(response.data.message || 'Failed to update', 'error');
				}
			},
			error: function(jqXHR) {
				if (jqXHR.status === 200) {
					showNotification('Updated successfully', 'success');
				} else {
					showNotification('Network error', 'error');
				}
			}
		});
	}

	var socialPollTimers = {};
	var socialErrorCounts = {};

	function getSocialPlatformFromField(fieldName) {
		if (!fieldName) return '';
		if (fieldName.indexOf('social_') !== 0) return '';
		return fieldName.replace('social_', '').toLowerCase();
	}

	function normalizeSocialUrl(url, platform) {
		var value = (url || '').trim();
		if (!value) return '';
		if (!/^https?:\/\//i.test(value)) {
			value = 'https://' + value.replace(/^\/+/, '');
		}
		try {
			var parsed = new URL(value);
			if (!(platform === 'facebook' && parsed.pathname === '/profile.php' && parsed.searchParams.get('id'))) {
				parsed.search = '';
			}
			parsed.hash = '';
			var host = (parsed.hostname || '').toLowerCase().replace(/^www\./, '');
			var path = (parsed.pathname || '').trim();
			if (platform === 'youtube' && host === 'youtube.com' && /^\/channel[^/]/i.test(path)) {
				path = path.replace(/^\/channel/i, '/channel/');
				parsed.pathname = path;
			}
			if (platform === 'youtube' && host === 'youtube.com' && /^\/@[^/]+$/i.test(path)) {
				parsed.pathname = path.replace(/^\/@/i, '/@');
			}
			if (platform === 'instagram') {
				parsed.pathname = path.replace(/\/+$/, '');
			}
			if (platform === 'facebook') {
				parsed.pathname = path.replace(/\/+$/, '');
			}
			value = parsed.toString();
		} catch (e) {
			return value;
		}
		return value;
	}

	function isValidSocialUrl(platform, url) {
		if (!url) return false;
		var parsed;
		try {
			parsed = new URL(url);
		} catch (e) {
			return false;
		}
		var host = (parsed.hostname || '').toLowerCase().replace(/^www\./, '');
		var path = (parsed.pathname || '').trim();
		if (!path || path === '/') return false;
		switch (platform) {
			case 'youtube':
				if (host !== 'youtube.com') return false;
				if (/^\/@[^/]+$/i.test(path)) return true;
				return /^\/(channel|user|c)\//i.test(path);
			case 'instagram':
				if (host !== 'instagram.com') return false;
				var segments = path.replace(/^\/+|\/+$/g, '').split('/');
				if (segments.length !== 1) return false;
				var handle = segments[0].toLowerCase();
				var reserved = ['p', 'reel', 'reels', 'stories', 'explore', 'tv', 'accounts', 'about', 'developer', 'directory', 'tags', 'locations'];
				if (reserved.indexOf(handle) !== -1) return false;
				return true;
			case 'facebook':
				if (host !== 'facebook.com') return false;
				if (path.toLowerCase().indexOf('/profile.php') === 0) {
					return !!parsed.searchParams.get('id');
				}
				if (path.toLowerCase().indexOf('/people/') === 0) {
					var peopleSegments = path.replace(/^\/+|\/+$/g, '').split('/');
					return peopleSegments.length >= 3 && /^\d+$/.test(peopleSegments[peopleSegments.length - 1]);
				}
				var fbSegments = path.replace(/^\/+|\/+$/g, '').split('/');
				if (fbSegments.length !== 1) return false;
				var fbHandle = fbSegments[0].toLowerCase();
				var fbReserved = ['pages', 'profile.php', 'home.php', 'people', 'groups', 'events', 'watch', 'marketplace', 'login', 'settings', 'help', 'plugins', 'privacy'];
				if (fbReserved.indexOf(fbHandle) !== -1) return false;
				return true;
			case 'linkedin':
				if (host !== 'linkedin.com') return false;
				return /^\/(in|company|school)\//i.test(path);
			default:
				return true;
		}
	}

	function formatSocialNumber(value) {
		var num = parseInt(value, 10);
		if (isNaN(num)) return '';
		if (num >= 1000000) {
			return (Math.round((num / 1000000) * 10) / 10) + 'M';
		}
		if (num >= 1000) {
			return (Math.round((num / 1000) * 10) / 10) + 'K';
		}
		return num.toLocaleString();
	}

	function updateSocialStatusLabel(platform, statusText) {
		var $field = $('.social-url-field[data-platform="' + platform + '"]');
		if (!$field.length) return;
		var $label = $field.find('.social-url-label');
		if (!$label.length) return;
		var $status = $label.find('.social-url-status');
		if (statusText && statusText.length) {
			if (!$status.length) {
				$status = $('<span class="social-url-status"></span>');
				$label.append($status);
			}
			$status.text(' (' + statusText + ')');
		} else if ($status.length) {
			$status.remove();
		}
	}

	function getPlatformDisplayName(platform) {
		switch (platform) {
			case 'youtube':
				return 'YouTube';
			case 'instagram':
				return 'Instagram';
			case 'facebook':
				return 'Facebook';
			case 'linkedin':
				return 'LinkedIn';
			default:
				return platform ? platform.charAt(0).toUpperCase() + platform.slice(1) : '';
		}
	}

	function getStatValueClass(color) {
		if (!color) return '';
		switch (String(color).toLowerCase()) {
			case '#d4af37':
				return 'stat-value--gold';
			case '#ffd1bf':
				return 'stat-value--rose';
			default:
				return '';
		}
	}

	function buildStatItem(iconClass, label, value, color) {
		var valueClass = getStatValueClass(color);
		var valueClassAttr = valueClass ? ' class="' + valueClass + '"' : '';
		return '<div class="stat-item">'
			+ '<i class="fas ' + iconClass + ' stat-icon"></i> '
			+ label + ': <strong' + valueClassAttr + '>' + value + '</strong>'
			+ '</div>';
	}

	function buildMessage(text) {
		return '<div class="stat-item stat-item--muted">' + text + '</div>';
	}

	function getSocialInputUrl(platform) {
		var $input = $('.social-url-field[data-platform="' + platform + '"] .social-url-input');
		if (!$input.length) return '';
		return ($input.val() || '').trim();
	}

	function updateSocialHeaderLink(platform, hasUrl) {
		var $column = $('.social-metric-column[data-platform="' + platform + '"]');
		if (!$column.length) return;
		var $header = $column.find('.social-header').first();
		if (!$header.length) return;
		var $info = $header.find('div').first();
		if (!$info.length) {
			$info = $header;
		}
		var $link = $info.find('a').first();
		var $notConnected = $info.find('span').filter(function() {
			return $(this).text().trim() === 'Not connected';
		}).first();
		var url = hasUrl ? getSocialInputUrl(platform) : '';
		if (hasUrl && url) {
			if (!$link.length) {
				$link = $('<a target="_blank" class="social-profile-link"><i class="fas fa-external-link-alt social-profile-link-icon"></i> View Profile</a>');
				$info.append($link);
			}
			$link.attr('href', url).show();
			if ($notConnected.length) {
				$notConnected.hide();
			}
		} else {
			if ($link.length) {
				$link.hide();
			}
			if (!$notConnected.length) {
				$notConnected = $('<span style="font-size: 10px; color: #666;">Not connected</span>');
				$info.append($notConnected);
			} else {
				$notConnected.show();
			}
		}
	}

	function renderSocialStats(platform, payload) {
		var $column = $('.social-metric-column[data-platform="' + platform + '"]');
		if (!$column.length) return;
		var $stats = $column.find('.social-stats');
		if (!$stats.length) return;

		var metrics = (payload && payload.metrics) ? payload.metrics : {};
		var hasUrl = payload && payload.has_url;
		var html = '';
		updateSocialHeaderLink(platform, hasUrl);

		if (payload && payload.fetching) {
			switch (platform) {
				case 'youtube':
					html += buildStatItem('fa-users', 'Subscribers', '...', '#D4AF37');
					html += buildStatItem('fa-chart-bar', 'Avg Views', '...', '#FFD1BF');
					html += buildStatItem('fa-heart', 'Avg Reactions', '...', '#FFD1BF');
					break;
				case 'instagram':
					html += buildStatItem('fa-users', 'Followers', '...', '#D4AF37');
					html += buildStatItem('fa-heart', 'Avg Reactions', '...', '#FFD1BF');
					html += buildStatItem('fa-comment-dots', 'Avg Comments', '...', '#FFD1BF');
					break;
				case 'facebook':
					html += buildStatItem('fa-users', 'Followers', '...', '#D4AF37');
					html += buildStatItem('fa-eye', 'Avg Views', '...', '#FFD1BF');
					html += buildStatItem('fa-thumbs-up', 'Avg Reactions', '...', '#FFD1BF');
					break;
				case 'linkedin':
					html += buildStatItem('fa-users', 'Followers', '...', '#D4AF37');
					html += buildStatItem('fa-heart', 'Avg Reactions', '...', '#FFD1BF');
					html += buildStatItem('fa-user-friends', 'Connections', '...', '#FFD1BF');
					break;
			}
			$stats.html(html);
			return;
		}

		if (payload && payload.error) {
			$stats.html(buildMessage('error'));
			return;
		}

		if (payload && payload.stats_hidden) {
			switch (platform) {
				case 'facebook':
					html += buildStatItem('fa-users', 'Followers', 'N/A', '#D4AF37');
					html += buildStatItem('fa-eye', 'Avg Views', 'N/A', '#FFD1BF');
					html += buildStatItem('fa-thumbs-up', 'Avg Reactions', 'N/A', '#FFD1BF');
					break;
			}
			$stats.html(html);
			return;
		}

		switch (platform) {
			case 'youtube':
				if (metrics.subscribers !== null && typeof metrics.subscribers !== 'undefined') {
					html += buildStatItem('fa-users', 'Subscribers', formatSocialNumber(metrics.subscribers), '#D4AF37');
				}
				if (metrics.avg_views !== null && typeof metrics.avg_views !== 'undefined') {
					html += buildStatItem('fa-chart-bar', 'Avg Views', formatSocialNumber(metrics.avg_views), '#FFD1BF');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', 'Avg Reactions', formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				break;
			case 'instagram':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', 'Followers', formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', 'Avg Reactions', formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				if (metrics.avg_comments !== null && typeof metrics.avg_comments !== 'undefined') {
					html += buildStatItem('fa-comment-dots', 'Avg Comments', formatSocialNumber(metrics.avg_comments), '#FFD1BF');
				}
				break;
			case 'facebook':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', 'Followers', formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_views !== null && typeof metrics.avg_views !== 'undefined') {
					html += buildStatItem('fa-eye', 'Avg Views', formatSocialNumber(metrics.avg_views), '#FFD1BF');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-thumbs-up', 'Avg Reactions', formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				break;
			case 'linkedin':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', 'Followers', formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', 'Avg Reactions', formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				if (metrics.connections !== null && typeof metrics.connections !== 'undefined') {
					html += buildStatItem('fa-user-friends', 'Connections', formatSocialNumber(metrics.connections), '#FFD1BF');
				}
				break;
		}

		if (!html) {
			if (!hasUrl) {
				html = buildMessage('No ' + getPlatformDisplayName(platform) + ' URL provided');
			} else {
				html = buildMessage('Click Fetch Metrics to load data');
			}
		}

		$stats.html(html);
	}

	function fetchSocialStatus(platform) {
		if (!window.vendorStoreData || !vendorStoreData.ajaxurl) return $.Deferred().reject().promise();
		return $.ajax({
			url: vendorStoreData.ajaxurl,
			type: 'POST',
			data: {
				action: 'tm_social_metrics_status',
				nonce: vendorStoreData.editNonce,
				vendor_id: vendorStoreData.userId,
				platform: platform
			}
		});
	}

	window.tmSocialDebugDump = function(platform) {
		if (!platform) return;
		if (!window.vendorStoreData || !vendorStoreData.ajaxurl) return;
		$.ajax({
			url: vendorStoreData.ajaxurl,
			type: 'POST',
			data: {
				action: 'tm_social_debug_dump',
				nonce: vendorStoreData.editNonce,
				vendor_id: vendorStoreData.userId,
				platform: platform
			}
		}).done(function(response) {
			console.log('TM Social Debug Dump:', platform, response);
		}).fail(function(xhr) {
			console.error('TM Social Debug Dump failed:', platform, xhr && xhr.responseText);
		});
	};

	window.tmSocialDebugEnabled = window.tmSocialDebugEnabled || false;

	function startSocialPolling(platform) {
		if (!platform) return;
		if (socialPollTimers[platform]) {
			clearInterval(socialPollTimers[platform].timerId);
		}
		var attempts = 0;
		var maxAttempts = 20;
		var intervalMs = 12000;

		var poll = function() {
			attempts++;
			fetchSocialStatus(platform).done(function(response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				var data = response.data;
				if (window.tmSocialDebugEnabled && platform === 'facebook') {
					var m = data.metrics || {};
					var isZero = (m.followers === 0 || m.followers === null || typeof m.followers === 'undefined')
						&& (m.avg_views === 0 || m.avg_views === null || typeof m.avg_views === 'undefined')
						&& (m.avg_reactions === 0 || m.avg_reactions === null || typeof m.avg_reactions === 'undefined');
					if (data.error || isZero) {
						console.log('TM Social Debug Status:', platform, data);
					}
				}
				if (!socialErrorCounts[platform]) {
					socialErrorCounts[platform] = 0;
				}
				if (data.error) {
					socialErrorCounts[platform] += 1;
					if (socialErrorCounts[platform] < 3) {
						data.error = false;
						data.fetching = true;
						if (typeof data.status_text === 'string' && data.status_text.indexOf('fetch failed') === 0) {
							data.status_text = 'fetching data... may take few minutes';
						}
					}
				} else {
					socialErrorCounts[platform] = 0;
				}
				if (typeof data.status_text !== 'undefined') {
					updateSocialStatusLabel(platform, data.status_text);
				}
				renderSocialStats(platform, data);
				if (!data.fetching) {
					clearInterval(socialPollTimers[platform].timerId);
					delete socialPollTimers[platform];
				}
			});
			if (attempts >= maxAttempts && socialPollTimers[platform]) {
				clearInterval(socialPollTimers[platform].timerId);
				delete socialPollTimers[platform];
			}
		};

		socialPollTimers[platform] = {
			timerId: setInterval(poll, intervalMs)
		};
		setTimeout(poll, 1000);
	}

	function initSocialPollingFromDom() {
		$('.social-url-field[data-platform]').each(function() {
			var $field = $(this);
			var platform = ($field.data('platform') || '').toString();
			if (!platform) return;
			var statusText = $field.find('.social-url-status').text() || '';
			if (statusText.toLowerCase().indexOf('fetching') !== -1) {
				startSocialPolling(platform);
			}
		});
	}

	$(function() {
		initSocialPollingFromDom();
	});

	// Helper function to save contact field data via AJAX
	function saveContactField($wrapper, fieldName, list, mainVal) {
		var vendorId = $('.profile-img').data('vendor-id');
		var ajaxData = {
			action: 'vendor_update_contact_info',
			nonce: window.vendorStoreData ? window.vendorStoreData.nonce : '',
			user_id: vendorId
		};
		
		if (fieldName === 'contact_emails') {
			ajaxData.contact_emails = list;
			ajaxData.contact_email_main = mainVal;
		} else if (fieldName === 'contact_phones') {
			ajaxData.contact_phones = list;
			ajaxData.contact_phone_main = mainVal;
		}
		
		$.ajax({
			url: (window.vendorStoreData && window.vendorStoreData.ajax_url) ? window.vendorStoreData.ajax_url : '/wp-admin/admin-ajax.php',
			type: 'POST',
			data: ajaxData,
			success: function(response) {
				if (response.success) {
					console.log('âœ… Contact field saved:', fieldName);
					showNotification('Contact updated successfully', 'success');
					
					// Update wrapper data
					if (fieldName === 'contact_emails') {
						$wrapper.data('originalList', response.data.contact_emails || []);
						$wrapper.data('originalMain', response.data.contact_email_main || '');
					} else if (fieldName === 'contact_phones') {
						$wrapper.data('originalList', response.data.contact_phones || []);
						$wrapper.data('originalMain', response.data.contact_phone_main || '');
					}
				} else {
					console.error('âŒ Contact save failed:', response.data);
					showNotification(response.data.message || 'Failed to update contact', 'error');
				}
			},
			error: function(jqXHR) {
				if (jqXHR.status === 200) {
					showNotification('Contact updated successfully', 'success');
				} else {
					showNotification('Network error', 'error');
				}
			}
		});
	}
	
	function buildContactRow(type, value, isMain, radioName) {
		var inputType = type === 'email' ? 'email' : 'text';
		var $row = $('<div/>', { class: 'contact-edit-row' });
		var $input = $('<input/>', {
			type: inputType,
			class: 'edit-field-input contact-list-input contact-' + type + '-input',
			value: value || ''
		});
		var $label = $('<label/>', { class: 'contact-main-choice' });
		var $radio = $('<input/>', {
			type: 'radio',
			class: 'contact-main-radio',
			name: radioName
		});
		if (isMain) {
			$radio.prop('checked', true);
		}
		$label.append($radio, $('<span/>').text('Main'));
		$row.append($input, $label);
		return $row;
	}

	function renderContactList($wrapper, list, mainValue) {
		var $list = $wrapper.find('.contact-edit-list');
		var type = $list.data('type');
		var radioName = $list.data('radio-name');
		var items = Array.isArray(list) ? list : [];
		while (items.length < 3) {
			items.push('');
		}
		items = items.slice(0, 3);
		var mainSet = false;
		$list.empty();
		items.forEach(function(value, index) {
			var isMain = mainValue && value === mainValue;
			if (!mainSet && isMain) {
				mainSet = true;
			}
			$list.append(buildContactRow(type, value, isMain, radioName));
		});
		if (!mainSet) {
			$list.find('.contact-main-radio').first().prop('checked', true);
		}
		updateContactMainSummary($wrapper);
	}

	function getContactListData($wrapper) {
		var list = [];
		$wrapper.find('.contact-list-input').each(function() {
			list.push($(this).val().trim());
		});
		var mainVal = '';
		var $checkedRow = $wrapper.find('.contact-main-radio:checked').closest('.contact-edit-row');
		if ($checkedRow.length) {
			mainVal = $checkedRow.find('.contact-list-input').val().trim();
		}
		return { list: list, main: mainVal };
	}

	function normalizeContactList(list) {
		var cleaned = [];
		list.forEach(function(value) {
			var item = value.trim();
			if (item) {
				cleaned.push(item);
			}
		});
		return cleaned;
	}

	function updateContactMainSummary($wrapper) {
		var mainVal = '';
		var $checkedRow = $wrapper.find('.contact-main-radio:checked').closest('.contact-edit-row');
		if ($checkedRow.length) {
			mainVal = $checkedRow.find('.contact-list-input').val().trim();
		}
		if (!mainVal) {
			$wrapper.find('.contact-list-input').each(function() {
				if (!mainVal && $(this).val().trim()) {
					mainVal = $(this).val().trim();
				}
			});
		}
		$wrapper.find('.contact-main-summary .contact-value').text(mainVal || 'Not set');
	}

	$(document).on('change', '.contact-edit-list .contact-main-radio', function() {
		var $wrapper = $(this).closest('.editable-field');
		updateContactMainSummary($wrapper);
	});

	$(document).on('input', '.contact-edit-list .contact-list-input', function() {
		var $wrapper = $(this).closest('.editable-field');
		if ($(this).closest('.contact-edit-row').find('.contact-main-radio').is(':checked')) {
			updateContactMainSummary($wrapper);
		}
	});

	// Save Contact Email
	$(document).on('click', '.contact-email-wrapper .save-field-btn', function(e) {
		e.preventDefault();

		var $wrapper = $(this).closest('.contact-email-wrapper');
		var listData = getContactListData($wrapper);
		var contactEmails = normalizeContactList(listData.list).slice(0, 3);
		var contactEmailMain = listData.main;
		if (!contactEmailMain && contactEmails.length) {
			contactEmailMain = contactEmails[0];
		}
		var vendorId = $('.profile-img').data('vendor-id');
		var $profileHead = $wrapper.closest('.profile-info-head');

		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_contact_info',
				nonce: vendorStoreData.nonce,
				user_id: vendorId,
				contact_emails: contactEmails,
				contact_email_main: contactEmailMain
			},
			success: function(response) {
				if (response.success) {
					var emailDisplay = response.data.contact_email_main ? response.data.contact_email_main : 'Not set';
					var emailCount = Array.isArray(response.data.contact_emails) ? Math.max(response.data.contact_emails.length - 1, 0) : 0;
					$wrapper.find('.contact-email-value').text(emailDisplay);
					var $count = $wrapper.find('.contact-email-count');
					if (emailCount > 0) {
						if (!$count.length) {
							$wrapper.find('.contact-email-value').after('<span class="contact-extra-count contact-email-count"></span>');
							$count = $wrapper.find('.contact-email-count');
						}
						$count.text('(+'+ emailCount + ')');
					} else if ($count.length) {
						$count.remove();
					}
					renderContactList($wrapper, response.data.contact_emails || [], response.data.contact_email_main || '');
					$wrapper.data('originalList', response.data.contact_emails || []);
					$wrapper.data('originalMain', response.data.contact_email_main || '');
					$wrapper.find('.field-edit').hide();
					$wrapper.find('.field-display').show();
					$wrapper.removeClass('editing');
					$wrapper.closest('.profile-info-content').removeClass('view-mode-editing');
					cleanupEditingMode($wrapper);
					if ($profileHead.length) {
						$profileHead.removeClass('is-editing');
					}
					showNotification('Contact email updated successfully', 'success');
				} else {
					showNotification(response.data.message || 'Failed to update contact email', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	});

	// Save Contact Phone
	$(document).on('click', '.contact-phone-wrapper .save-field-btn', function(e) {
		e.preventDefault();

		var $wrapper = $(this).closest('.contact-phone-wrapper');
		var listData = getContactListData($wrapper);
		var contactPhones = normalizeContactList(listData.list).slice(0, 3);
		var contactPhoneMain = listData.main;
		if (!contactPhoneMain && contactPhones.length) {
			contactPhoneMain = contactPhones[0];
		}
		var vendorId = $('.profile-img').data('vendor-id');
		var $profileHead = $wrapper.closest('.profile-info-head');

		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_contact_info',
				nonce: vendorStoreData.nonce,
				user_id: vendorId,
				contact_phones: contactPhones,
				contact_phone_main: contactPhoneMain
			},
			success: function(response) {
				if (response.success) {
					var phoneDisplay = response.data.contact_phone_main ? response.data.contact_phone_main : 'Not set';
					var phoneCount = Array.isArray(response.data.contact_phones) ? Math.max(response.data.contact_phones.length - 1, 0) : 0;
					$wrapper.find('.contact-phone-value').text(phoneDisplay);
					var $count = $wrapper.find('.contact-phone-count');
					if (phoneCount > 0) {
						if (!$count.length) {
							$wrapper.find('.contact-phone-value').after('<span class="contact-extra-count contact-phone-count"></span>');
							$count = $wrapper.find('.contact-phone-count');
						}
						$count.text('(+'+ phoneCount + ')');
					} else if ($count.length) {
						$count.remove();
					}
					renderContactList($wrapper, response.data.contact_phones || [], response.data.contact_phone_main || '');
					$wrapper.data('originalList', response.data.contact_phones || []);
					$wrapper.data('originalMain', response.data.contact_phone_main || '');
					$wrapper.find('.field-edit').hide();
					$wrapper.find('.field-display').show();
					$wrapper.removeClass('editing');
					$wrapper.closest('.profile-info-content').removeClass('view-mode-editing');
					cleanupEditingMode($wrapper);
					if ($profileHead.length) {
						$profileHead.removeClass('is-editing');
					}
					showNotification('Phone updated successfully', 'success');
				} else {
					showNotification(response.data.message || 'Failed to update phone', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	});

	// Cancel Contact Email/Phone
	$(document).on('click', '.contact-email-wrapper .cancel-field-btn, .contact-phone-wrapper .cancel-field-btn', function(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		var $wrapper = $(this).closest('.contact-email-wrapper, .contact-phone-wrapper');
		var $profileHead = $wrapper.closest('.profile-info-head');
		var originalList = $wrapper.data('originalList') || [];
		var originalMain = $wrapper.data('originalMain') || '';
		renderContactList($wrapper, originalList, originalMain);
		$wrapper.find('.field-edit').hide();
		$wrapper.find('.field-display').show();
		$wrapper.removeClass('editing');
		$wrapper.closest('.profile-info-content').removeClass('view-mode-editing');
		cleanupEditingMode($wrapper);
		if ($profileHead.length) {
			$profileHead.removeClass('is-editing');
		}
	});

	$(document).on('focus', '.social-url-input', function() {
		var $input = $(this);
		$input.data('original', $input.val() || '');
	});

	$(document).on('blur', '.social-url-input', function() {
		var $input = $(this);
		var fieldName = $input.data('field');
		if (!fieldName) return;
		var currentValue = $input.val() || '';
		var originalValue = $input.data('original') || '';
		var platform = getSocialPlatformFromField(fieldName);
		var normalizedValue = normalizeSocialUrl(currentValue, platform);
		var normalizedOriginal = normalizeSocialUrl(originalValue, platform);
		if (normalizedValue === normalizedOriginal) return;
		if (platform && !isValidSocialUrl(platform, normalizedValue)) {
			showNotification('Please enter a valid ' + getPlatformDisplayName(platform) + ' URL', 'error');
			$input.val(originalValue);
			return;
		}
		$input.val(normalizedValue);
		var $wrapper = $input.closest('.social-url-field');
		saveFieldToServer($wrapper, fieldName, normalizedValue, normalizedValue);
	});

	function openLocationModal($wrapper) {
		var $modal = $('.tm-location-modal');
		if (!$modal.length) return;

		suspendBackgroundForEditing();

		var $edit = $wrapper.find('.field-edit').first();
		if (!$edit.length) return;

		var $profileHead = $wrapper.closest('.profile-info-head');
		$edit.data('tm-origin', $wrapper);
		$edit.show();
		$modal.find('.tm-location-modal__content').empty().append($edit);
		$modal.addClass('is-open').attr('aria-hidden', 'false');
		$('body').addClass('tm-modal-open');
		if ($profileHead.length) {
			$profileHead.removeClass('is-collapsed');
			writeStoredBool(STORAGE_KEYS.collapsed, false);
		}
		initInlineLocationMap($modal);
	}

	function closeLocationModal() {
		var $modal = $('.tm-location-modal');
		if (!$modal.length) return;

		var $edit = $modal.find('.field-edit').first();
		var $origin = $edit.data('tm-origin');
		if ($origin && $origin.length) {
			$edit.hide();
			$origin.append($edit);
		}
		$modal.removeClass('is-open').attr('aria-hidden', 'true');
		$('body').removeClass('tm-modal-open');
		resumeBackgroundAfterEditing();
	}

	$(document).on('click', '.tm-location-modal__backdrop', function(e) {
		e.preventDefault();
		closeLocationModal();
	});

	// Esc key to close location modal
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			var $modal = $('.tm-location-modal.is-open');
			if ($modal.length) {
				e.preventDefault();
				closeLocationModal();
			}
		}
	});

	function resetProfileInfoEdits($profileContent) {
		if (!$profileContent || !$profileContent.length) return;
		$profileContent.find('.editing').removeClass('editing');
		$profileContent.find('.field-edit, .attribute-edit').hide();
		$profileContent.find('.field-display, .attribute-display').show();
		$profileContent.removeClass('view-mode-editing');
	}
	
	
	// Save Location
	$(document).on('click', '.location-wrapper .save-field-btn, .tm-location-modal .save-field-btn', function(e) {
		e.preventDefault();
		
		var $wrapper = $(this).closest('.location-wrapper');
		var $edit = $(this).closest('.field-edit');
		if (!$wrapper.length && $edit.length) {
			$wrapper = $edit.data('tm-origin');
		}
		var $input = $edit.length ? $edit.find('input.location-search-input') : $wrapper.find('input.location-search-input');
		var $dataInput = $edit.length ? $edit.find('input[name="location_data"]') : $wrapper.find('input[name="location_data"]');
		var newLocation = $input.length ? $input.val().trim() : '';
		var locationData = $dataInput.length ? $dataInput.val() : '';
		if (!newLocation && $edit.length) {
			var geocoderValue = $edit.find('.mapboxgl-ctrl-geocoder--input').val();
			if (geocoderValue && geocoderValue.trim()) {
				newLocation = geocoderValue.trim();
			}
		}
		var vendorId = $('.profile-img').data('vendor-id');
		var $profileHead = $wrapper.closest('.profile-info-head');
		
		if (!newLocation) {
			showNotification('Location cannot be empty', 'error');
			return;
		}
		
		// Save via AJAX
		$.ajax({
			url: vendorStoreData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_location',
				nonce: vendorStoreData.nonce,
				user_id: vendorId,
				geo_address: newLocation,
				location_data: locationData
			},
			success: function(response) {
				if (response.success) {
					// Update display
					if (response.data.geo_display) {
						$wrapper.find('.field-value').html(response.data.geo_display);
					} else {
						$wrapper.find('.field-value').text(response.data.geo_address);
					}
					syncLocationDom($wrapper, newLocation, locationData, response);
					$wrapper.find('.field-edit').hide();
					$wrapper.find('.field-display').show();
					$wrapper.removeClass('editing'); // Remove editing class
					$wrapper.closest('.profile-info-content').removeClass('view-mode-editing');
					cleanupEditingMode($wrapper);
					if ($profileHead.length) {
						$profileHead.removeClass('is-editing');
					}
						closeLocationModal();
					
					showNotification('Location updated successfully', 'success');
				} else {
					showNotification(response.data.message || 'Failed to update location', 'error');
				}
			},
			error: function() {
				showNotification('Network error', 'error');
			}
		});
	});
	
	// Cancel Location from modal
	$(document).on('click', '.tm-location-modal .cancel-field-btn', function(e) {
		e.preventDefault();
		var $edit = $(this).closest('.field-edit');
		var $wrapper = $edit.data('tm-origin');
		if ($wrapper && $wrapper.length) {
			var $input = $wrapper.find('input.location-search-input');
			$input.val($input.attr('value'));
		}
		closeLocationModal();
	});

	
	// Mapbox Location Panel (Map + Geocoder)
	var mapboxLoader = {
		loading: false,
		queue: []
	};

	function injectMapboxCss(href, key) {
		var selector = 'link[data-mapbox-css="' + key + '"]';
		if (document.querySelector(selector)) return;
		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		link.setAttribute('data-mapbox-css', key);
		document.head.appendChild(link);
	}

	function injectMapboxScript(src, onDone) {
		var selector = 'script[data-mapbox-src="' + src + '"]';
		if (document.querySelector(selector)) {
			onDone();
			return;
		}
		var script = document.createElement('script');
		script.src = src;
		script.async = true;
		script.setAttribute('data-mapbox-src', src);
		script.onload = onDone;
		script.onerror = onDone;
		document.head.appendChild(script);
	}

	function ensureMapboxAssets(readyCallback) {
		if (typeof mapboxgl !== 'undefined' && typeof MapboxGeocoder !== 'undefined') {
			readyCallback();
			return;
		}
		mapboxLoader.queue.push(readyCallback);
		if (mapboxLoader.loading) return;
		mapboxLoader.loading = true;

		var pending = 0;
		var done = function() {
			pending--;
			if (pending > 0) return;
			mapboxLoader.loading = false;
			if (typeof mapboxgl === 'undefined' || typeof MapboxGeocoder === 'undefined') {
				mapboxLoader.queue = [];
				return;
			}
			var queue = mapboxLoader.queue.slice();
			mapboxLoader.queue = [];
			queue.forEach(function(fn) {
				try {
					fn();
				} catch (e) {}
			});
		};

		injectMapboxCss('https://api.mapbox.com/mapbox-gl-js/v1.4.1/mapbox-gl.css', 'gl');
		injectMapboxCss('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.2.0/mapbox-gl-geocoder.css', 'geocoder');

		pending += 1;
		injectMapboxScript('https://api.mapbox.com/mapbox-gl-js/v1.4.1/mapbox-gl.js', function() {
			pending += 1;
			injectMapboxScript('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.2.0/mapbox-gl-geocoder.min.js', done);
			done();
		});
	}

	function initInlineLocationMap($wrapper) {
		if (!$wrapper || !$wrapper.length) return;
		var $panel = $wrapper.find('.inline-mapbox-panel').first();
		if (!$panel.length) return;
		if ($panel.data('mapbox-initialized')) return;

		var mapboxToken = '';
		if (window.vendorStoreData && window.vendorStoreData.mapbox_token) {
			mapboxToken = window.vendorStoreData.mapbox_token;
		}
		if (!mapboxToken && window.vendorStoreUiData && window.vendorStoreUiData.mapbox_token) {
			mapboxToken = window.vendorStoreUiData.mapbox_token;
		}
		if (!mapboxToken) return;

		ensureMapboxAssets(function() {
			if ($panel.data('mapbox-initialized')) return;
			if (typeof mapboxgl === 'undefined' || typeof MapboxGeocoder === 'undefined') return;

			mapboxgl.accessToken = mapboxToken;

			var $mapEl = $panel.find('.inline-mapbox-map').first();
			var $searchEl = $panel.find('.inline-mapbox-search').first();
			var $input = $wrapper.find('.location-search-input').first();
			var $dataInput = $wrapper.find('input[name="location_data"]').first();

			if (!$mapEl.length || !$searchEl.length || !$input.length || !$dataInput.length) return;

			var startLat = parseFloat($panel.data('lat'));
			var startLng = parseFloat($panel.data('lng'));
			var hasCoords = isFinite(startLat) && isFinite(startLng);

			if (!hasCoords && $dataInput.val()) {
				try {
					var parsed = JSON.parse($dataInput.val());
					if (parsed && parsed.center && parsed.center.length === 2) {
						startLng = parseFloat(parsed.center[0]);
						startLat = parseFloat(parsed.center[1]);
						hasCoords = isFinite(startLat) && isFinite(startLng);
					}
				} catch (e) {}
			}

			var map = new mapboxgl.Map({
				container: $mapEl[0],
				style: 'mapbox://styles/mapbox/dark-v11',
				center: hasCoords ? [startLng, startLat] : [0, 0],
				zoom: hasCoords ? 10 : 1,
				minZoom: 2,
				maxZoom: 14,
				pitch: 0,
				bearing: 0,
				renderWorldCopies: false
			});

			map.addControl(new mapboxgl.NavigationControl(), 'top-right');
			map.scrollZoom.disable();
			map.boxZoom.disable();
			map.dragRotate.disable();
			map.keyboard.disable();
			map.doubleClickZoom.disable();
			if (map.touchZoomRotate && map.touchZoomRotate.disableRotation) {
				map.touchZoomRotate.disableRotation();
			}
			if (map.touchPitch) {
				map.touchPitch.disable();
			}

			map.once('load', function() {
				var layers = (map.getStyle() && map.getStyle().layers) ? map.getStyle().layers.slice() : [];
				var dropPattern = /(building|poi|transit|housenumber|airport|rail|ferry|road-label)/;
				layers.forEach(function(layer) {
					if (layer && layer.id && dropPattern.test(layer.id)) {
						try { map.removeLayer(layer.id); } catch (e) {}
					}
				});
			});

			var marker = new mapboxgl.Marker({ color: '#D4AF37' });
			if (hasCoords) {
				marker.setLngLat([startLng, startLat]).addTo(map);
			}

			var geocoder = new MapboxGeocoder({
				accessToken: mapboxToken,
				types: 'country,region,place,postcode,locality,neighborhood',
				placeholder: 'Start typing location...',
				marker: false,
				mapboxgl: mapboxgl
			});

			geocoder.addTo($searchEl[0]);

			var geocoderInput = $searchEl.find('.mapboxgl-ctrl-geocoder--input');
			geocoderInput.val($input.val());

			geocoder.on('result', function(e) {
				var result = e.result;
				if (!result || !result.center || result.center.length !== 2) return;
				$input.val(result.place_name || '');
				$dataInput.val(JSON.stringify(result));
				marker.setLngLat(result.center).addTo(map);
				map.flyTo({ center: result.center, zoom: 12, essential: true });
			});

			geocoder.on('clear', function() {
				$input.val('');
				$dataInput.val('');
			});

			$input.hide();
			$panel.data('mapbox-initialized', true);

			setTimeout(function() {
				map.resize();
			}, 80);
		});
	}

	$(document).on('focus', '.location-search-input', function() {
		var $wrapper = $(this).closest('.location-wrapper');
		initInlineLocationMap($wrapper);
	});
	
	// Simple notification function
	function showNotification(message, type) {
		var bgColor = type === 'success' ? '#28a745' : '#dc3545';
		var notification = $('<div class="vendor-notification" style="position:fixed; top:20px; right:20px; background:' + bgColor + '; color:#fff; padding:15px 20px; border-radius:4px; z-index:999999; box-shadow:0 2px 8px rgba(0,0,0,0.3); font-size:14px; font-weight:500; max-width:300px; word-wrap:break-word;"></div>');
		notification.text(message);
		$('body').append(notification);
		
		setTimeout(function() {
			notification.fadeOut(function() {
				notification.remove();
			});
		}, 3000);
	}

	function showCenteredTooltip(message, autoCloseMs) {
		$('.help-tooltip').remove();
		$('.help-toggle-btn.is-tooltip-open').removeClass('is-tooltip-open');
		var $tooltip = $('<span class="help-tooltip"></span>').text(message);
		$('body').append($tooltip);
		setTimeout(function() {
			$tooltip.addClass('visible');
		}, 10);
		var closeDelay = typeof autoCloseMs === 'number' ? autoCloseMs : 3000;
		var timer = setTimeout(function() {
			$tooltip.remove();
		}, closeDelay);
		$tooltip.on('click', function() {
			clearTimeout(timer);
			$tooltip.remove();
		});
	}

});
