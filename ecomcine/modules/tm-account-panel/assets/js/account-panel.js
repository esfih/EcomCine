(function($) {
	"use strict";

	function persistModalState(accountTab) {
		try {
			window.localStorage.setItem("tmAccountOpen", "1");
			if (accountTab) {
				window.localStorage.setItem("tmAccountTab", accountTab);
			}
		} catch (e) {
			// no-op
		}
	}

	function openAccountModal(preferredManageTab, preferredAccountTab) {
		var $modal = $("#tm-account-modal");
		if (!$modal.length) return;
		$modal.addClass("is-open").attr("aria-hidden", "false");
		$("body").addClass("tm-account-open");
		$(".tm-account-tab").attr("aria-expanded", "true");
		setAccountTab($modal, preferredAccountTab || "login");
		prepareLoginForm($modal);
		prepareVendorRegistration($modal);
		setManageTab($modal, preferredManageTab || "orders");
		$(document).trigger("tm-account:open");
	}

	function prepareLoginForm($modal) {
		var $form = $modal.find(".tm-account-login form.woocommerce-form-login").first();
		if (!$form.length) return;

		if (!$form.hasClass("tm-account-login-grid")) {
			$form.addClass("tm-account-login-grid");
		}

		var $actionsRow = $form.find(".form-row").has(".woocommerce-form-login__rememberme").first();
		if ($actionsRow.length && !$actionsRow.hasClass("tm-account-login-actions-row")) {
			$actionsRow.addClass("tm-account-login-actions-row");
		}
	}

	function openAdminCreateDialog() {
		var $dialog = $("#tm-admin-create-dialog");
		if (!$dialog.length) return;
		$dialog.addClass("is-open").attr("aria-hidden", "false");
		$dialog.find("#tm-admin-talent-name").val("").trigger("focus");
		$dialog.find("#tm-admin-talent-email, #tm-admin-talent-phone").val("");
		$dialog.find(".tm-admin-create-submit").prop("disabled", false).text("Create & Open Profile");
	}

	function closeAdminCreateDialog() {
		var $dialog = $("#tm-admin-create-dialog");
		if (!$dialog.length) return;
		$dialog.removeClass("is-open").attr("aria-hidden", "true");
	}

	function submitAdminCreateForm($form) {
		var fullName = ($form.find("#tm-admin-talent-name").val() || "").trim();
		if (!fullName) {
			$form.find("#tm-admin-talent-name").trigger("focus");
			return;
		}
		var email = ($form.find("#tm-admin-talent-email").val() || "").trim();
		var phone = ($form.find("#tm-admin-talent-phone").val() || "").trim();
		var $submit = $form.find(".tm-admin-create-submit");
		$submit.prop("disabled", true).text("Creating…");

		if (!window.tmAccountPanel || !tmAccountPanel.adminNonce) {
			console.warn("[submitAdminCreateForm] tmAccountPanel not available");
			$submit.prop("disabled", false).text("Create & Open Profile");
			return;
		}

		$.ajax({
			url: tmAccountPanel.ajaxUrl,
			method: "post",
			dataType: "json",
			data: {
				action: "tm_account_create_talent",
				nonce: tmAccountPanel.adminNonce,
				talent_full_name: fullName,
				talent_email: email,
				talent_phone: phone
			}
		})
		.done(function(response) {
			console.log("[createTalentProfile] AJAX done", response);
			var personLabelSingular = (window.tmAccountPanel && tmAccountPanel.personLabelSingular) ? tmAccountPanel.personLabelSingular : 'Talent';
			if (response && response.success && response.data && response.data.store_url) {
				closeAdminCreateDialog();
				window.location.href = response.data.store_url;
				return;
			}
			var msg = (response && response.data && response.data.message) ? response.data.message : "unknown";
			if (msg === "email_exists") {
				alert("That email address is already registered. Please use a different email or leave it blank.");
			} else {
				alert("Unable to create " + personLabelSingular.toLowerCase() + " profile: " + msg);
			}
			$submit.prop("disabled", false).text("Create & Open Profile");
		})
		.fail(function(jqXHR, textStatus, errorThrown) {
			console.error("[createTalentProfile] AJAX failed", textStatus, errorThrown, jqXHR.status, jqXHR.responseText);
			var personLabelSingular = (window.tmAccountPanel && tmAccountPanel.personLabelSingular) ? tmAccountPanel.personLabelSingular : 'Talent';
			alert("Unable to create a new " + personLabelSingular.toLowerCase() + " profile.");
			$submit.prop("disabled", false).text("Create & Open Profile");
		});
	}

	function createTalentProfile($trigger) {
		openAdminCreateDialog();
	}

	function closeAccountModal() {
		var $modal = $("#tm-account-modal");
		if (!$modal.length) return;
		$modal.removeClass("is-open").attr("aria-hidden", "true");
		$("body").removeClass("tm-account-open");
		$(".tm-account-tab").attr("aria-expanded", "false");
		$(document).trigger("tm-account:close");
	}

	function setAccountTab($modal, tabName) {
		var $tabs = $modal.find(".tm-account-tab-btn");
		var $forms = $modal.find(".tm-account-form");
		var isRegister = tabName === "register";

		$tabs.removeClass("is-active").attr("aria-selected", "false");
		$tabs.filter("[data-tab='" + tabName + "']").addClass("is-active").attr("aria-selected", "true");

		$forms.removeClass("is-active");
		$modal.find(isRegister ? ".tm-account-register" : ".tm-account-login").addClass("is-active");
	}



	function slugifyName(name) {
		return (name || "")
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, "-")
			.replace(/^-+|-+$/g, "");
	}

	function prepareVendorRegistration($modal) {
		var $form = $modal.find("form#dokan-vendor-register, form.dokan-vendor-register, form#ecomcine-vendor-register, form.ecomcine-vendor-register");
		if (!$form.length) return;

		if (!$form.hasClass("tm-account-register-grid")) {
			$form.addClass("tm-account-register-grid");
		}

		var $first = $form.find("#first-name");
		var $last = $form.find("#last-name");
		var $shopName = $form.find("#company-name");
		var $shopUrl = $form.find("#seller-url");
		var $nativePassword = $form.find('input[name="password"]').first();
		var $roleSelectorRow = $form.find(".user-role.vendor-customer-registration").first();
		var selectedRole = "seller";
		if ($roleSelectorRow.length) {
			var checkedRole = ($roleSelectorRow.find('input[name="role"]:checked').val() || "").toLowerCase();
			if (checkedRole === "customer") {
				selectedRole = "customer";
			}
		}
		var $role = $form.find('input[name="role"][type="hidden"]').first();
		if (!$role.length) {
			$role = $('<input type="hidden" name="role" value="seller" />').appendTo($form);
		}
		$role.val(selectedRole);
		if ($roleSelectorRow.length) {
			$roleSelectorRow.remove();
		}
		var $addressWrapper = $form.find("#dokan-address-fields-wrapper");
		var $addressBlock = $addressWrapper.length ? $addressWrapper.closest(".dokan-form-group") : $();
		var $nameSplit = $form.find(".split-row.name-field").first();
		var $firstRow = $first.closest("p.form-row");
		var $lastRow = $last.closest("p.form-row");
		var $shopNameRow = $shopName.closest("p.form-row");
		var $shopUrlRow = $shopUrl.closest("p.form-row");

		function ensureHiddenCarrier(name, id, initialValue) {
			var $hidden = $form.find('input[name="' + name + '"][type="hidden"]').first();
			if (!$hidden.length) {
				$hidden = $('<input type="hidden" />').attr("name", name).attr("id", id).appendTo($form);
			}
			if (typeof initialValue === "string" && initialValue.length && !$hidden.val()) {
				$hidden.val(initialValue);
			}
			return $hidden;
		}

		if ($nameSplit.length && $firstRow.length && !$firstRow.parent().is($form)) {
			$firstRow.insertBefore($nameSplit);
		}
		if ($nameSplit.length) {
			$nameSplit.remove();
		}

		if (!$form.find("#tm-account-type").length) {
			var personLabelSingular = (window.tmAccountPanel && tmAccountPanel.personLabelSingular) ? tmAccountPanel.personLabelSingular : 'Talent';
			var accountTypeHtml =
				'<p class="form-row form-group tm-account-account-type-row">' +
				'<label for="tm-account-type">Account Type <span class="required">*</span></label>' +
				'<select id="tm-account-type" class="input-text form-control" required="required">' +
				'<option value="talent">' + personLabelSingular + ' (Vendor)</option>' +
				'<option value="hirer">Hirer (Customer)</option>' +
				'</select>' +
				'</p>';
			$form.prepend(accountTypeHtml);
		}

		var $accountTypeHidden = $form.find('input[name="tm_account_type"]').first();
		if (!$accountTypeHidden.length) {
			$accountTypeHidden = $('<input type="hidden" name="tm_account_type" value="talent" />').appendTo($form);
		}

		var $existingTerms = $form.find("#tc_agree").first();
		if ($existingTerms.length) {
			$existingTerms.closest("p.form-row").remove();
		}

		if (!$form.find(".tm-account-terms-grid").length) {
			var privacyUrl = (window.tmAccountPanel && tmAccountPanel.privacyUrl) ? tmAccountPanel.privacyUrl : (window.location.origin + "/privacy");
			var termsUrl = (window.tmAccountPanel && tmAccountPanel.talentTermsUrl) ? tmAccountPanel.talentTermsUrl : (window.location.origin + "/talent-terms/");
			var personTermsLabel = (window.tmAccountPanel && tmAccountPanel.personTermsLabel) ? tmAccountPanel.personTermsLabel : 'Talent Terms';
			var termsRow =
				'<div class="tm-account-terms-grid">' +
				'<div class="tm-account-terms-item">' +
				'<input type="checkbox" id="tm-account-accept-privacy" name="tm_accept_privacy" class="input-checkbox" required="required" />' +
				'<label for="tm-account-accept-privacy">Accept <a href="' + privacyUrl + '" target="_blank" rel="noopener noreferrer">privacy policy</a></label>' +
				'</div>' +
				'<div class="tm-account-terms-item">' +
				'<input type="checkbox" id="tm-account-accept-terms" name="tc_agree" class="input-checkbox" required="required" />' +
				'<label for="tm-account-accept-terms">Accept <a class="tm-account-terms-link" href="' + termsUrl + '" target="_blank" rel="noopener noreferrer"><span class="tm-account-terms-link-label">' + personTermsLabel.toLowerCase() + '</span> &amp; conditions</a></label>' +
				'</div>' +
				'</div>';
			var $submitRow = $form.find('input[name="register"], button[name="register"]').first().closest("p.form-row");
			if ($submitRow.length) {
				$submitRow.addClass("tm-account-submit-row");
				$(termsRow).insertBefore($submitRow);
			} else {
				$form.append(termsRow);
			}
		}

		if (!$form.find(".tm-account-password-row").length) {
			var existingPassword = $nativePassword.length ? ($nativePassword.val() || "") : "";
			var passwordRow =
				'<div class="tm-account-password-row tm-account-grid-item">' +
				'<p class="form-row form-group tm-account-password-col">' +
				'<label for="tm-account-password">Password <span class="required">*</span></label>' +
				'<input type="password" class="input-text form-control" name="tm_reg_password" id="tm-account-password" required="required" minlength="6" autocomplete="new-password" value="' + existingPassword.replace(/"/g, '&quot;') + '" />' +
				'</p>' +
				'<p class="form-row form-group tm-account-password-col">' +
				'<label for="tm-account-password-confirm">Repeat Password <span class="required">*</span></label>' +
				'<input type="password" class="input-text form-control" name="tm_reg_password_confirm" id="tm-account-password-confirm" required="required" minlength="6" autocomplete="new-password" value="' + existingPassword.replace(/"/g, '&quot;') + '" />' +
				'</p>' +
				'</div>';

			var $termsGrid = $form.find(".tm-account-terms-grid").first();
			if ($termsGrid.length) {
				$(passwordRow).insertBefore($termsGrid);
			} else {
				var $submitRowForPassword = $form.find('input[name="register"], button[name="register"]').first().closest("p.form-row");
				if ($submitRowForPassword.length) {
					$(passwordRow).insertBefore($submitRowForPassword);
				} else {
					$form.append(passwordRow);
				}
			}
		}

		var $customPassword = $form.find("#tm-account-password").first();
		var $customPasswordConfirm = $form.find("#tm-account-password-confirm").first();
		if ($nativePassword.length) {
			var $nativePasswordRow = $nativePassword.closest("p.form-row");
			if ($nativePasswordRow.length) {
				$nativePasswordRow.remove();
			} else {
				$nativePassword.remove();
			}
		}
		var $passwordCarrier = ensureHiddenCarrier("password", "tm-account-password-hidden", "");

		$form.find("label[for='first-name']").contents().filter(function() {
			return this.nodeType === 3;
		}).first().replaceWith("Full Name ");

		var initialLastName = ($last.val() || "").trim();
		var initialShopName = ($shopName.val() || "").trim();
		var initialShopUrl = ($shopUrl.val() || "").trim();

		if ($lastRow.length) {
			$lastRow.remove();
		}
		if ($shopNameRow.length) {
			$shopNameRow.remove();
		}
		if ($shopUrlRow.length) {
			$shopUrlRow.remove();
		}

		$last = ensureHiddenCarrier("lname", "tm-account-lname-hidden", initialLastName);
		$shopName = ensureHiddenCarrier("shopname", "tm-account-shopname-hidden", initialShopName);
		$shopUrl = ensureHiddenCarrier("shopurl", "tm-account-shopurl-hidden", initialShopUrl);

		if ($addressBlock.length) {
			$addressBlock.remove();
		}
		$form.find("#dokan_selected_country, #dokan_selected_state").remove();

		$form.find(
			"p.form-row, .tm-account-terms-grid"
		).addClass("tm-account-grid-item");

		function applyAccountType() {
			var selectedType = ($form.find("#tm-account-type").val() || "talent").toLowerCase();
			$role.val(selectedType === "hirer" ? "customer" : "seller");
			$accountTypeHidden.val(selectedType);

			var talentTermsUrl = (window.tmAccountPanel && tmAccountPanel.talentTermsUrl) ? tmAccountPanel.talentTermsUrl : (window.location.origin + "/talent-terms/");
			var hirerTermsUrl = (window.tmAccountPanel && tmAccountPanel.hirerTermsUrl) ? tmAccountPanel.hirerTermsUrl : (window.location.origin + "/hirer-terms/");
			var personTermsLabel = (window.tmAccountPanel && tmAccountPanel.personTermsLabel) ? tmAccountPanel.personTermsLabel : 'Talent Terms';
			var $termsLink = $form.find(".tm-account-terms-link");
			var $termsLabel = $form.find(".tm-account-terms-link-label");

			if ($termsLink.length) {
				if (selectedType === "hirer") {
					$termsLink.attr("href", hirerTermsUrl);
					$termsLabel.text("hirer terms");
				} else {
					$termsLink.attr("href", talentTermsUrl);
					$termsLabel.text(personTermsLabel.toLowerCase());
				}
			}
		}

		$form.find("#tm-account-type").off("change.tmaccount").on("change.tmaccount", function() {
			applyAccountType();
		});

		if (($role.val() || "").toLowerCase() === "customer") {
			$form.find("#tm-account-type").val("hirer");
		}
		applyAccountType();

		function syncGeneratedFields() {
			var fullName = ($first.val() || "").trim();
			var slug = slugifyName(fullName);
			$last.val(fullName);
			$shopName.val(fullName);
			$shopUrl.val(slug);
		}

		function syncPasswordFields() {
			$passwordCarrier.val(($customPassword.val() || "").trim());
		}

		$first.off("input.tmaccount").on("input.tmaccount", syncGeneratedFields);
		$customPassword.off("input.tmaccount").on("input.tmaccount", syncPasswordFields);
		$form.off("submit.tmaccount").on("submit.tmaccount", function() {
			var passwordValue = ($customPassword.val() || "").trim();
			var confirmValue = ($customPasswordConfirm.val() || "").trim();
			if (!passwordValue || passwordValue.length < 6) {
				alert("Please enter a password with at least 6 characters.");
				$customPassword.trigger("focus");
				return false;
			}
			if (!confirmValue) {
				alert("Please repeat your password.");
				$customPasswordConfirm.trigger("focus");
				return false;
			}
			if (passwordValue !== confirmValue) {
				alert("Passwords do not match.");
				$customPasswordConfirm.trigger("focus");
				return false;
			}
			applyAccountType();
			syncGeneratedFields();
			syncPasswordFields();
			persistModalState("register");
		});
		syncGeneratedFields();
		syncPasswordFields();
	}

	$(document).on("click", ".tm-account-tab", function() {
		if ($(this).hasClass("tm-account-tab--admin")) return;
		openAccountModal();
	});

	$(document).on("click", ".tm-account-tab--admin", function() {
		createTalentProfile($(this));
	});

	$(document).on("keydown", ".tm-account-tab", function(e) {
		if ($(this).hasClass("tm-account-tab--admin")) return;
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault();
			openAccountModal();
		}
	});

	$(document).on("keydown", ".tm-account-tab--admin", function(e) {
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault();
			createTalentProfile($(this));
		}
	});

	$(document).on("click", ".tm-account-tab-btn", function() {
		var $modal = $("#tm-account-modal");
		if (!$modal.length) return;
		var tabName = $(this).data("tab");
		setAccountTab($modal, tabName);
	});

	$(document).on("click", ".tm-account-manage-tab", function() {
		var $modal = $("#tm-account-modal");
		if (!$modal.length) return;
		var tabName = $(this).data("tab");
		setManageTab($modal, tabName);
	});

	function setManageTab($modal, tabName) {
		var $tabs = $modal.find(".tm-account-manage-tab");
		var $panels = $modal.find(".tm-account-manage-panel");
		if (!$tabs.length || !$panels.length) return;

		$tabs.removeClass("is-active").attr("aria-selected", "false");
		$tabs.filter("[data-tab='" + tabName + "']").addClass("is-active").attr("aria-selected", "true");

		$panels.removeClass("is-active").attr("aria-hidden", "true");
		$panels.filter("#tm-account-panel-" + tabName)
			.addClass("is-active")
			.attr("aria-hidden", "false");
	}

	function loadOrderDetails(orderId, $container) {
		if (!orderId || !$container || !$container.length) return;
		var $list = $container.find(".tm-account-orders-list");
		var $detail = $container.find(".tm-account-orders-detail");
		$detail.addClass("is-loading");

		$.ajax({
			url: tmAccountPanel.ajaxUrl,
			method: "post",
			dataType: "json",
			data: {
				action: "tm_account_panel_order_details",
				nonce: tmAccountPanel.orderNonce,
				order_id: orderId
			}
		})
		.done(function(response) {
			if (response && response.success && response.data && response.data.html) {
				$detail.html(
					'<button class="tm-account-orders-back" type="button">Back to orders</button>' +
					response.data.html
				);
				$detail.removeClass("is-loading").addClass("is-active").attr("aria-hidden", "false");
				$list.addClass("is-hidden");
				return;
			}
			$detail.html('<p class="tm-account-muted">Unable to load order details.</p>');
		})
		.fail(function() {
			$detail.html('<p class="tm-account-muted">Unable to load order details.</p>');
		})
		.always(function() {
			$detail.removeClass("is-loading");
		});
	}

	$(document).on("click", ".tm-account-section--orders .dokan-order-id a, .tm-account-section--orders .dokan-order-action a", function(e) {
		var href = $(this).attr("href") || "";
		if (href.indexOf("order_id=") === -1) return;
		var match = href.match(/order_id=(\d+)/);
		if (!match) return;
		e.preventDefault();
		var orderId = parseInt(match[1], 10);
		var $container = $(this).closest(".tm-account-orders-content");
		loadOrderDetails(orderId, $container);
	});

	$(document).on("click", ".tm-account-orders-back", function() {
		var $container = $(this).closest(".tm-account-orders-content");
		$container.find(".tm-account-orders-detail").removeClass("is-active").attr("aria-hidden", "true").empty();
		$container.find(".tm-account-orders-list").removeClass("is-hidden");
	});

	$(document).on("submit", ".tm-account-login form", function() {
		persistModalState("login");
	});

	$(document).on("submit", ".tm-account-register form", function() {
		persistModalState("register");
	});

	$(document).on("click", ".tm-account-order-actions .tm-account-action-btn", function() {
		var $button = $(this);
		var status = $button.data("status");
		var $actions = $button.closest(".tm-account-order-actions");
		var orderId = $actions.data("orderId");
		var nonce = $actions.data("nonce");
		if (!status || !orderId || !nonce) return;

		$button.prop("disabled", true);
		$.ajax({
			url: tmAccountPanel.ajaxUrl,
			method: "post",
			dataType: "json",
			data: {
				action: "dokan_change_status",
				order_id: orderId,
				order_status: status,
				_wpnonce: nonce
			}
		})
		.done(function(response) {
			if (response && response.success && response.data) {
				var text = $("<div>").html(response.data).text().trim();
				var $status = $(".tm-account-order-status").first();
				if (text) {
					$status.text(text);
				}
			}
		})
		.always(function() {
			$button.prop("disabled", false);
		});
	});


	$(document).on("click", ".tm-account-backdrop, .tm-account-close", function() {
		closeAccountModal();
	});

	$(document).on("click", ".tm-admin-create-backdrop, .tm-admin-create-close", function() {
		closeAdminCreateDialog();
	});

	$(document).on("submit", "#tm-admin-create-form", function(e) {
		e.preventDefault();
		submitAdminCreateForm($(this));
	});

	$(document).on("keydown", function(e) {
		if (e.key !== "Escape") return;
		if ($("#tm-admin-create-dialog").hasClass("is-open")) {
			closeAdminCreateDialog();
			return;
		}
		if ($("#tm-account-modal").hasClass("is-open")) {
			closeAccountModal();
		}
	});

	$(function() {
		var shouldOpen = false;
		var preferredTab = "login";
		var queryParams = null;
		var search = window.location.search || "";
		var isOnboardingRedirect = search.indexOf("tm_onboard_claimed=") !== -1 || search.indexOf("tm_onboard=") !== -1;
		if (window.URLSearchParams) {
			queryParams = new URLSearchParams(search);
			isOnboardingRedirect = queryParams.has("tm_onboard_claimed") || queryParams.has("tm_onboard");
		}
		try {
			shouldOpen = window.localStorage.getItem("tmAccountOpen") === "1";
			preferredTab = window.localStorage.getItem("tmAccountTab") || "login";
			if (shouldOpen) {
				window.localStorage.removeItem("tmAccountOpen");
				window.localStorage.removeItem("tmAccountTab");
			}
		} catch (e) {
			shouldOpen = false;
			preferredTab = "login";
		}

		if (isOnboardingRedirect) {
			shouldOpen = false;
			preferredTab = "login";
		}

		var hasRegistrationErrors = $(
			".woocommerce-error, .dokan-alert-danger, .woocommerce-notices-wrapper .woocommerce-error"
		).length > 0;
		if (hasRegistrationErrors) {
			shouldOpen = true;
			preferredTab = "register";
		}

		if (shouldOpen) {
			openAccountModal("orders", preferredTab);
		}

		if ($("#tm-account-modal").hasClass("is-open")) {
			$("body").addClass("tm-account-open");
			$(document).trigger("tm-account:open");
		}
	});
})(jQuery);
