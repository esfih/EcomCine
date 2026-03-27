<?php
/**
 * Filter Provider Contract
 *
 * Core contract for store-listing attribute filters (feature dca-004).
 * Produces a schema for rendering filter controls and modifies the
 * vendor query args when active filter params are present.
 *
 * @package Dokan_Category_Attributes
 * @since   2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface DCA_Filter_Provider {

	/**
	 * Return the full filter schema: all active attribute sets that have
	 * fields with show_in_filters = 1.
	 *
	 * @return array[] Each element:
	 *   [
	 *     'set_id'     => int,
	 *     'name'       => string,
	 *     'icon'       => string|null,
	 *     'categories' => string[],   // set's assigned category slugs
	 *     'fields'     => [
	 *       [
	 *         'field_name'    => string,
	 *         'field_label'   => string,
	 *         'field_icon'    => string|null,
	 *         'field_type'    => string,
	 *         'field_options' => array,
	 *       ],
	 *       ...
	 *     ],
	 *   ]
	 */
	public function get_filter_schema(): array;

	/**
	 * Apply active filter params to a vendor query args array.
	 *
	 * Iterates known filter fields. For each field whose name appears
	 * as a key in $active_filters with a non-empty value, appends a
	 * meta_query clause. Params not matching any known field are ignored.
	 *
	 * @param array $query_args     Base WP_User_Query / seller listing args.
	 * @param array $active_filters Associative array of field_name => value|string[].
	 *                              Typically derived from sanitized GET params.
	 * @return array Modified query args with meta_query entries appended.
	 */
	public function apply_query_filters( array $query_args, array $active_filters ): array;
}
