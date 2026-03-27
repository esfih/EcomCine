<?php
/**
 * Default WP Filter Provider — REST-based scaffold (Phase 1 stub).
 *
 * Phase 1: Both methods are stubbed.  get_filter_schema() returns an empty array;
 * apply_query_filters() returns the original args unchanged.
 *
 * Phase 2 implementation plan:
 *  - get_filter_schema()      → serve via REST endpoint `/wp-json/dca/v1/filter-schema`
 *                               built from CPT-based attribute sets
 *  - apply_query_filters()    → build meta_query from the same CPT storage
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * TODO(phase-2): Implement CPT-based filter schema + REST-compatible query builder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_Filter_Provider
 *
 * @implements DCA_Filter_Provider
 */
class DCA_WP_Filter_Provider implements DCA_Filter_Provider {

	/**
	 * @inheritdoc
	 *
	 * Phase 1: stub — returns empty array.
	 *
	 * @return array
	 */
	public function get_filter_schema() {
		// TODO(phase-2): Build schema from 'dca_attribute_set' CPT.
		_doing_it_wrong(
			__METHOD__,
			'Default WP filter provider "get_filter_schema" not yet implemented.',
			'1.1.0'
		);

		return array();
	}

	/**
	 * @inheritdoc
	 *
	 * Phase 1: stub — returns $query_args unchanged.
	 *
	 * @param array $query_args
	 * @param array $active_filters
	 * @return array
	 */
	public function apply_query_filters( array $query_args, array $active_filters ) {
		// TODO(phase-2): Build meta_query from CPT-based attribute sets.
		_doing_it_wrong(
			__METHOD__,
			'Default WP filter provider "apply_query_filters" not yet implemented.',
			'1.1.0'
		);

		return $query_args;
	}
}
