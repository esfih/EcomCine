/**
 * Vendor Store Pages - Non-Player UI
 * Handles: Biography lightbox, profile editing, store page controls, social polling, location map.
 * Media playback is handled by the tm-media-player plugin (player.js).
 */

jQuery(document).ready(function($) {

	// ==========================================
	// NON-PLAYER STATE & HELPERS
	// ==========================================

	// ── TM Icons ─────────────────────────────────────────────────────────────────
	// Inline SVG icon helper — zero font/CSS dependency.
	// Paths from Font Awesome 6 Free (MIT / CC BY 4.0).
	var _TM_ICONS = {
		'circle-question': { vb: '0 0 512 512', p: '<path d=\"M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3l58.3 0c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24l0-13.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1l-58.3 0c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z\"/>' },
		'arrow-up-right-from-square': { vb: '0 0 512 512', p: '<path d=\"M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l82.7 0L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3l0 82.7c0 17.7 14.3 32 32 32s32-14.3 32-32l0-160c0-17.7-14.3-32-32-32L320 0zM80 32C35.8 32 0 67.8 0 112L0 432c0 44.2 35.8 80 80 80l320 0c44.2 0 80-35.8 80-80l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 112c0 8.8-7.2 16-16 16L80 448c-8.8 0-16-7.2-16-16l0-320c0-8.8 7.2-16 16-16l112 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L80 32z\"/>' },
		'users': { vb: '0 0 640 512', p: '<path d=\"M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192l42.7 0c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0L21.3 320C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7l42.7 0C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3l-213.3 0zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352l117.3 0C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7l-330.7 0c-14.7 0-26.7-11.9-26.7-26.7z\"/>' },
		'chart-bar': { vb: '0 0 512 512', p: '<path d=\"M32 32c17.7 0 32 14.3 32 32l0 336c0 8.8 7.2 16 16 16l400 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L80 480c-44.2 0-80-35.8-80-80L0 64C0 46.3 14.3 32 32 32zm96 96c0-17.7 14.3-32 32-32l192 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-192 0c-17.7 0-32-14.3-32-32zm32 64l128 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-128 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 96l256 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z\"/>' },
		'heart': { vb: '0 0 512 512', p: '<path d=\"M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5z\"/>' },
		'comment-dots': { vb: '0 0 512 512', p: '<path d=\"M256 448c141.4 0 256-93.1 256-208S397.4 32 256 32S0 125.1 0 240c0 45.1 17.7 86.8 47.7 120.9c-1.9 24.5-11.4 46.3-21.4 62.9c-5.5 9.2-11.1 16.6-15.2 21.6c-2.1 2.5-3.7 4.4-4.9 5.7c-.6 .6-1 1.1-1.3 1.4l-.3 .3c0 0 0 0 0 0c0 0 0 0 0 0s0 0 0 0s0 0 0 0c-4.6 4.6-5.9 11.4-3.4 17.4c2.5 6 8.3 9.9 14.8 9.9c28.7 0 57.6-8.9 81.6-19.3c22.9-10 42.4-21.9 54.3-30.6c31.8 11.5 67 17.9 104.1 17.9zM128 208a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm128 0a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm96 32a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z\"/>' },
		'eye': { vb: '0 0 576 512', p: '<path d=\"M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z\"/>' },
		'thumbs-up': { vb: '0 0 512 512', p: '<path d=\"M313.4 32.9c26 5.2 42.9 30.5 37.7 56.5l-2.3 11.4c-5.3 26.7-15.1 52.1-28.8 75.2l144 0c26.5 0 48 21.5 48 48c0 18.5-10.5 34.6-25.9 42.6C497 275.4 504 288.9 504 304c0 23.4-16.8 42.9-38.9 47.1c4.4 7.3 6.9 15.8 6.9 24.9c0 21.3-13.9 39.4-33.1 45.6c.7 3.3 1.1 6.8 1.1 10.4c0 26.5-21.5 48-48 48l-97.5 0c-19 0-37.5-5.6-53.3-16.1l-38.5-25.7C176 420.4 160 390.4 160 358.3l0-38.3 0-48 0-24.9c0-29.2 13.3-56.7 36-75l7.4-5.9c26.5-21.2 44.6-51 51.2-84.2l2.3-11.4c5.2-26 30.5-42.9 56.5-37.7zM32 192l64 0c17.7 0 32 14.3 32 32l0 224c0 17.7-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32L0 224c0-17.7 14.3-32 32-32z\"/>' },
		'user-group': { vb: '0 0 640 512', p: '<path d=\"M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304l91.4 0C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7L29.7 512C13.3 512 0 498.7 0 482.3zM609.3 512l-137.8 0c5.4-9.4 8.6-20.3 8.6-32l0-8c0-60.7-27.1-115.2-69.8-151.8c2.4-.1 4.7-.2 7.1-.2l61.4 0C567.8 320 640 392.2 640 481.3c0 17-13.8 30.7-30.7 30.7zM432 256c-31 0-59-12.6-79.3-32.9C372.4 196.5 384 163.6 384 128c0-26.8-6.6-52.1-18.3-74.3C384.3 40.1 407.2 32 432 32c61.9 0 112 50.1 112 112s-50.1 112-112 112z\"/>' }
	};
	var _TM_ICON_ALIASES = {
		'fa-question-circle':    'circle-question',
		'fa-external-link-alt':  'arrow-up-right-from-square',
		'fa-users':              'users',
		'fa-chart-bar':          'chart-bar',
		'fa-heart':              'heart',
		'fa-comment-dots':       'comment-dots',
		'fa-eye':                'eye',
		'fa-thumbs-up':          'thumbs-up',
		'fa-user-friends':       'user-group'
	};
	function tmIcon( name, extraClass ) {
		var c = _TM_ICON_ALIASES[ name ] || name;
		var d = _TM_ICONS[ c ];
		if ( ! d ) { return ''; }
		var cls = 'tm-icon tm-icon-' + c + ( extraClass ? ' ' + extraClass : '' );
		return '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"' + d.vb + '\" class=\"' + cls + '\" style=\"fill:currentColor\" aria-hidden=\"true\" focusable=\"false\">' + d.p + '</svg>';
	}
	// ── end TM Icons ─────────────────────────────────────────────────────────────

	// Only the keys this file actually reads/writes. Player-managed keys
	// (muted, fullDuration, loopMode, vendorLoop) live exclusively in player.js.
	var STORAGE_KEYS = {
		collapsed: "tm_profile_collapsed"
	};

	function publicPersonSingularLabel() {
		return (window.vendorStoreUiData && vendorStoreUiData.personLabelSingular)
			? vendorStoreUiData.personLabelSingular
			: 'Talent';
	}

	function publicPersonTermsLabelLower() {
		var label = (window.vendorStoreUiData && vendorStoreUiData.personTermsLabel)
			? vendorStoreUiData.personTermsLabel
			: 'Talent Terms';
		return String(label).toLowerCase();
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

	function canEditProfile() {
		if (!window.vendorStoreUiData) return false;
		if (typeof window.vendorStoreUiData.canEdit !== "undefined") {
			return !!window.vendorStoreUiData.canEdit;
		}
		return !!window.vendorStoreUiData.isOwner;
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
			console.log('ðŸ§¹ Removed head-editing from profile-info-head');
		} else {
			console.log('âš ï¸ No profile-info-head found for cleanup');
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
		var $helpIcon = $('<span class="help-icon-wrapper modal-help-wrapper" style="position: relative; display: inline-block; margin-left: 8px;"><button type="button" class="help-toggle-btn" aria-label="Show help" style="font-size: 16px; color: #888; cursor: pointer; background: none; border: none; padding: 0;" data-help-text="' + helpText + '">' + tmIcon('fa-question-circle') + '</button></span>');
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

		$modal.removeClass('is-open is-location-editor is-onboard-modal tm-location-manual-mode').attr('aria-hidden', 'true');
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
		if (!window.vendorStoreUiData || !vendorStoreUiData.onboardNonce) {
			showNotification('Unable to create onboarding link.', 'error');
			return;
		}
		var vendorId = $('.tm-onboard-share-btn').data('vendorId') || vendorStoreUiData.userId || 0;
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
			url: vendorStoreUiData.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'tm_onboard_share_link',
				nonce: vendorStoreUiData.onboardNonce,
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
			showCenteredTooltip('Please enter your email and password, then accept the privacy policy and ' + publicPersonTermsLabelLower() + ' to claim your profile.', 3000);
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
		var lat = $mapboxPanel.length ? ($mapboxPanel.data('lat') || '') : '';
		var lng = $mapboxPanel.length ? ($mapboxPanel.data('lng') || '') : '';

		$body.empty();
		
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

		if (!$body.find('.inline-mapbox-panel .inline-mapbox-map').length) {
			$body.append('<div class="inline-mapbox-panel"><div class="inline-mapbox-search"></div><div class="inline-mapbox-map"></div></div>');
		}
		
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
		if (!window.vendorStoreUiData) {
			readyCallback();
			return;
		}
		datepickerLoader.queue.push(readyCallback);
		if (datepickerLoader.loading) return;
		datepickerLoader.loading = true;

		var cssUrl = vendorStoreUiData.jqueryUiCssUrl || '/wp-content/plugins/woocommerce-bookings/dist/jquery-ui-styles.css';
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

		injectDatepickerScript(vendorStoreUiData.jqueryUiCoreUrl, done);
		injectDatepickerScript(vendorStoreUiData.jqueryUiWidgetUrl, done);
		injectDatepickerScript(vendorStoreUiData.jqueryUiDatepickerUrl, done);
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
		} else if (inputType === 'textarea') {
			var $textarea = $('<textarea class="edit-field-input" data-field="' + fieldName + '" rows="6"></textarea>');
			$textarea.val(currentValue);
			$body.append($textarea);
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
	function syncViewportMetrics() {
		var visualViewport = window.visualViewport;
		var viewportHeight = visualViewport ? visualViewport.height : window.innerHeight;
		var viewportWidth = visualViewport ? visualViewport.width : window.innerWidth;
		var browserOffset = Math.max(0, window.innerHeight - viewportHeight);

		document.documentElement.style.setProperty('--tm-visible-viewport-height', viewportHeight + 'px');
		document.documentElement.style.setProperty('--tm-visible-viewport-width', viewportWidth + 'px');
		document.documentElement.style.setProperty('--tm-browser-ui-offset', browserOffset + 'px');
	}


	// ==========================================
	// PLAYER BRIDGE - delegates to tm-media-player plugin (player.js)
	// suspendBackgroundForEditing / resumeBackgroundAfterEditing are exposed
	// globally by player.js via window.tmSuspendBackgroundForEditing etc.
	// ==========================================

	function isShowcaseMode() {
		// Use the dedicated global set by the plugin (immune to vendorStoreData overwrites).
		// Falls back to vendorStoreUiData only as a legacy safety net.
		if (window.tmPlayerMode) return window.tmPlayerMode === "showcase";
		return !!(window.vendorStoreUiData && vendorStoreUiData.playerMode === "showcase");
	}

	function suspendBackgroundForEditing() {
		if (window.tmSuspendBackgroundForEditing) window.tmSuspendBackgroundForEditing();
	}
	function resumeBackgroundAfterEditing() {
		if (window.tmResumeBackgroundAfterEditing) window.tmResumeBackgroundAfterEditing();
	}
	// Stubs for player-managed UI state functions called by initStorePageControls
	function updateTalentPanelOpenState() { /* player.js manages this */ }
	function bindFullscreenListeners()   { /* player.js manages this */ }
	function updateFullscreenButton()    { /* player.js manages this */ }
	function updateTheatreButton()       { /* player.js manages this */ }
	function updateVendorLoopButton()    { /* player.js manages this */ }

	// ==========================================
	// INITIALIZATION & EVENT SETUP
	// ==========================================

	function initStorePageControls() {
			var collapseTimer = null;
			function clearCollapseTimer() {
				if (collapseTimer) {
					clearTimeout(collapseTimer);
					collapseTimer = null;
				}
			}

			var infoPanelOpen = false;
			var drawerPanelOpen = false;
			var interactionPaused = false;
			function pauseShowcaseRotation() {
				if (!isShowcaseMode()) return;
				if (window.tmPauseShowcaseRotation) {
					window.tmPauseShowcaseRotation();
				}
			}
			function updateInteractionPause() {
				// In profile / direct-navigation mode the player loops only this talent's media
				// and never auto-swaps to another talent, so there is no risk of the drawer data
				// changing mid-read. Pause/resume is only needed in showcase mode where the
				// player would otherwise swap the talent while the user is reading the tab.
				if (!isShowcaseMode()) return;
				var shouldPause = infoPanelOpen || drawerPanelOpen;
				if (shouldPause && !interactionPaused) {
					pauseShowcaseRotation();
					interactionPaused = true;
					return;
				}
				if (!shouldPause && interactionPaused) {
					interactionPaused = false;
				}
			}

			function closeTalentPanel() {
				var $panel = $(".profile-info-head");
				if (!$panel.length || $panel.hasClass("is-collapsed")) {
					return false;
				}
				clearCollapseTimer();
				$panel.addClass("is-collapsed");
				writeStoredBool(STORAGE_KEYS.collapsed, true);
				infoPanelOpen = false;
				updateInteractionPause();
				updateMobileLandscapePanel();
				updateMobilePortraitHeader();
				return true;
			}

			function closeDrawerPanel() {
				if (!$(".attribute-slide-section.slide-up").length && !$(".bottom-tab-item.active-panel").length) {
					return false;
				}
				$(".attribute-slide-section").removeClass("slide-up");
				$(".bottom-tab-item").removeClass("active-panel");
				currentPanel = null;
				drawerPanelOpen = false;
				updateInteractionPause();
				updateMobileDrawerFocusState();
				return true;
			}

			function isDismissExemptTarget($target) {
				return $target.closest("a, button, input, select, textarea, label, summary, [role='button'], .attribute-edit, .help-icon-wrapper, .editable-field, .field-edit, .store-categories-wrapper, .tm-field-editor-modal, .tm-field-editor-backdrop, .tm-location-modal__dialog, .tm-location-modal__backdrop, .tm-account-modal, .help-tooltip, .mapboxgl-map, .mapboxgl-canvas-container, .mapboxgl-ctrl, .vendor-cta-btn, .contact-channel-icon, .inline-mapbox-panel").length > 0;
			}

		// Profile panel collapse/expand on click
		$(".profile-info-head, .collapsed-tab-label").on("click", function(e) {
			if ($(".profile-info-head").hasClass("is-editing")) {
				return;
			}
			if ($(e.target).closest("a, button, input, select, textarea, .attribute-edit, .help-icon-wrapper, .editable-field, .field-edit, .store-categories-wrapper").length) {
				return;
			}
				clearCollapseTimer();
			var wasCollapsed = $(".profile-info-head").hasClass("is-collapsed");
			$(".profile-info-head").toggleClass("is-collapsed");
			writeStoredBool(STORAGE_KEYS.collapsed, $(".profile-info-head").hasClass("is-collapsed"));
			updateMobileLandscapePanel();
			var isCollapsed = $(".profile-info-head").hasClass("is-collapsed");
			if (wasCollapsed && !isCollapsed) {
				infoPanelOpen = true;
				updateInteractionPause();
			} else if (!wasCollapsed && isCollapsed) {
				infoPanelOpen = false;
				updateInteractionPause();
			}
			e.stopPropagation();
		});

		// Apply persisted collapsed state
			var isMobile = isHandheldViewport();
			var storedCollapsed = null;
			try {
				storedCollapsed = window.localStorage ? localStorage.getItem(STORAGE_KEYS.collapsed) : null;
			} catch (e) {
				storedCollapsed = null;
			}
			var isCollapsed = isMobile ? true : (storedCollapsed === null ? false : storedCollapsed === "true");
			if (!isMobile) {
				isCollapsed = true;
				clearCollapseTimer();
				collapseTimer = setTimeout(function() {
					$(".profile-info-head").removeClass("is-collapsed");
					updateTalentPanelOpenState();
					collapseTimer = null;
				}, 3000);
			}
		$(".profile-info-head").toggleClass("is-collapsed", isCollapsed);

			if (isMobile) {
				writeStoredBool(STORAGE_KEYS.collapsed, true);
			}

		// Bottom tabs click-based slide-up interaction
		var currentPanel = null;

		function setDrawerTabHeight() {
			var maxHeight = 0;
			$(".bottom-tab-label").each(function() {
				maxHeight = Math.max(maxHeight, $(this).outerHeight(true));
			});
			if (maxHeight > 0) {
				$(".profile-bottom-drawer").css("--tab-height", maxHeight + "px");
				$(".profile-frame").css("--tab-height", maxHeight + "px");
				$(".keyboard-nav-container").css("--tab-height", maxHeight + "px");
				$(".hero-global-btn").css("--tab-height", maxHeight + "px");
			}
		}

		function getBreakpointValue(varName, fallback) {
			var rawValue = getComputedStyle(document.documentElement).getPropertyValue(varName);
			var parsed = parseInt(rawValue, 10);
			return Number.isFinite(parsed) ? parsed : fallback;
		}

		function updateBottomTabCompact() {
			var maxCompactWidth = getBreakpointValue("--bp-phone-landscape-max", 932);
			var isCompact = window.innerWidth <= maxCompactWidth;
			$(".profile-bottom-drawer").toggleClass("is-compact-tabs", isCompact);
			$(document.body).toggleClass("tm-compact-tabs", isCompact);
		}

		function updateMobileLandscapePanel() {
			var maxLandscapeWidth = getBreakpointValue("--bp-phone-landscape-max", 932);
			var isLandscape = window.matchMedia ? window.matchMedia("(orientation: landscape)").matches : window.innerWidth > window.innerHeight;
			var isMobileLandscape = window.innerWidth <= maxLandscapeWidth && isLandscape;
			$(document.body).toggleClass("tm-mobile-landscape", isMobileLandscape);
			updateMobileTalentPanelState(isMobileLandscape, $(document.body).hasClass("tm-mobile-portrait"));
		}

		function updateMobilePortraitHeader() {
			var maxPortraitWidth = getBreakpointValue("--bp-mobile", 600);
			var isPortrait = window.matchMedia ? window.matchMedia("(orientation: portrait)").matches : window.innerHeight >= window.innerWidth;
			var isMobilePortrait = window.innerWidth <= maxPortraitWidth && isPortrait;
			$(document.body).toggleClass("tm-mobile-portrait", isMobilePortrait);
			updateMobileTalentPanelState($(document.body).hasClass("tm-mobile-landscape"), isMobilePortrait);
		}

		function updateMobileTalentPanelState(isMobileLandscape, isMobilePortrait) {
			var isExpanded = !$(".profile-info-head").hasClass("is-collapsed");
			$(document.body).toggleClass("tm-talent-panel-open", Boolean((isMobileLandscape || isMobilePortrait) && isExpanded));
			updateMobileDrawerFocusState();
		}

		function updateMobileDrawerFocusState() {
			var isMobile = $(document.body).hasClass("tm-mobile-landscape") || $(document.body).hasClass("tm-mobile-portrait");
			var drawerOpen = isMobile && $(".attribute-slide-section.slide-up").length > 0;
			$(document.body).toggleClass("tm-mobile-drawer-open", drawerOpen);
		}

		setDrawerTabHeight();
		syncViewportMetrics();
		updateBottomTabCompact();
		updateMobileLandscapePanel();
		updateMobilePortraitHeader();
		updateMobileDrawerFocusState();
		$(window).on("resize", function() {
			setDrawerTabHeight();
			syncViewportMetrics();
			updateBottomTabCompact();
			updateMobileLandscapePanel();
			updateMobilePortraitHeader();
			updateMobileDrawerFocusState();
		});
		$(window).on("orientationchange", function() {
			syncViewportMetrics();
			setDrawerTabHeight();
			updateBottomTabCompact();
			updateMobileLandscapePanel();
			updateMobilePortraitHeader();
			updateMobileDrawerFocusState();
		});
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', syncViewportMetrics);
			window.visualViewport.addEventListener('scroll', syncViewportMetrics);
		}
		bindFullscreenListeners();
		updateFullscreenButton();
		updateTheatreButton();

		// Bottom tabs click handler - toggle open/closed
		// Sole authority: vendor-store.js (theme). player.js has no tab handlers.
		// Delegated via $(document) so it survives talent DOM replacement during
		// showcase rotation. Namespace prevents accumulation on re-init.
		$(document).off("click.tm-bottom-tab").on("click.tm-bottom-tab", ".bottom-tab-item", function(e) {
			e.preventDefault();
			pauseShowcaseRotation();
			
			var $clickedTab = $(this);
			var targetId = $clickedTab.data("target");
			var targetSection = $("#" + targetId);

			if (!targetSection.length) return;

			var isCurrentlyActive = $clickedTab.hasClass("active-panel");

			// Close all panels and remove active states
			$(".attribute-slide-section").removeClass("slide-up");
			$(".bottom-tab-item").removeClass("active-panel");

			// If clicking the same tab, just close it (toggle off)
			if (isCurrentlyActive) {
				currentPanel = null;
				drawerPanelOpen = false;
				updateInteractionPause();
				updateMobileDrawerFocusState();
				return;
			}

			// Otherwise, open the clicked tab
			targetSection.addClass("slide-up");
			$clickedTab.addClass("active-panel");
			currentPanel = targetSection;
			drawerPanelOpen = true;
			updateInteractionPause();
			updateMobileDrawerFocusState();
		});

		$(document).off("click.tm-bottom-panel-inside").on("click.tm-bottom-panel-inside", ".attribute-slide-section.slide-up", function(e) {
			var $target = $(e.target);
			if (isDismissExemptTarget($target)) {
				return;
			}
			if (closeDrawerPanel()) {
				e.preventDefault();
				e.stopPropagation();
			}
		});

		// Close panel when clicking outside (optional enhancement)
		$(document).on("click", function(e) {
			var $target = $(e.target);
			// Don't close if clicking inside a panel or on a tab
			if ($target.closest(".tm-field-editor-modal, .tm-field-editor-backdrop, .tm-location-modal__dialog, .tm-location-modal__backdrop").length) {
				return;
			}
			if ($target.closest(".attribute-slide-section, .bottom-tab-item").length === 0) {
				closeDrawerPanel();
			}
			if ($target.closest(".profile-info-head, .collapsed-tab-label").length === 0 && !isDismissExemptTarget($target)) {
				closeTalentPanel();
			}
		});

		initBiographyLightbox();
	}
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
			url: vendorStoreUiData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_location',
				nonce: vendorStoreUiData.nonce,
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

		var vendorId = (window.vendorStoreUiData && window.vendorStoreUiData.userId)
			? window.vendorStoreUiData.userId
			: parseInt($('.profile-info-box').data('vendorId'), 10) || 0;

		$.ajax({
			url: vendorStoreUiData.ajaxurl,
			method: 'POST',
			data: {
				action: 'vendor_save_attribute',
				nonce: vendorStoreUiData.editNonce,
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
			error: function() {
				showNotification('Connection error', 'error');
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
				nonce: (window.vendorStoreUiData && window.vendorStoreUiData.editNonce) ? window.vendorStoreUiData.editNonce : '',
				user_id: vendorId,
				field: 'store_categories',
				value: fieldValue
			};
		} else if (fieldName === 'store_name') {
			ajaxData = {
				action: 'vendor_update_store_name',
				nonce: (window.vendorStoreUiData && window.vendorStoreUiData.nonce) ? window.vendorStoreUiData.nonce : '',
				user_id: vendorId,
				store_name: fieldValue
			};
		} else {
			ajaxData = {
				action: 'vendor_save_attribute',
				nonce: (window.vendorStoreUiData && window.vendorStoreUiData.editNonce) ? window.vendorStoreUiData.editNonce : '',
				user_id: vendorId,
				field: fieldName,
				value: fieldValue
			};
		}
		
		$.ajax({
			url: (window.vendorStoreUiData && window.vendorStoreUiData.ajaxurl) ? window.vendorStoreUiData.ajaxurl : '/wp-admin/admin-ajax.php',
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
		var config = getSocialMetricConfig(platform);
		if (config.label) {
			return config.label;
		}

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

	function getSocialMetricConfig(platform) {
		var config = (window.vendorStoreUiData && window.vendorStoreUiData.socialMetricConfig)
			? window.vendorStoreUiData.socialMetricConfig
			: {};
		return (platform && Object.prototype.hasOwnProperty.call(config, platform) && config[platform])
			? config[platform]
			: {};
	}

	function getSocialMetricLabel(platform, key, fallback) {
		var config = getSocialMetricConfig(platform);
		if (config && config[key]) {
			return config[key];
		}
		return fallback;
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
			+ tmIcon( iconClass, 'stat-icon' ) + ' '
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
				$link = $('<a target="_blank" class="social-profile-link">' + tmIcon('fa-external-link-alt', 'social-profile-link-icon') + ' View Profile</a>');
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
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Subscribers'), '...', '#D4AF37');
					html += buildStatItem('fa-chart-bar', getSocialMetricLabel(platform, 'views_label', 'Avg Views'), '...', '#FFD1BF');
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), '...', '#FFD1BF');
					break;
				case 'instagram':
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), '...', '#D4AF37');
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), '...', '#FFD1BF');
					html += buildStatItem('fa-comment-dots', getSocialMetricLabel(platform, 'comments_label', 'Avg Comments'), '...', '#FFD1BF');
					break;
				case 'facebook':
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), '...', '#D4AF37');
					html += buildStatItem('fa-eye', getSocialMetricLabel(platform, 'views_label', 'Avg Views'), '...', '#FFD1BF');
					html += buildStatItem('fa-thumbs-up', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), '...', '#FFD1BF');
					break;
				case 'linkedin':
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), '...', '#D4AF37');
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), '...', '#FFD1BF');
					html += buildStatItem('fa-user-friends', getSocialMetricLabel(platform, 'connections_label', 'Connections'), '...', '#FFD1BF');
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
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), 'N/A', '#D4AF37');
					html += buildStatItem('fa-eye', getSocialMetricLabel(platform, 'views_label', 'Avg Views'), 'N/A', '#FFD1BF');
					html += buildStatItem('fa-thumbs-up', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), 'N/A', '#FFD1BF');
					break;
			}
			$stats.html(html);
			return;
		}

		switch (platform) {
			case 'youtube':
				if (metrics.subscribers !== null && typeof metrics.subscribers !== 'undefined') {
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Subscribers'), formatSocialNumber(metrics.subscribers), '#D4AF37');
				}
				if (metrics.avg_views !== null && typeof metrics.avg_views !== 'undefined') {
					html += buildStatItem('fa-chart-bar', getSocialMetricLabel(platform, 'views_label', 'Avg Views'), formatSocialNumber(metrics.avg_views), '#FFD1BF');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				break;
			case 'instagram':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				if (metrics.avg_comments !== null && typeof metrics.avg_comments !== 'undefined') {
					html += buildStatItem('fa-comment-dots', getSocialMetricLabel(platform, 'comments_label', 'Avg Comments'), formatSocialNumber(metrics.avg_comments), '#FFD1BF');
				}
				break;
			case 'facebook':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_views !== null && typeof metrics.avg_views !== 'undefined') {
					html += buildStatItem('fa-eye', getSocialMetricLabel(platform, 'views_label', 'Avg Views'), formatSocialNumber(metrics.avg_views), '#FFD1BF');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-thumbs-up', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				break;
			case 'linkedin':
				if (metrics.followers !== null && typeof metrics.followers !== 'undefined') {
					html += buildStatItem('fa-users', getSocialMetricLabel(platform, 'followers_label', 'Followers'), formatSocialNumber(metrics.followers), '#D4AF37');
				}
				if (metrics.avg_reactions !== null && typeof metrics.avg_reactions !== 'undefined') {
					html += buildStatItem('fa-heart', getSocialMetricLabel(platform, 'reactions_label', 'Avg Reactions'), formatSocialNumber(metrics.avg_reactions), '#FFD1BF');
				}
				if (metrics.connections !== null && typeof metrics.connections !== 'undefined') {
					html += buildStatItem('fa-user-friends', getSocialMetricLabel(platform, 'connections_label', 'Connections'), formatSocialNumber(metrics.connections), '#FFD1BF');
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
		if (!window.vendorStoreUiData || !vendorStoreUiData.ajaxurl) return $.Deferred().reject().promise();
		return $.ajax({
			url: vendorStoreUiData.ajaxurl,
			type: 'POST',
			data: {
				action: 'tm_social_metrics_status',
				nonce: vendorStoreUiData.editNonce,
				vendor_id: vendorStoreUiData.userId,
				platform: platform
			}
		});
	}

	window.tmSocialDebugDump = function(platform) {
		if (!platform) return;
		if (!window.vendorStoreUiData || !vendorStoreUiData.ajaxurl) return;
		$.ajax({
			url: vendorStoreUiData.ajaxurl,
			type: 'POST',
			data: {
				action: 'tm_social_debug_dump',
				nonce: vendorStoreUiData.editNonce,
				vendor_id: vendorStoreUiData.userId,
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
			nonce: window.vendorStoreUiData ? window.vendorStoreUiData.editNonce : '',
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
			url: (window.vendorStoreUiData && window.vendorStoreUiData.ajax_url) ? window.vendorStoreUiData.ajax_url : '/wp-admin/admin-ajax.php',
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
			url: vendorStoreUiData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_contact_info',
				nonce: vendorStoreUiData.nonce,
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
			url: vendorStoreUiData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_contact_info',
				nonce: vendorStoreUiData.nonce,
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
			url: vendorStoreUiData.ajax_url,
			type: 'POST',
			data: {
				action: 'vendor_update_location',
				nonce: vendorStoreUiData.nonce,
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
		if (typeof readyCallback !== 'function') {
			return;
		}
		if (typeof mapboxgl !== 'undefined' && typeof MapboxGeocoder !== 'undefined') {
			readyCallback(true);
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
			var assetsReady = (typeof mapboxgl !== 'undefined' && typeof MapboxGeocoder !== 'undefined');
			var queue = mapboxLoader.queue.slice();
			mapboxLoader.queue = [];
			queue.forEach(function(fn) {
				try {
					fn(assetsReady);
				} catch (e) {}
			});
		};

		injectMapboxCss('https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', 'gl');
		injectMapboxCss('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.2/mapbox-gl-geocoder.css', 'geocoder');

		pending += 1;
		injectMapboxScript('https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', function() {
			pending += 1;
			injectMapboxScript('https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.2/mapbox-gl-geocoder.min.js', done);
			done();
		});
	}

	function enableLocationManualMode($wrapper, message) {
		var $modal = $('.tm-field-editor-modal');
		var $panel = $wrapper.find('.inline-mapbox-panel').first();
		var $input = $wrapper.find('.location-search-input').first();
		var $note = $wrapper.find('.tm-location-fallback-note').first();

		$modal.addClass('tm-location-manual-mode');
		if ($panel.length) {
			$panel.hide();
		}
		if ($input.length) {
			$input.show();
		}
		if (!$note.length) {
			$note = $('<div class="tm-location-fallback-note" aria-live="polite"></div>');
			$wrapper.append($note);
		}
		$note.text(message || 'Map is unavailable right now. You can still type an address and save.').show();
	}

	function initInlineLocationMap($wrapper) {
		if (!$wrapper || !$wrapper.length) return;
		var $modal = $('.tm-field-editor-modal');
		var $panel = $wrapper.find('.inline-mapbox-panel').first();
		if (!$panel.length) return;
		if ($panel.data('mapbox-initialized')) return;

		var mapboxToken = '';
		if (window.vendorStoreUiData && window.vendorStoreUiData.mapbox_token) {
			mapboxToken = window.vendorStoreUiData.mapbox_token;
		}
		if (!mapboxToken && window.vendorStoreData && window.vendorStoreData.mapbox_token) {
			mapboxToken = window.vendorStoreData.mapbox_token;
		}
		if (!mapboxToken) {
			enableLocationManualMode($wrapper, 'Mapbox token is missing. Type your location and save.');
			return;
		}

		ensureMapboxAssets(function(assetsReady) {
			if (!assetsReady) {
				enableLocationManualMode($wrapper, 'Mapbox assets failed to load. Type your location and save.');
				return;
			}
			if ($panel.data('mapbox-initialized')) return;
			if (typeof mapboxgl === 'undefined' || typeof MapboxGeocoder === 'undefined') return;

			try {

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

			$wrapper.find('.tm-location-fallback-note').hide();
			$modal.removeClass('tm-location-manual-mode');
			$panel.show();
			$input.hide();
			$panel.data('mapbox-initialized', true);

			setTimeout(function() {
				map.resize();
			}, 80);
			} catch (err) {
				enableLocationManualMode($wrapper, 'Mapbox UI failed to initialize. Type your location and save.');
				if (window.console && typeof window.console.error === 'function') {
					window.console.error('[tm-store-ui] Mapbox modal init failed', err);
				}
			}
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


	// Initialize non-player store page controls on DOM ready
	$(function() {
		initStorePageControls();
	});

	// ══════════════════════════════════════════════════════════════════
	// HEADER MENU — Sign in / Sign up → open account modal
	// ══════════════════════════════════════════════════════════════════

	$(document).on('click', '.tm-open-signin, .tm-open-signup', function (e) {
		e.preventDefault();
		var isSignup = $(this).hasClass('tm-open-signup');
		// Trigger the existing side account-tab to open the modal.
		$('.tm-account-tab:not(.tm-account-tab--admin)').first().trigger('click');
		if (isSignup) {
			// After modal opens, switch to the Register tab.
			setTimeout(function () {
				$('#tm-account-modal [data-tab="register"]').trigger('click');
			}, 80);
		}
	});

	$(document).on('click', '.tm-open-account-seller', function (e) {
		e.preventDefault();
		$('.tm-account-tab:not(.tm-account-tab--admin)').first().trigger('click');
	});

});
