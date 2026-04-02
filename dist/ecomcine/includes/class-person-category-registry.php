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
	const DB_VERSION     = '1';

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

		dbDelta( $sql_cats );
		dbDelta( $sql_join );

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
		global $wpdb;

		$slug  = sanitize_title( $slug );
		$table = $wpdb->prefix . 'ecomcine_categories';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, name, slug, description, sort_order FROM {$table} WHERE slug = %s LIMIT 1", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
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
}
