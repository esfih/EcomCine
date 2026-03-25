(function($) {
	"use strict";

	function openAccountModal(preferredManageTab) {
		var $modal = $("#tm-account-modal");
		if (!$modal.length) return;
		$modal.addClass("is-open").attr("aria-hidden", "false");
		$("body").addClass("tm-account-open");
		$(".tm-account-tab").attr("aria-expanded", "true");
		setAccountTab($modal, "login");
		prepareVendorRegistration($modal);
		setManageTab($modal, preferredManageTab || "orders");
		$(document).trigger("tm-account:open");
	}

	function createTalentProfile($trigger) {
		if (!window.tmAccountPanel || !tmAccountPanel.adminNonce) {
			return;
		}
		$trigger = $trigger || $(".tm-account-tab--admin");
		$trigger.addClass("is-loading");
		$.ajax({
			url: tmAccountPanel.ajaxUrl,
			method: "post",
			dataType: "json",
			data: {
				action: "tm_account_create_talent",
				nonce: tmAccountPanel.adminNonce
			}
		})
		.done(function(response) {
			if (response && response.success && response.data && response.data.store_url) {
				window.location.href = response.data.store_url;
				return;
			}
			alert("Unable to create a new talent profile.");
		})
		.fail(function() {
			alert("Unable to create a new talent profile.");
		})
		.always(function() {
			$trigger.removeClass("is-loading");
		});
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
		var $form = $modal.find("form#dokan-vendor-register, form.dokan-vendor-register");
		if (!$form.length) return;

		var $first = $form.find("#first-name");
		var $last = $form.find("#last-name");
		var $shopName = $form.find("#company-name");
		var $shopUrl = $form.find("#seller-url");
		var $addressWrapper = $form.find("#dokan-address-fields-wrapper");
		var $addressBlock = $addressWrapper.length ? $addressWrapper.closest(".dokan-form-group") : $();

		$form.find("label[for='first-name']").contents().filter(function() {
			return this.nodeType === 3;
		}).first().replaceWith("Full Name ");

		$last.closest("p.form-row").addClass("tm-account-hidden-field");
		$shopName.closest("p.form-row").addClass("tm-account-hidden-field");
		$shopUrl.closest("p.form-row").addClass("tm-account-hidden-field");
		if ($addressBlock.length) {
			$addressBlock.addClass("tm-account-hidden-field");
		}

		$last.prop("required", false).removeAttr("required");
		$shopName.prop("required", false).removeAttr("required");
		$shopUrl.prop("required", false).removeAttr("required");

		if ($addressBlock.length) {
			$addressBlock.find("input, select, textarea").each(function() {
				$(this).prop("required", false).removeAttr("required").prop("disabled", true);
			});
		}

		function syncGeneratedFields() {
			var fullName = ($first.val() || "").trim();
			var slug = slugifyName(fullName);
			$last.val(fullName);
			$shopName.val(fullName);
			$shopUrl.val(slug);
		}

		$first.off("input.tmaccount").on("input.tmaccount", syncGeneratedFields);
		$form.off("submit.tmaccount").on("submit.tmaccount", function() {
			syncGeneratedFields();
		});
		syncGeneratedFields();
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
		try {
			window.localStorage.setItem("tmAccountOpen", "1");
		} catch (e) {
			// no-op
		}
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

	$(document).on("keydown", function(e) {
		if (e.key !== "Escape") return;
		if ($("#tm-account-modal").hasClass("is-open")) {
			closeAccountModal();
		}
	});

	$(function() {
		var shouldOpen = false;
		try {
			shouldOpen = window.localStorage.getItem("tmAccountOpen") === "1";
			if (shouldOpen) {
				window.localStorage.removeItem("tmAccountOpen");
			}
		} catch (e) {
			shouldOpen = false;
		}

		if (shouldOpen) {
			openAccountModal("orders");
		}

		if ($("#tm-account-modal").hasClass("is-open")) {
			$("body").addClass("tm-account-open");
			$(document).trigger("tm-account:open");
		}
	});
})(jQuery);
