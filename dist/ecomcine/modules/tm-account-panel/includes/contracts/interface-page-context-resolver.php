<?php
/**
 * Contract: Page context resolver for the account panel.
 *
 * Determines whether the current request is an eligible page for rendering
 * the account panel. Both the compatibility adapter (Dokan) and the
 * default-WP adapter implement this interface.
 *
 * @package TM_Account_Panel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TAP_Page_Context_Resolver {
	/**
	 * Returns true when the account panel should be displayed on the current page.
	 */
	public function is_eligible_page(): bool;
}
