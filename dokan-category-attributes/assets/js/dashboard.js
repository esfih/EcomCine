/**
 * Dashboard JavaScript
 * Handles conditional field visibility in vendor dashboard
 * 
 * @package Dokan_Category_Attributes
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		console.log('[DCA] Dashboard script initialized');
		
		// Function to get selected categories
		function getSelectedCategories() {
			var categories = [];
			
			// Try Select2 dropdown first
			var $select = $('select[name*="categor"]');
			if ($select.length > 0) {
				console.log('[DCA] Found category select element');
				
				// Get values from Select2
				var selectedValues = $select.val();
				if (selectedValues && Array.isArray(selectedValues)) {
					categories = selectedValues;
				}
				
				// Also check visible selection chips
				$('.select2-selection__choice').each(function() {
					var text = $(this).text().replace('×', '').trim().toLowerCase();
					if (text && !categories.includes(text)) {
						categories.push(text);
					}
				});
			}
			
			console.log('[DCA] Selected categories:', categories);
			return categories;
		}
		
		// Function to update field visibility
		function updateFieldVisibility() {
			var selectedCategories = getSelectedCategories();
			var totalShown = 0;
			var totalHidden = 0;
			
			// Show/hide attribute sections and fields
			$('.dca-attribute-section, .dca-field-wrapper').each(function() {
				var $element = $(this);
				var elementCategories = ($element.data('category') || '').toString().split(',');
				var shouldShow = false;
				
				// Check if any selected category matches
				for (var i = 0; i < selectedCategories.length; i++) {
					if (elementCategories.includes(selectedCategories[i])) {
						shouldShow = true;
						break;
					}
				}
				
				if (shouldShow) {
					$element.show();
					totalShown++;
				} else {
					$element.hide();
					totalHidden++;
				}
			});
			
			console.log('[DCA] Fields shown:', totalShown, 'hidden:', totalHidden);
		}
		
		// Initial update
		updateFieldVisibility();
		
		// Watch for category changes (Select2)
		$(document).on('change', 'select[name*="categor"]', function() {
			console.log('[DCA] Category selection changed');
			setTimeout(updateFieldVisibility, 100); // Small delay for Select2 to update
		});
		
		// Watch for Select2 events
		$(document).on('select2:select select2:unselect', 'select[name*="categor"]', function() {
			console.log('[DCA] Select2 event detected');
			setTimeout(updateFieldVisibility, 100);
		});
		
		// Fallback: Watch for checkbox changes (if site uses checkboxes instead)
		$(document).on('change', 'input[name*="category"]', function() {
			console.log('[DCA] Category checkbox changed');
			updateFieldVisibility();
		});
		
		// Debug: Log all select elements on page
		console.log('[DCA] All select elements:', $('select').length);
		$('select').each(function() {
			console.log('[DCA] Select name:', $(this).attr('name'));
		});
	});
	
})(jQuery);
