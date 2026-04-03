<?php
/**
 * EcomCine Person Category Registry
 *
 * Owns the concept of "person categories" (Actor, Model, TV Host, …) using
 * two custom DB tables instead of taxonomies so there is zero dependency on
 * Dokan's store_category taxonomy or WordPress term infrastructure.
 *
 * Tables created via install() on plugin activation:
 *   {$wpdb->prefix}ecomcine_categories         — category definitions
 *   {$wpdb->prefix}ecomcine_person_categories  — person ↔ category join
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Person_Category_Registry {

	const DB_VERSION_KEY = 'ecomcine_category_db_version';
	const DB_VERSION     = '2';

	// ── Schema ──────────────────────────────────────────────────────────────

	/**
	 * Create/update DB tables.  Safe to call on every activation and upgrade.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$table_cats = $wpdb->prefix . 'ecomcine_categories';
		$sql_cats   = "CREATE TABLE {$table_cats} (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name         VARCHAR(200)    NOT NULL DEFAULT '',
			slug         VARCHAR(200)    NOT NULL DEFAULT '',
			description  TEXT            NOT NULL,
			sort_order   INT             NOT NULL DEFAULT 0,
			created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   slug (slug)
		) {$charset};";

		$table_join = $wpdb->prefix . 'ecomcine_person_categories';
		$sql_join   = "CREATE TABLE {$table_join} (
			user_id     BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY (user_id, category_id),
			KEY         category_id (category_id)
		) {$charset};";

		$table_fields = $wpdb->prefix . 'ecomcine_category_fields';
		$sql_fields   = "CREATE TABLE {$table_fields} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id     BIGINT UNSIGNED NOT NULL,
			field_key       VARCHAR(191)    NOT NULL DEFAULT '',
			field_label     VARCHAR(200)    NOT NULL DEFAULT '',
			field_type      VARCHAR(32)     NOT NULL DEFAULT 'select',
			field_options   LONGTEXT        NULL,
			field_icon      VARCHAR(100)    NOT NULL DEFAULT '',
			sort_order      INT             NOT NULL DEFAULT 0,
			required        TINYINT(1)      NOT NULL DEFAULT 0,
			show_in_public  TINYINT(1)      NOT NULL DEFAULT 1,
			show_in_filters TINYINT(1)      NOT NULL DEFAULT 1,
			created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category_id (category_id),
			UNIQUE KEY category_field (category_id, field_key)
		) {$charset};";

		dbDelta( $sql_cats );
		dbDelta( $sql_join );
		dbDelta( $sql_fields );

		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	// ── CRUD for categories ──────────────────────────────────────────────────

	/**
	 * Create a new category.
	 *
	 * @param string $name
	 * @param string $slug
	 * @param string $description
	 * @param int    $sort_order
	 * @return int|false New row ID or false on failure.
	 */
	public static function create( string $name, string $slug, string $description = '', int $sort_order = 0 ) {
		self::ensure_schema();
		global $wpdb;

		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $slug );

		if ( '' === $name || '' === $slug ) {
			return false;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'ecomcine_categories',
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $description ),
				'sort_order'  => (int) $sort_order,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing category.
	 *
	 * @param int   $id
	 * @param array $fields  Associative array of fields to update.
	 * @return bool
	 */
	public static function update( int $id, array $fields ): bool {
		self::ensure_schema();
		global $wpdb;

		if ( ! $id ) {
			return false;
		}

		$allowed = array( 'name', 'slug', 'description', 'sort_order' );
		$data    = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}
			if ( 'sort_order' === $key ) {
				$data[ $key ] = (int) $fields[ $key ];
				$formats[]    = '%d';
			} elseif ( 'slug' === $key ) {
				$data[ $key ] = sanitize_title( $fields[ $key ] );
				$formats[]    = '%s';
			} elseif ( 'description' === $key ) {
				$data[ $key ] = sanitize_textarea_field( $fields[ $key ] );
				$formats[]    = '%s';
			} else {
				$data[ $key ] = sanitize_text_field( $fields[ $key ] );
				$formats[]    = '%s';
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ecomcine_categories',
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a category and remove all person assignments for it.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		self::ensure_schema();
		global $wpdb;

		if ( ! $id ) {
			return false;
		}

		// Remove assignments first.
		$wpdb->delete(
			$wpdb->prefix . 'ecomcine_person_categories',
			array( 'category_id' => $id ),
			array( '%d' )
		);

		$result = $wpdb->delete(
			$wpdb->prefix . 'ecomcine_categories',
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Return all categories ordered by sort_order, then name.
	 *
	 * @return array[]  Each element: { id, name, slug, description, sort_order }
	 */
	public static function get_all(): array {
		self::ensure_schema();
		global $wpdb;

		$table = $wpdb->prefix . 'ecomcine_categories';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, name, slug, description, sort_order FROM {$table} ORDER BY sort_order ASC, name ASC", ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Return a single category by slug.
	 *
	 * @param string $slug
	 * @return array|null
	 */
	public static function get_by_slug( string $slug ): ?array {
		self::ensure_schema();
		global $wpdb;

		$slug  = sanitize_title( $slug );
		$table = $wpdb->prefix . 'ecomcine_categories';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, name, slug, description, sort_order FROM {$table} WHERE slug = %s LIMIT 1", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return a single category by ID.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public static function get_category( int $id ): ?array {
		self::ensure_schema();
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$table = $wpdb->prefix . 'ecomcine_categories';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, name, slug, description, sort_order FROM {$table} WHERE id = %d LIMIT 1", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	// ── CRUD for category fields ─────────────────────────────────────────────

	/**
	 * Get all custom fields for a category.
	 *
	 * @param int  $category_id
	 * @param bool $public_only
	 * @return array[]
	 */
	public static function get_fields_for_category( int $category_id, bool $public_only = false ): array {
		self::ensure_schema();
		global $wpdb;

		if ( $category_id < 1 ) {
			return array();
		}

		$table = $wpdb->prefix . 'ecomcine_category_fields';
		$where = $public_only ? 'AND show_in_public = 1' : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, category_id, field_key, field_label, field_type, field_options, field_icon, sort_order, required, show_in_public, show_in_filters
				 FROM {$table}
				 WHERE category_id = %d {$where}
				 ORDER BY sort_order ASC, id ASC",
				$category_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['options_map'] = self::decode_field_options( $row['field_options'] ?? '' );
		}

		return $rows;
	}

	/**
	 * Return one custom field by ID.
	 *
	 * @param int $field_id
	 * @return array|null
	 */
	public static function get_field( int $field_id ): ?array {
		self::ensure_schema();
		global $wpdb;

		if ( $field_id < 1 ) {
			return null;
		}

		$table = $wpdb->prefix . 'ecomcine_category_fields';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, category_id, field_key, field_label, field_type, field_options, field_icon, sort_order, required, show_in_public, show_in_filters
				 FROM {$table}
				 WHERE id = %d LIMIT 1",
				$field_id
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['options_map'] = self::decode_field_options( $row['field_options'] ?? '' );
		return $row;
	}

	/**
	 * Create a category custom field.
	 *
	 * @param int   $category_id
	 * @param array $data
	 * @return int|false
	 */
	public static function create_field( int $category_id, array $data ) {
		self::ensure_schema();
		global $wpdb;

		if ( $category_id < 1 || ! self::get_category( $category_id ) ) {
			return false;
		}

		$field_key = sanitize_key( $data['field_key'] ?? '' );
		$field_label = sanitize_text_field( $data['field_label'] ?? '' );
		$field_type  = self::sanitize_field_type( $data['field_type'] ?? 'select' );
		$field_icon  = sanitize_text_field( $data['field_icon'] ?? '' );
		$sort_order  = (int) ( $data['sort_order'] ?? 0 );
		$required    = empty( $data['required'] ) ? 0 : 1;
		$show_public = array_key_exists( 'show_in_public', $data ) ? ( empty( $data['show_in_public'] ) ? 0 : 1 ) : 1;
		$show_filter = array_key_exists( 'show_in_filters', $data ) ? ( empty( $data['show_in_filters'] ) ? 0 : 1 ) : 1;

		if ( '' === $field_key || '' === $field_label ) {
			return false;
		}

		$field_options = self::encode_field_options( $data['field_options'] ?? '' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ecomcine_category_fields',
			array(
				'category_id'     => $category_id,
				'field_key'       => $field_key,
				'field_label'     => $field_label,
				'field_type'      => $field_type,
				'field_options'   => $field_options,
				'field_icon'      => $field_icon,
				'sort_order'      => $sort_order,
				'required'        => $required,
				'show_in_public'  => $show_public,
				'show_in_filters' => $show_filter,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update category custom field.
	 *
	 * @param int   $field_id
	 * @param array $data
	 * @return bool
	 */
	public static function update_field( int $field_id, array $data ): bool {
		self::ensure_schema();
		global $wpdb;

		if ( $field_id < 1 ) {
			return false;
		}

		$allowed = array(
			'field_key', 'field_label', 'field_type', 'field_options', 'field_icon',
			'sort_order', 'required', 'show_in_public', 'show_in_filters',
		);

		$payload = array();
		$formats = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			switch ( $key ) {
				case 'field_key':
					$payload[ $key ] = sanitize_key( (string) $data[ $key ] );
					$formats[] = '%s';
					break;
				case 'field_label':
				case 'field_icon':
					$payload[ $key ] = sanitize_text_field( (string) $data[ $key ] );
					$formats[] = '%s';
					break;
				case 'field_type':
					$payload[ $key ] = self::sanitize_field_type( (string) $data[ $key ] );
					$formats[] = '%s';
					break;
				case 'field_options':
					$payload[ $key ] = self::encode_field_options( $data[ $key ] );
					$formats[] = '%s';
					break;
				default:
					$payload[ $key ] = empty( $data[ $key ] ) ? 0 : 1;
					$formats[] = '%d';
					if ( 'sort_order' === $key ) {
						$payload[ $key ] = (int) $data[ $key ];
					}
					break;
			}
		}

		if ( empty( $payload ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'ecomcine_category_fields',
			$payload,
			array( 'id' => $field_id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete custom field.
	 *
	 * @param int $field_id
	 * @return bool
	 */
	public static function delete_field( int $field_id ): bool {
		self::ensure_schema();
		global $wpdb;

		if ( $field_id < 1 ) {
			return false;
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'ecomcine_category_fields',
			array( 'id' => $field_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get all public custom fields for a person, grouped by category slug.
	 *
	 * @param int $user_id
	 * @return array<string,array>
	 */
	public static function get_public_fields_for_person( int $user_id ): array {
		$categories = self::get_for_person( $user_id );
		if ( empty( $categories ) ) {
			return array();
		}

		$grouped = array();
		foreach ( $categories as $category ) {
			$cat_id = (int) ( $category['id'] ?? 0 );
			$slug   = (string) ( $category['slug'] ?? '' );
			if ( $cat_id < 1 || '' === $slug ) {
				continue;
			}
			$grouped[ $slug ] = self::get_fields_for_category( $cat_id, true );
		}

		return $grouped;
	}

	/**
	 * Recover cameraman category and fields from legacy Dokan Category Attributes.
	 *
	 * @return array{category_id:int,fields:int,source:string}
	 */
	public static function recover_cameraman_from_legacy(): array {
		self::ensure_schema();
		$category = self::get_by_slug( 'cameraman' );
		if ( ! $category ) {
			$new_id   = self::create( 'Cameraman', 'cameraman', 'Recovered from legacy Dokan attributes.', 11 );
			$category = $new_id ? self::get_category( (int) $new_id ) : null;
		}

		if ( ! $category ) {
			return array( 'category_id' => 0, 'fields' => 0, 'source' => 'none' );
		}

		$category_id = (int) $category['id'];
		$fields      = self::read_legacy_cameraman_fields_from_dca();
		$source      = 'dca';

		if ( empty( $fields ) ) {
			$fields = self::read_legacy_cameraman_fields_from_runtime();
			$source = empty( $fields ) ? 'none' : 'runtime';
		}

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'ecomcine_category_fields', array( 'category_id' => $category_id ), array( '%d' ) );

		$created = 0;
		foreach ( $fields as $field ) {
			$ok = self::create_field( $category_id, $field );
			if ( $ok ) {
				$created++;
			}
		}

		return array(
			'category_id' => $category_id,
			'fields'      => $created,
			'source'      => $source,
		);
	}

	// ── Person ↔ category assignments ────────────────────────────────────────

	/**
	 * Assign a person to a set of category IDs (replaces existing assignments).
	 *
	 * @param int   $user_id
	 * @param int[] $category_ids
	 * @return bool
	 */
	public static function set_person_categories( int $user_id, array $category_ids ): bool {
		self::ensure_schema();
		global $wpdb;

		if ( ! $user_id ) {
			return false;
		}

		$join_table = $wpdb->prefix . 'ecomcine_person_categories';

		// Remove existing.
		$wpdb->delete( $join_table, array( 'user_id' => $user_id ), array( '%d' ) );

		// Insert new.
		foreach ( array_unique( array_map( 'intval', $category_ids ) ) as $cat_id ) {
			if ( $cat_id < 1 ) {
				continue;
			}
			$wpdb->insert(
				$join_table,
				array( 'user_id' => $user_id, 'category_id' => $cat_id ),
				array( '%d', '%d' )
			);
		}

		return true;
	}

	/**
	 * Set person categories by slug array.
	 *
	 * @param int      $user_id
	 * @param string[] $slugs
	 * @return bool
	 */
	public static function set_person_categories_by_slug( int $user_id, array $slugs ): bool {
		$cat_ids = array();
		foreach ( $slugs as $slug ) {
			$cat = self::get_by_slug( (string) $slug );
			if ( $cat ) {
				$cat_ids[] = (int) $cat['id'];
			}
		}
		return self::set_person_categories( $user_id, $cat_ids );
	}

	/**
	 * Return all category rows assigned to a person.
	 *
	 * @param int $user_id
	 * @return array[]  Each element: { id, name, slug, description, sort_order }
	 */
	public static function get_for_person( int $user_id ): array {
		self::ensure_schema();
		global $wpdb;

		if ( ! $user_id ) {
			return array();
		}

		$cats = $wpdb->prefix . 'ecomcine_categories';
		$join = $wpdb->prefix . 'ecomcine_person_categories';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name, c.slug, c.description, c.sort_order
				 FROM {$cats} c
				 INNER JOIN {$join} j ON j.category_id = c.id
				 WHERE j.user_id = %d
				 ORDER BY c.sort_order ASC, c.name ASC",
				$user_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Return all user IDs assigned to a category slug.
	 *
	 * Used by the store-lists filter to restrict the vendor listing.
	 *
	 * @param string $slug
	 * @return int[]
	 */
	public static function get_person_ids_for_slug( string $slug ): array {
		self::ensure_schema();
		global $wpdb;

		$cat = self::get_by_slug( $slug );
		if ( ! $cat ) {
			return array();
		}

		$join = $wpdb->prefix . 'ecomcine_person_categories';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT user_id FROM {$join} WHERE category_id = %d", (int) $cat['id'] )
		);

		return array_map( 'intval', (array) $ids );
	}

	// ── Seed default categories ───────────────────────────────────────────────

	/**
	 * Insert the default EcomCine talent categories if the table is empty.
	 *
	 * Called from ecomcine.php on activation so a fresh install has something
	 * to show.  Each category maps to a legacy Dokan store_category slug.
	 */
	public static function seed_defaults(): void {
		self::ensure_schema();
		global $wpdb;

		$table = $wpdb->prefix . 'ecomcine_categories';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count > 0 ) {
			return; // Already seeded.
		}

		$defaults = array(
			array( 'Actor',             'actor',              '', 1 ),
			array( 'Model',             'model',              '', 2 ),
			array( 'TV Host',           'tv-host',            '', 3 ),
			array( 'Athlete',           'athlete',            '', 4 ),
			array( 'Musician',          'musician',           '', 5 ),
			array( 'Director',          'director',           '', 6 ),
			array( 'Photographer',      'photographer',       '', 7 ),
			array( 'Voiceover Artist',  'voiceover-artist',   '', 8 ),
			array( 'Stunt Performer',   'stunt-performer',    '', 9 ),
			array( 'Production Crew',   'production-crew',    '', 10 ),
		);

		foreach ( $defaults as [ $name, $slug, $desc, $order ] ) {
			self::create( $name, $slug, $desc, $order );
		}
	}

	// ── Legacy Dokan taxonomy sync ────────────────────────────────────────────

	/**
	 * One-time migration: read existing store_category term assignments for all
	 * sellers and populate the EcomCine tables.
	 *
	 * Safe to call multiple times; existing assignments are replaced.
	 *
	 * @return int Number of persons migrated.
	 */
	public static function migrate_from_store_category(): int {
		self::ensure_schema();
		if ( ! taxonomy_exists( 'store_category' ) ) {
			return 0;
		}

		$migrated = 0;
		$users    = get_users( array( 'role' => 'seller', 'number' => -1, 'fields' => array( 'ID' ) ) );

		foreach ( $users as $user ) {
			$uid   = (int) ( is_object( $user ) ? $user->ID : $user );
			$terms = wp_get_object_terms( $uid, 'store_category', array( 'fields' => 'slugs' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$cat_ids = array();
			foreach ( $terms as $term_slug ) {
				if ( 'uncategorized' === $term_slug ) {
					continue;
				}
				$cat = self::get_by_slug( $term_slug );
				if ( $cat ) {
					$cat_ids[] = (int) $cat['id'];
				}
			}

			if ( ! empty( $cat_ids ) ) {
				self::set_person_categories( $uid, $cat_ids );
				$migrated++;
			}
		}

		return $migrated;
	}

	/**
	 * Sanitize field type.
	 *
	 * @param string $type
	 * @return string
	 */
	private static function sanitize_field_type( string $type ): string {
		$allowed = array( 'select', 'text', 'textarea', 'number', 'radio', 'checkbox' );
		$type    = sanitize_key( $type );
		return in_array( $type, $allowed, true ) ? $type : 'select';
	}

	/**
	 * Encode field options as normalized JSON map.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	private static function encode_field_options( $raw ): string {
		if ( is_array( $raw ) ) {
			$map = array();
			foreach ( $raw as $key => $value ) {
				$clean_key   = sanitize_key( (string) $key );
				$clean_label = sanitize_text_field( (string) $value );
				if ( '' === $clean_key || '' === $clean_label ) {
					continue;
				}
				$map[ $clean_key ] = $clean_label;
			}
			return wp_json_encode( $map );
		}

		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return wp_json_encode( array() );
		}

		$map = array();
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, ':' ) ) {
				list( $value, $label ) = array_map( 'trim', explode( ':', $line, 2 ) );
			} else {
				$value = $line;
				$label = $line;
			}
			$clean_value = sanitize_key( (string) $value );
			$clean_label = sanitize_text_field( (string) $label );
			if ( '' === $clean_value || '' === $clean_label ) {
				continue;
			}
			$map[ $clean_value ] = $clean_label;
		}

		return wp_json_encode( $map );
	}

	/**
	 * Decode JSON options map.
	 *
	 * @param string $json
	 * @return array<string,string>
	 */
	private static function decode_field_options( string $json ): array {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$map = array();
		foreach ( $decoded as $value => $label ) {
			$clean_value = sanitize_key( (string) $value );
			$clean_label = sanitize_text_field( (string) $label );
			if ( '' === $clean_value || '' === $clean_label ) {
				continue;
			}
			$map[ $clean_value ] = $clean_label;
		}

		return $map;
	}

	/**
	 * Read legacy cameraman field definitions from DCA CPT storage.
	 *
	 * @return array<int,array>
	 */
	private static function read_legacy_cameraman_fields_from_dca(): array {
		$sets = get_posts(
			array(
				'post_type'      => 'dca_attribute_set',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_dca_set_categories',
						'value'   => 'cameraman',
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( empty( $sets ) ) {
			return array();
		}

		$set = $sets[0];
		$fields = get_posts(
			array(
				'post_type'      => 'dca_attribute_field',
				'post_parent'    => (int) $set->ID,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$results = array();
		foreach ( $fields as $index => $field_post ) {
			$field_key = sanitize_key( (string) get_post_meta( $field_post->ID, '_dca_field_name', true ) );
			if ( '' === $field_key ) {
				continue;
			}

			$options_json = (string) get_post_meta( $field_post->ID, '_dca_field_options', true );
			$options_arr  = json_decode( $options_json, true );
			if ( ! is_array( $options_arr ) ) {
				$options_arr = array();
			}

			$results[] = array(
				'field_key'       => $field_key,
				'field_label'     => sanitize_text_field( $field_post->post_title ),
				'field_type'      => self::sanitize_field_type( (string) get_post_meta( $field_post->ID, '_dca_field_type', true ) ),
				'field_options'   => $options_arr,
				'field_icon'      => sanitize_text_field( (string) get_post_meta( $field_post->ID, '_dca_field_icon', true ) ),
				'sort_order'      => (int) $index,
				'required'        => (int) get_post_meta( $field_post->ID, '_dca_field_required', true ) ? 1 : 0,
				'show_in_public'  => (int) get_post_meta( $field_post->ID, '_dca_field_show_public', true ) ? 1 : 0,
				'show_in_filters' => (int) get_post_meta( $field_post->ID, '_dca_field_show_filters', true ) ? 1 : 0,
			);
		}

		return $results;
	}

	/**
	 * Fallback: read legacy cameraman options from runtime helper.
	 *
	 * @return array<int,array>
	 */
	private static function read_legacy_cameraman_fields_from_runtime(): array {
		if ( ! function_exists( 'get_cameraman_filter_options' ) ) {
			return array();
		}

		$options = get_cameraman_filter_options();
		if ( ! is_array( $options ) ) {
			return array();
		}

		$results = array();
		$order   = 0;
		foreach ( $options as $field_key => $map ) {
			$results[] = array(
				'field_key'       => sanitize_key( (string) $field_key ),
				'field_label'     => ucwords( str_replace( '_', ' ', (string) $field_key ) ),
				'field_type'      => 'select',
				'field_options'   => is_array( $map ) ? $map : array(),
				'field_icon'      => '',
				'sort_order'      => $order,
				'required'        => 0,
				'show_in_public'  => 1,
				'show_in_filters' => 1,
			);
			$order++;
		}

		return $results;
	}

	/**
	 * Ensure DB schema is up to date on long-lived installs.
	 */
	private static function ensure_schema(): void {
		$current = (string) get_option( self::DB_VERSION_KEY, '0' );
		if ( self::DB_VERSION !== $current ) {
			self::install();
		}
	}
}
