/**
 * Frontend JavaScript
 * Handles conditional filter visibility on store listing page
 * 
 * @package Dokan_Category_Attributes
 */

(function($) {
	'use strict';
	
	$(document).ready(function() {
		console.log('[DCA] Frontend filters script initialized');
		
		// Function to get selected category from filter
		function getSelectedCategory() {
			var category = '';
			
			// Check category select dropdown
			var $categorySelect = $('select[name="store_category"]');
			if ($categorySelect.length > 0) {
				category = $categorySelect.val();
			}
			
			// Check category radio buttons
			if (!category) {
				var $categoryRadio = $('input[name="store_category"]:checked');
				if ($categoryRadio.length > 0) {
					category = $categoryRadio.val();
				}
			}
			
			console.log('[DCA] Selected filter category:', category);
			return category;
		}
		
		// Function to update filter visibility
		function updateFilterVisibility() {
			var selectedCategory = getSelectedCategory();
			var totalShown = 0;
			var totalHidden = 0;
			
			// Show/hide filter sections and fields
			$('.dca-filter-section, .dca-filter-field').each(function() {
				var $element = $(this);
				var elementCategories = ($element.data('category') || '').toString().split(',');
				var shouldShow = false;
				
				// If no category selected, hide all
				if (!selectedCategory) {
					shouldShow = false;
				} else if (elementCategories.includes(selectedCategory)) {
					shouldShow = true;
				}
				
				if (shouldShow) {
					$element.show();
					totalShown++;
				} else {
					$element.hide();
					// Clear hidden field values
					$element.find('select, input[type="text"], input[type="number"]').val('');
					$element.find('input[type="checkbox"]').prop('checked', false);
					totalHidden++;
				}
			});
			
			console.log('[DCA] Filters shown:', totalShown, 'hidden:', totalHidden);
		}
		
		// Initial update
		updateFilterVisibility();
		
		// Watch for category filter changes
		$(document).on('change', 'select[name="store_category"], input[name="store_category"]', function() {
			console.log('[DCA] Category filter changed');
			updateFilterVisibility();
		});
		
		// Auto-submit filter form on change
		$(document).on('change', '.dca-filter-select, .dca-filter-input', function() {
			// Optional: auto-submit the filter form
			// $(this).closest('form').submit();
		});
	});
	
})(jQuery);
