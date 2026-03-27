<?php
/**
 * WP CPT Storage — low-level persistence layer for the Default WP adapter.
 *
 * Registers two private CPTs and exposes typed read/write helpers that convert
 * between WP_Post objects and the stdClass shape that the rest of the adapter
 * layer (and the interface contracts) expect.
 *
 * CPT registry:
 *  - dca_attribute_set   (stores attribute set metadata in post meta)
 *  - dca_attribute_field (stores field metadata; post_parent = set post ID)
 *
 * Vendor values are NOT stored here — they stay in standard user meta so that
 * both adapters share the same values with zero migration work.
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 2 — Default WP Pilot Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_CPT_Storage
 */
final class DCA_WP_CPT_Storage {

	/** CPT slug for attribute sets. */
	const SET_POST_TYPE = 'dca_attribute_set';

	/** CPT slug for attribute fields. */
	const FIELD_POST_TYPE = 'dca_attribute_field';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register both private CPTs.
	 * Must be called on the 'init' WordPress action.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		// Attribute Set CPT — private; not exposed in UI or REST.
		register_post_type(
			self::SET_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Attribute Sets', 'dokan-category-attributes' ),
					'singular_name' => __( 'Attribute Set', 'dokan-category-attributes' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'custom-fields', 'page-attributes' ),
				'capability_type'     => 'post',
			)
		);

		// Attribute Field CPT — private; post_parent = set post ID.
		register_post_type(
			self::FIELD_POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Attribute Fields', 'dokan-category-attributes' ),
					'singular_name' => __( 'Attribute Field', 'dokan-category-attributes' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'custom-fields', 'page-attributes' ),
				'capability_type'     => 'post',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Attribute Set operations
	// -------------------------------------------------------------------------

	/**
	 * Insert a new attribute set CPT post.
	 *
	 * @param array $data  Keys: name (required), slug, icon, categories (array), priority, status.
	 * @return int|false   New post ID or false on failure.
	 */
	public function insert_set( array $data ) {
		$post_status = ( isset( $data['status'] ) && 'draft' === $data['status'] ) ? 'draft' : 'publish';

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::SET_POST_TYPE,
				'post_title'  => sanitize_text_field( $data['name'] ?? '' ),
				'post_name'   => sanitize_title( $data['slug'] ?? ( $data['name'] ?? '' ) ),
				'post_status' => $post_status,
				'menu_order'  => (int) ( $data['priority'] ?? 10 ),
			),
			true // return WP_Error on failure
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		update_post_meta( $post_id, '_dca_set_icon', sanitize_text_field( $data['icon'] ?? '' ) );

		$categories = $data['categories'] ?? array();
		if ( is_string( $categories ) ) {
			$categories = json_decode( $categories, true ) ?: array();
		}
		update_post_meta( $post_id, '_dca_set_categories', wp_json_encode( array_values( (array) $categories ) ) );

		return $post_id;
	}

	/**
	 * Update an existing attribute set CPT post.
	 *
	 * @param int   $post_id  Post ID of the set.
	 * @param array $data     Partial data — only provided keys are updated.
	 * @return bool
	 */
	public function update_set( $post_id, array $data ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}

		$update_args = array( 'ID' => $post_id );

		if ( isset( $data['name'] ) ) {
			$update_args['post_title'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['slug'] ) ) {
			$update_args['post_name'] = sanitize_title( $data['slug'] );
		}
		if ( isset( $data['priority'] ) ) {
			$update_args['menu_order'] = (int) $data['priority'];
		}
		if ( isset( $data['status'] ) ) {
			$update_args['post_status'] = ( 'draft' === $data['status'] ) ? 'draft' : 'publish';
		}

		if ( count( $update_args ) > 1 ) {
			$result = wp_update_post( $update_args, true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		if ( isset( $data['icon'] ) ) {
			update_post_meta( $post_id, '_dca_set_icon', sanitize_text_field( $data['icon'] ) );
		}
		if ( isset( $data['categories'] ) ) {
			$cats = $data['categories'];
			if ( is_string( $cats ) ) {
				$cats = json_decode( $cats, true ) ?: array();
			}
			update_post_meta( $post_id, '_dca_set_categories', wp_json_encode( array_values( (array) $cats ) ) );
		}

		return true;
	}

	/**
	 * Delete an attribute set and all its child field posts.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function delete_set( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}

		// Delete child fields first.
		$child_ids = get_posts(
			array(
				'post_type'      => self::FIELD_POST_TYPE,
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'draft', 'trash', 'any' ),
			)
		);
		foreach ( $child_ids as $child_id ) {
			wp_delete_post( (int) $child_id, true );
		}

		$result = wp_delete_post( $post_id, true );
		return ( false !== $result && null !== $result );
	}

	/**
	 * Get a single attribute set by post ID.
	 *
	 * @param int $post_id
	 * @return object|null  stdClass with same shape as DCA_Database::get_attribute_set().
	 */
	public function get_set( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || self::SET_POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $this->set_to_object( $post );
	}

	/**
	 * Get attribute sets matching the given args.
	 *
	 * @param array $args  status (active|draft|''), orderby, order.
	 * @return object[]    Array of stdClass objects.
	 */
	public function get_sets( array $args = array() ) {
		$defaults = array(
			'status'  => 'active',
			'orderby' => 'priority',
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => self::SET_POST_TYPE,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => ( 'DESC' === strtoupper( $args['order'] ) ) ? 'DESC' : 'ASC',
			'no_found_rows'  => true,
		);

		if ( ! empty( $args['status'] ) ) {
			$query_args['post_status'] = ( 'active' === $args['status'] ) ? 'publish' : sanitize_key( $args['status'] );
		} else {
			$query_args['post_status'] = array( 'publish', 'draft' );
		}

		$posts = get_posts( $query_args );
		return array_map( array( $this, 'set_to_object' ), $posts );
	}

	/**
	 * Convert a WP_Post (dca_attribute_set) to the canonical stdClass shape.
	 *
	 * @param WP_Post $post
	 * @return object
	 */
	public function set_to_object( WP_Post $post ) {
		$obj             = new stdClass();
		$obj->id         = (int) $post->ID;
		$obj->name       = $post->post_title;
		$obj->slug       = $post->post_name;
		$obj->icon       = (string) get_post_meta( $post->ID, '_dca_set_icon', true );
		$raw_cats        = get_post_meta( $post->ID, '_dca_set_categories', true );
		$obj->categories = $raw_cats ? ( json_decode( $raw_cats, true ) ?: array() ) : array();
		$obj->priority   = (int) $post->menu_order;
		$obj->status     = ( 'publish' === $post->post_status ) ? 'active' : $post->post_status;

		return $obj;
	}

	// -------------------------------------------------------------------------
	// Attribute Field operations
	// -------------------------------------------------------------------------

	/**
	 * Insert a new attribute field CPT post.
	 *
	 * @param array $data  Keys: attribute_set_id (required), field_name (required),
	 *                     field_label, field_icon, field_type, field_options (array),
	 *                     required, display_order, show_in_dashboard, show_in_public,
	 *                     show_in_filters.
	 * @return int|false   New post ID or false on failure.
	 */
	public function insert_field( array $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::FIELD_POST_TYPE,
				'post_title'  => sanitize_text_field( $data['field_label'] ?? '' ),
				'post_status' => 'publish',
				'post_parent' => (int) ( $data['attribute_set_id'] ?? 0 ),
				'menu_order'  => (int) ( $data['display_order'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		update_post_meta( $post_id, '_dca_field_name', sanitize_key( $data['field_name'] ?? '' ) );
		update_post_meta( $post_id, '_dca_field_icon', sanitize_text_field( $data['field_icon'] ?? '' ) );
		update_post_meta( $post_id, '_dca_field_type', sanitize_key( $data['field_type'] ?? 'select' ) );

		$options = $data['field_options'] ?? array();
		if ( is_string( $options ) ) {
			$options = json_decode( $options, true ) ?: array();
		}
		update_post_meta( $post_id, '_dca_field_options', wp_json_encode( (array) $options ) );

		update_post_meta( $post_id, '_dca_field_required',        (int) ( $data['required']          ?? 0 ) );
		update_post_meta( $post_id, '_dca_field_show_dashboard',  (int) ( $data['show_in_dashboard'] ?? 1 ) );
		update_post_meta( $post_id, '_dca_field_show_public',     (int) ( $data['show_in_public']    ?? 1 ) );
		update_post_meta( $post_id, '_dca_field_show_filters',    (int) ( $data['show_in_filters']   ?? 1 ) );

		return $post_id;
	}

	/**
	 * Update an existing attribute field post.
	 *
	 * @param int   $post_id
	 * @param array $data    Partial data — only provided keys are updated.
	 * @return bool
	 */
	public function update_field( $post_id, array $data ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}

		$update_args = array( 'ID' => $post_id );
		if ( isset( $data['field_label'] ) ) {
			$update_args['post_title'] = sanitize_text_field( $data['field_label'] );
		}
		if ( isset( $data['display_order'] ) ) {
			$update_args['menu_order'] = (int) $data['display_order'];
		}

		if ( count( $update_args ) > 1 ) {
			$result = wp_update_post( $update_args, true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}

		$meta_map = array(
			'field_name'       => '_dca_field_name',
			'field_icon'       => '_dca_field_icon',
			'field_type'       => '_dca_field_type',
			'required'         => '_dca_field_required',
			'show_in_dashboard'=> '_dca_field_show_dashboard',
			'show_in_public'   => '_dca_field_show_public',
			'show_in_filters'  => '_dca_field_show_filters',
		);

		foreach ( $meta_map as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				$value = ( '_dca_field_name' === $meta_key ) ? sanitize_key( $data[ $key ] ) : $data[ $key ];
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		if ( isset( $data['field_options'] ) ) {
			$opts = $data['field_options'];
			if ( is_string( $opts ) ) {
				$opts = json_decode( $opts, true ) ?: array();
			}
			update_post_meta( $post_id, '_dca_field_options', wp_json_encode( (array) $opts ) );
		}

		return true;
	}

	/**
	 * Delete a single attribute field post.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function delete_field( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return false;
		}
		$result = wp_delete_post( $post_id, true );
		return ( false !== $result && null !== $result );
	}

	/**
	 * Get a single field by post ID.
	 *
	 * @param int $post_id
	 * @return object|null  stdClass with same shape as DCA_Database::get_fields_by_set() row.
	 */
	public function get_field( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || self::FIELD_POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $this->field_to_object( $post );
	}

	/**
	 * Get all fields belonging to a set, ordered by display_order.
	 *
	 * @param int   $set_id  Post ID of the parent set.
	 * @param array $args    Optional. location (dashboard|public|filters).
	 * @return object[]
	 */
	public function get_fields_by_set( $set_id, array $args = array() ) {
		$set_id = (int) $set_id;

		$query_args = array(
			'post_type'      => self::FIELD_POST_TYPE,
			'post_parent'    => $set_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		// Location filter via meta_query.
		if ( ! empty( $args['location'] ) ) {
			$location_meta_map = array(
				'dashboard' => '_dca_field_show_dashboard',
				'public'    => '_dca_field_show_public',
				'filters'   => '_dca_field_show_filters',
			);
			$location = $args['location'];
			if ( isset( $location_meta_map[ $location ] ) ) {
				$query_args['meta_query'] = array(
					array(
						'key'     => $location_meta_map[ $location ],
						'value'   => '1',
						'compare' => '=',
					),
				);
			}
		}

		$posts = get_posts( $query_args );
		return array_map( array( $this, 'field_to_object' ), $posts );
	}

	/**
	 * Convert a WP_Post (dca_attribute_field) to the canonical stdClass shape.
	 *
	 * @param WP_Post $post
	 * @return object
	 */
	public function field_to_object( WP_Post $post ) {
		$obj                   = new stdClass();
		$obj->id               = (int) $post->ID;
		$obj->attribute_set_id = (int) $post->post_parent;
		$obj->field_label      = $post->post_title;
		$obj->field_name       = (string) get_post_meta( $post->ID, '_dca_field_name', true );
		$obj->field_icon       = (string) get_post_meta( $post->ID, '_dca_field_icon', true );
		$obj->field_type       = (string) get_post_meta( $post->ID, '_dca_field_type', true ) ?: 'select';
		$raw_opts              = get_post_meta( $post->ID, '_dca_field_options', true );
		$obj->field_options    = $raw_opts ? ( json_decode( $raw_opts, true ) ?: array() ) : array();
		$obj->required         = (int) get_post_meta( $post->ID, '_dca_field_required', true );
		$obj->display_order    = (int) $post->menu_order;
		$obj->show_in_dashboard = (int) get_post_meta( $post->ID, '_dca_field_show_dashboard', true );
		$obj->show_in_public   = (int) get_post_meta( $post->ID, '_dca_field_show_public', true );
		$obj->show_in_filters  = (int) get_post_meta( $post->ID, '_dca_field_show_filters', true );

		// Normalise defaults for new fields where meta may not be set yet.
		if ( 0 === $obj->show_in_dashboard && '' === get_post_meta( $post->ID, '_dca_field_show_dashboard', true ) ) {
			$obj->show_in_dashboard = 1;
		}
		if ( 0 === $obj->show_in_public && '' === get_post_meta( $post->ID, '_dca_field_show_public', true ) ) {
			$obj->show_in_public = 1;
		}
		if ( 0 === $obj->show_in_filters && '' === get_post_meta( $post->ID, '_dca_field_show_filters', true ) ) {
			$obj->show_in_filters = 1;
		}

		return $obj;
	}
}
