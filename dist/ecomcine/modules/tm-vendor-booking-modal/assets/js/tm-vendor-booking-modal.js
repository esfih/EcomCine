(function($) {
	if (typeof tmVendorBookingModal === 'undefined') {
		return;
	}

	var state = {
		isOpen: false,
		formLoaded: false,
		checkoutLoaded: false,
		bookingInit: false,
		checkoutInit: false,
		loadingForm: false,
		loadingCheckout: false
	};

	var assetState = {
		loading: false,
		loaded: false,
		callbacks: []
	};

	function loadStyleOnce(asset) {
		if (!asset || !asset.href || !asset.handle) {
			return;
		}

		var id = 'tm-asset-style-' + asset.handle;
		if (document.getElementById(id)) {
			return;
		}

		var link = document.createElement('link');
		link.id = id;
		link.rel = 'stylesheet';
		link.href = asset.href;
		link.setAttribute('data-tm-asset', asset.handle);
		document.head.appendChild(link);
	}

	function loadInlineOnce(handle, code) {
		if (!handle || !code) {
			return;
		}

		var id = 'tm-asset-inline-' + handle;
		if (document.getElementById(id)) {
			return;
		}

		var script = document.createElement('script');
		script.id = id;
		script.setAttribute('data-tm-asset', handle);
		script.text = code;
		document.head.appendChild(script);
	}

	function loadScriptOnce(asset, done) {
		if (!asset || !asset.src || !asset.handle) {
			done();
			return;
		}

		var id = 'tm-asset-script-' + asset.handle;
		if (document.getElementById(id)) {
			done();
			return;
		}

		if (asset.inline) {
			loadInlineOnce(asset.handle, asset.inline);
		}

		var script = document.createElement('script');
		script.id = id;
		script.src = asset.src + (asset.src.indexOf('?') === -1 ? '?' : '&') + 'tm_modal=1';
		script.onload = function() {
			done();
		};
		script.onerror = function() {
			done();
		};
		document.body.appendChild(script);
	}

	function ensureModalAssets(callback) {
		if (!tmVendorBookingModal || !tmVendorBookingModal.assets) {
			callback();
			return;
		}

		if (assetState.loaded) {
			callback();
			return;
		}

		assetState.callbacks.push(callback);
		if (assetState.loading) {
			return;
		}

		assetState.loading = true;

		var styles = tmVendorBookingModal.assets.styles || [];
		var scripts = tmVendorBookingModal.assets.scripts || [];

		for (var i = 0; i < styles.length; i += 1) {
			loadStyleOnce(styles[i]);
		}

		var index = 0;
		function next() {
			if (index >= scripts.length) {
				assetState.loaded = true;
				assetState.loading = false;
				while (assetState.callbacks.length) {
					assetState.callbacks.shift()();
				}
				return;
			}
			loadScriptOnce(scripts[index], function() {
				index += 1;
				next();
			});
		}
		next();
	}

	function getModal() {
		return $('.tm-booking-modal');
	}

	function setBodyLocked(locked) {
		$('body').toggleClass('tm-booking-modal-open', locked);
		$('body').toggleClass('tm-modal-open', locked);
	}

	function openModal() {
		var $modal = getModal();
		$modal.addClass('is-open').attr('aria-hidden', 'false');
		state.isOpen = true;
		setBodyLocked(true);
		if (window.tmSuspendBackgroundForEditing) {
			window.tmSuspendBackgroundForEditing();
		}
	}

	function closeModal() {
		var $modal = getModal();
		$modal.removeClass('is-open').attr('aria-hidden', 'true');
		state.isOpen = false;
		setBodyLocked(false);
		if (window.tmResumeBackgroundAfterEditing) {
			window.tmResumeBackgroundAfterEditing();
		}
	}

	function getBookingPanel() {
		return getModal().find('.tm-booking-modal__panel--booking');
	}

	function getCheckoutPanel() {
		return getModal().find('.tm-booking-modal__panel--checkout');
	}

	function showPanelMessage($panel, message) {
		$panel.html('<div class="tm-booking-modal__message">' + message + '</div>');
	}

	function showLoading($panel, message) {
		$panel.html('<div class="tm-booking-modal__loading">' + message + '</div>');
	}

	function reloadScript(src, callback) {
		if (!src) {
			if (typeof callback === 'function') {
				callback();
			}
			return;
		}

		var script = document.createElement('script');
		var separator = src.indexOf('?') === -1 ? '?' : '&';
		script.src = src + separator + 'tm_reload=' + Date.now();
		script.onload = function() {
			if (typeof callback === 'function') {
				callback();
			}
		};
		document.body.appendChild(script);
	}

	function initBookingForm(forceReload) {
		if (!forceReload && state.bookingInit) {
			return;
		}

		if (!forceReload && window.wc_bookings_booking_form && window.booking_form_params) {
			state.bookingInit = true;
			return;
		}

		state.bookingInit = false;
		reloadScript(tmVendorBookingModal.bookingScriptSrc, function() {
			state.bookingInit = true;
		});
	}

	function activateBookingForm($panel) {
		var $form = $panel.find('form.cart');
		if (!$form.length) {
			return;
		}

		$form.find('.wc-bookings-booking-form, .wc-bookings-booking-form-button').show().prop('disabled', false);

		if (window.wc_bookings_booking_form) {
			window.wc_bookings_booking_form.wc_booking_form = $form;
		}

		var attempts = 0;
		function tryInit() {
			attempts += 1;
			if (window.wc_bookings_booking_form && window.wc_bookings_booking_form.wc_bookings_date_picker && typeof window.wc_bookings_booking_form.wc_bookings_date_picker.init === 'function') {
				window.wc_bookings_booking_form.wc_bookings_date_picker.init();
				$form.find('.wc-bookings-booking-form input, .wc-bookings-booking-form select').first().trigger('change');
				return;
			}

			if (attempts < 8) {
				setTimeout(tryInit, 150);
			}
		}

		tryInit();
	}

	function initCheckoutForm() {
		if (state.checkoutInit) {
			return;
		}

		if (window.wc_checkout_params) {
			state.checkoutInit = true;
			return;
		}

		reloadScript(tmVendorBookingModal.checkoutScriptSrc, function() {
			state.checkoutInit = true;
		});
	}

	function loadBookingForm() {
		if (state.loadingForm) {
			return;
		}

		var $panel = getBookingPanel();
		state.loadingForm = true;
		showLoading($panel, tmVendorBookingModal.strings.loadingForm);

		if (!tmVendorBookingModal.vendorId) {
			showPanelMessage($panel, tmVendorBookingModal.strings.missingProduct);
			state.loadingForm = false;
			return;
		}

		$.ajax({
			url: tmVendorBookingModal.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: tmVendorBookingModal.formAction,
				nonce: tmVendorBookingModal.nonce,
				vendorId: tmVendorBookingModal.vendorId
			}
		}).done(function(response) {
			if (!response || !response.success) {
				showPanelMessage($panel, tmVendorBookingModal.strings.missingProduct);
				return;
			}

			$panel.html(response.data.html);
			if (response.data.productUrl) {
				$panel.data('productUrl', response.data.productUrl);
			}
			
			// Update modal title with vendor name
			if (response.data.vendorName) {
				$('#tm-booking-modal-title').text('Book a session with ' + response.data.vendorName);
			}
			
			state.formLoaded = true;
			initBookingForm(true);
			activateBookingForm($panel);
		}).fail(function() {
			showPanelMessage($panel, tmVendorBookingModal.strings.missingProduct);
		}).always(function() {
			state.loadingForm = false;
		});
	}

	// Ensure all input fields have visible labels or placeholder text
	function ensureFieldLabels($container) {
		var fieldMap = {
			'billing_first_name': 'First Name',
			'billing_last_name': 'Last Name',
			'billing_email': 'Email Address',
			'billing_phone': 'Phone Number'
		};

		// For each input field, ensure it has a visible label
		$container.find('.woocommerce-billing-fields, .woocommerce-checkout').find('input, select').each(function() {
			var $input = $(this);
			var fieldId = $input.attr('id') || $input.attr('name');
			
			if (!fieldId) {
				return;
			}

			var $label = $container.find('label[for="' + fieldId + '"]');
			
			// Ensure the label is visible if it exists
			if ($label.length) {
				$label.css({
					'display': 'inline-block !important',
					'visibility': 'visible !important',
					'opacity': '1 !important',
					'color': '#d7c28a',
					'font-size': '12px',
					'text-transform': 'uppercase',
					'margin-bottom': '6px'
				});
				console.log('TM Modal: Label found and styled for', fieldId);
			} else if (fieldMap[fieldId]) {
				console.warn('TM Modal: No label found for', fieldId, '- field might not be rendering');
			}

			// Ensure input is visible
			$input.css({
				'display': 'block',
				'visibility': 'visible',
				'opacity': '1'
			});
		});

		// Log checkout form structure for debugging
		var $billingFields = $container.find('.woocommerce-billing-fields');
		if ($billingFields.length) {
			console.log('TM Modal: Billing fields container found');
			console.log('TM Modal: Number of form-row elements:', $billingFields.find('.form-row').length);
			console.log('TM Modal: Number of input fields:', $billingFields.find('input, select').length);
		} else {
			console.warn('TM Modal: Billing fields container NOT found');
		}
	}

	// Split full name into first and last name before checkout submission
	function setupNameSplitting() {
		var $checkoutForm = $('form.checkout');
		if (!$checkoutForm.length) {
			return;
		}

		// Listen for checkout form submission
		$checkoutForm.on('checkout_place_order', function() {
			if (!$checkoutForm.find('input[name="tm_modal_checkout"]').length) {
				$checkoutForm.append('<input type="hidden" name="tm_modal_checkout" value="1" />');
			}

			var $fullNameField = $('#billing_first_name');
			var $lastNameField = $('#billing_last_name');
			
			if ($fullNameField.length && $lastNameField.length) {
				var fullName = $fullNameField.val().trim();
				
				if (fullName) {
					// Split name on last space
					var lastSpaceIndex = fullName.lastIndexOf(' ');
					var firstName = '';
					var lastName = '';
					
					if (lastSpaceIndex > 0) {
						firstName = fullName.substring(0, lastSpaceIndex).trim();
						lastName = fullName.substring(lastSpaceIndex + 1).trim();
					} else {
						// If no space, use full name as first name
						firstName = fullName;
						lastName = '-';
					}
					
					// Update the fields
					$fullNameField.val(firstName);
					$lastNameField.val(lastName);
				}
			}
			
			// Allow form to continue submitting
			return true;
		});
	}

	function updatePrivacyText() {
		var $privacyText = $('.woocommerce-privacy-policy-text');
		if (!$privacyText.length) {
			return;
		}

		var customText = tmVendorBookingModal.strings && tmVendorBookingModal.strings.privacyText
			? tmVendorBookingModal.strings.privacyText
			: 'Your data will be used to process your order.';

		$privacyText.html(customText);
	}

	function updateTermsWrapper() {
		var $termsWrapper = $('.woocommerce-terms-and-conditions-wrapper');
		if (!$termsWrapper.length) {
			return;
		}

		if ($termsWrapper.hasClass('tm-terms-customized')) {
			return;
		}

		if (tmVendorBookingModal.strings && tmVendorBookingModal.strings.termsHtml) {
			$termsWrapper.html(tmVendorBookingModal.strings.termsHtml);
			$termsWrapper.addClass('tm-terms-customized');
		}
	}

	function loadCheckout() {
		if (state.loadingCheckout) {
			return;
		}

		ensureModalAssets(function() {
			var $panel = getCheckoutPanel();
			state.loadingCheckout = true;
			showLoading($panel, tmVendorBookingModal.strings.loadingCheckout);

			$.ajax({
				url: tmVendorBookingModal.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: tmVendorBookingModal.checkoutAction,
					nonce: tmVendorBookingModal.nonce
				}
			}).done(function(response) {
				if (!response || !response.success) {
					showPanelMessage($panel, tmVendorBookingModal.strings.checkoutError);
					return;
				}

				// Just insert the HTML and let WooCommerce's scripts do their work
				$panel.html(response.data.html).addClass('is-loaded');
				state.checkoutLoaded = true;
				
				// Add handler to split full name into first/last before checkout submission
				setupNameSplitting();
				updatePrivacyText();
				updateTermsWrapper();
				if (!state.privacyBound) {
					$(document.body).on('updated_checkout', updatePrivacyText);
					$(document.body).on('updated_checkout', updateTermsWrapper);
					state.privacyBound = true;
				}
				
				console.log('TM Modal: Checkout loaded - letting WooCommerce handle initialization');
				
				getModal().addClass('tm-booking-modal--checkout');
			}).fail(function() {
				showPanelMessage($panel, tmVendorBookingModal.strings.checkoutError);
			}).always(function() {
				state.loadingCheckout = false;
			});
		});
	}

	function isBookingButton($button) {
		if (!$button || !$button.length) {
			return false;
		}

		if ($button.find('.fa-calendar-alt').length) {
			return true;
		}

		var text = $.trim($button.text()).toLowerCase();
		return text.indexOf('book session') !== -1;
	}

	$(document).on('click', '.vendor-cta-btn', function(event) {
		var $button = $(this);
		if (!isBookingButton($button)) {
			return;
		}

		event.preventDefault();
		openModal();

		ensureModalAssets(function() {
			if (!state.formLoaded) {
				loadBookingForm();
			}
		});
	});

	$(document).on('click', '.tm-booking-modal__backdrop, .tm-booking-modal__close', function(event) {
		event.preventDefault();
		closeModal();
	});

	$(document).on('keydown', function(event) {
		if (event.key === 'Escape' && state.isOpen) {
			closeModal();
		}
	});

	$(document).on('submit', '.tm-booking-modal form.cart', function(event) {
		if (!state.formLoaded) {
			return;
		}

		event.preventDefault();

		var $form = $(this);
		var serializedForm = $form.serialize();
		var $button = $form.find('.single_add_to_cart_button');
		var $panel = getBookingPanel();

		$button.addClass('loading');

		$.ajax({
			url: tmVendorBookingModal.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: tmVendorBookingModal.addToCartAction,
				nonce: tmVendorBookingModal.nonce,
				formData: serializedForm
			}
		}).done(function(response) {
			if (!response || !response.success) {
				var message = tmVendorBookingModal.strings.addToCartError;
				if (response && response.data && response.data.message) {
					message = response.data.message;
				}
				showPanelMessage(getBookingPanel(), message);
				return;
			}

			$(document.body).trigger('added_to_cart');
			loadCheckout();
		}).fail(function() {
			showPanelMessage(getBookingPanel(), tmVendorBookingModal.strings.addToCartError);
		}).always(function() {
			$button.removeClass('loading');
		});
	});
})(jQuery);
