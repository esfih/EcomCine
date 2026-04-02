<?php
/**
 * EcomCine Admin — Person Categories Tab
 *
 * Renders the "Talent Categories" tab on the EcomCine Settings page and handles
 * the create / update / delete admin-post actions.
 *
 * Accessed at: wp-admin/admin.php?page=ecomcine-settings&tab=categories
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Admin_Categories_Tab {

	/**
	 * Register admin_post hooks for CRUD actions.
	 */
	public static function init(): void {
		add_action( 'admin_post_ecomcine_category_create', array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_ecomcine_category_update', array( __CLASS__, 'handle_update' ) );
		add_action( 'admin_post_ecomcine_category_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_ecomcine_category_migrate', array( __CLASS__, 'handle_migrate' ) );
		add_action( 'admin_post_ecomcine_category_field_create', array( __CLASS__, 'handle_field_create' ) );
		add_action( 'admin_post_ecomcine_category_field_update', array( __CLASS__, 'handle_field_update' ) );
		add_action( 'admin_post_ecomcine_category_field_delete', array( __CLASS__, 'handle_field_delete' ) );
		add_action( 'admin_post_ecomcine_category_recover_cameraman', array( __CLASS__, 'handle_recover_cameraman' ) );
	}

	// ── Action handlers ──────────────────────────────────────────────────────

	public static function handle_create(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_create' );

		$name        = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
		$slug        = sanitize_title( wp_unslash( $_POST['cat_slug'] ?? $name ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ?? '' ) );
		$sort_order  = absint( $_POST['cat_sort_order'] ?? 0 );

		$error = '';
		if ( '' === $name ) {
			$error = 'name_required';
		} elseif ( '' === $slug ) {
			$error = 'slug_invalid';
		} else {
			$result = EcomCine_Person_Category_Registry::create( $name, $slug, $description, $sort_order );
			if ( ! $result ) {
				$error = 'create_failed';
			}
		}

		wp_safe_redirect( self::_tab_url( $error ? array( 'cat_error' => $error ) : array( 'cat_created' => 1 ) ) );
		exit;
	}

	public static function handle_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_update' );

		$id          = absint( $_POST['cat_id'] ?? 0 );
		$name        = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
		$slug        = sanitize_title( wp_unslash( $_POST['cat_slug'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ?? '' ) );
		$sort_order  = absint( $_POST['cat_sort_order'] ?? 0 );

		$error = '';
		if ( ! $id ) {
			$error = 'invalid_id';
		} elseif ( '' === $name ) {
			$error = 'name_required';
		} else {
			$ok = EcomCine_Person_Category_Registry::update( $id, array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'sort_order'  => $sort_order,
			) );
			if ( ! $ok ) {
				$error = 'update_failed';
			}
		}

		wp_safe_redirect( self::_tab_url( $error ? array( 'cat_error' => $error ) : array( 'cat_updated' => 1 ) ) );
		exit;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_delete' );

		$id    = absint( $_POST['cat_id'] ?? 0 );
		$error = '';
		if ( ! $id ) {
			$error = 'invalid_id';
		} else {
			$ok = EcomCine_Person_Category_Registry::delete( $id );
			if ( ! $ok ) {
				$error = 'delete_failed';
			}
		}

		wp_safe_redirect( self::_tab_url( $error ? array( 'cat_error' => $error ) : array( 'cat_deleted' => 1 ) ) );
		exit;
	}

	public static function handle_migrate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_migrate' );

		$migrated = EcomCine_Person_Category_Registry::migrate_from_store_category();
		wp_safe_redirect( self::_tab_url( array( 'cat_migrated' => $migrated ) ) );
		exit;
	}

	public static function handle_field_create(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_field_create' );

		$category_id = absint( $_POST['cat_id'] ?? 0 );
		$field_key   = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		$field_label = sanitize_text_field( wp_unslash( $_POST['field_label'] ?? '' ) );
		$field_type  = sanitize_key( wp_unslash( $_POST['field_type'] ?? 'select' ) );
		$field_icon  = sanitize_text_field( wp_unslash( $_POST['field_icon'] ?? '' ) );
		$options_raw = wp_unslash( $_POST['field_options'] ?? '' );
		$sort_order  = absint( $_POST['field_sort_order'] ?? 0 );
		$required    = isset( $_POST['field_required'] ) ? 1 : 0;
		$show_public = isset( $_POST['field_show_in_public'] ) ? 1 : 0;
		$show_filter = isset( $_POST['field_show_in_filters'] ) ? 1 : 0;

		$error = '';
		if ( $category_id < 1 ) {
			$error = 'invalid_id';
		} elseif ( '' === $field_key || '' === $field_label ) {
			$error = 'field_required';
		} else {
			$ok = EcomCine_Person_Category_Registry::create_field(
				$category_id,
				array(
					'field_key'       => $field_key,
					'field_label'     => $field_label,
					'field_type'      => $field_type,
					'field_icon'      => $field_icon,
					'field_options'   => self::normalize_options_text( $options_raw ),
					'sort_order'      => $sort_order,
					'required'        => $required,
					'show_in_public'  => $show_public,
					'show_in_filters' => $show_filter,
				)
			);
			if ( ! $ok ) {
				$error = 'field_create_failed';
			}
		}

		$args = array( 'fields_cat' => $category_id );
		if ( '' !== $error ) {
			$args['cat_error'] = $error;
		} else {
			$args['field_created'] = 1;
		}

		wp_safe_redirect( self::_tab_url( $args ) );
		exit;
	}

	public static function handle_field_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_field_update' );

		$field_id    = absint( $_POST['field_id'] ?? 0 );
		$category_id = absint( $_POST['cat_id'] ?? 0 );
		$field_key   = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		$field_label = sanitize_text_field( wp_unslash( $_POST['field_label'] ?? '' ) );
		$field_type  = sanitize_key( wp_unslash( $_POST['field_type'] ?? 'select' ) );
		$field_icon  = sanitize_text_field( wp_unslash( $_POST['field_icon'] ?? '' ) );
		$options_raw = wp_unslash( $_POST['field_options'] ?? '' );
		$sort_order  = absint( $_POST['field_sort_order'] ?? 0 );
		$required    = isset( $_POST['field_required'] ) ? 1 : 0;
		$show_public = isset( $_POST['field_show_in_public'] ) ? 1 : 0;
		$show_filter = isset( $_POST['field_show_in_filters'] ) ? 1 : 0;

		$error = '';
		if ( $field_id < 1 || $category_id < 1 ) {
			$error = 'invalid_id';
		} elseif ( '' === $field_key || '' === $field_label ) {
			$error = 'field_required';
		} else {
			$ok = EcomCine_Person_Category_Registry::update_field(
				$field_id,
				array(
					'field_key'       => $field_key,
					'field_label'     => $field_label,
					'field_type'      => $field_type,
					'field_icon'      => $field_icon,
					'field_options'   => self::normalize_options_text( $options_raw ),
					'sort_order'      => $sort_order,
					'required'        => $required,
					'show_in_public'  => $show_public,
					'show_in_filters' => $show_filter,
				)
			);
			if ( ! $ok ) {
				$error = 'field_update_failed';
			}
		}

		$args = array( 'fields_cat' => $category_id );
		if ( '' !== $error ) {
			$args['cat_error'] = $error;
		} else {
			$args['field_updated'] = 1;
		}

		wp_safe_redirect( self::_tab_url( $args ) );
		exit;
	}

	public static function handle_field_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_field_delete' );

		$field_id    = absint( $_POST['field_id'] ?? 0 );
		$category_id = absint( $_POST['cat_id'] ?? 0 );

		$error = '';
		if ( $field_id < 1 || $category_id < 1 ) {
			$error = 'invalid_id';
		} elseif ( ! EcomCine_Person_Category_Registry::delete_field( $field_id ) ) {
			$error = 'field_delete_failed';
		}

		$args = array( 'fields_cat' => $category_id );
		if ( '' !== $error ) {
			$args['cat_error'] = $error;
		} else {
			$args['field_deleted'] = 1;
		}

		wp_safe_redirect( self::_tab_url( $args ) );
		exit;
	}

	public static function handle_recover_cameraman(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ecomcine' ) );
		}
		check_admin_referer( 'ecomcine_category_recover_cameraman' );

		$result = EcomCine_Person_Category_Registry::recover_cameraman_from_legacy();
		wp_safe_redirect(
			self::_tab_url(
				array(
					'cat_recovered' => 1,
					'cat_source'    => sanitize_key( (string) ( $result['source'] ?? 'none' ) ),
					'cat_fields'    => absint( $result['fields'] ?? 0 ),
					'fields_cat'    => absint( $result['category_id'] ?? 0 ),
				)
			)
		);
		exit;
	}

	// ── Render ───────────────────────────────────────────────────────────────

	/**
	 * Render the full categories tab HTML.
	 * Called from EcomCine_Admin_Settings::render_settings_page() when tab = 'categories'.
	 */
	public static function render(): void {
		if ( ! class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			echo '<p>' . esc_html__( 'EcomCine_Person_Category_Registry not loaded.', 'ecomcine' ) . '</p>';
			return;
		}

		$categories = EcomCine_Person_Category_Registry::get_all();
		$edit_id    = isset( $_GET['edit_cat'] ) ? absint( $_GET['edit_cat'] ) : 0;
		$fields_cat = isset( $_GET['fields_cat'] ) ? absint( $_GET['fields_cat'] ) : 0;
		$edit_field = isset( $_GET['edit_field'] ) ? absint( $_GET['edit_field'] ) : 0;
		$edit_row   = null;
		$edit_field_row = null;
		if ( $edit_id ) {
			foreach ( $categories as $cat ) {
				if ( (int) $cat['id'] === $edit_id ) {
					$edit_row = $cat;
					break;
				}
			}
		}
		if ( $fields_cat > 0 && $edit_field > 0 ) {
			$edit_field_row = EcomCine_Person_Category_Registry::get_field( $edit_field );
			if ( ! $edit_field_row || (int) $edit_field_row['category_id'] !== $fields_cat ) {
				$edit_field_row = null;
			}
		}

		// Status notices.
		if ( isset( $_GET['cat_created'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category created.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['cat_updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category updated.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['cat_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Category deleted.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['cat_migrated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html( sprintf( __( 'Migrated %d persons from Dokan store_category.', 'ecomcine' ), absint( $_GET['cat_migrated'] ) ) )
				. '</p></div>';
		}
		if ( isset( $_GET['cat_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html( sprintf( __( 'Error: %s', 'ecomcine' ), sanitize_key( wp_unslash( $_GET['cat_error'] ) ) ) )
				. '</p></div>';
		}
		if ( isset( $_GET['field_created'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field created.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['field_updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field updated.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['field_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom field deleted.', 'ecomcine' ) . '</p></div>';
		}
		if ( isset( $_GET['cat_recovered'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html(
					sprintf(
						/* translators: 1: number of fields 2: source */
						__( 'Recovered Cameraman category with %1$d fields (source: %2$s).', 'ecomcine' ),
						absint( $_GET['cat_fields'] ?? 0 ),
						sanitize_key( wp_unslash( $_GET['cat_source'] ?? 'unknown' ) )
					)
				)
				. '</p></div>';
		}
		?>
		<div style="max-width:960px;margin-top:20px;">

			<h2 style="font-size:16px;"><?php esc_html_e( 'Person Categories', 'ecomcine' ); ?></h2>
			<p><?php esc_html_e( 'Manage the categories that appear on the talent listing and profile pages. These are owned by EcomCine and work without Dokan.', 'ecomcine' ); ?></p>

			<?php if ( ! empty( $categories ) ) : ?>
			<table class="widefat striped" style="margin-bottom:24px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Description', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Order', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ecomcine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $cat ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $cat['name'] ); ?></strong></td>
						<td><code><?php echo esc_html( $cat['slug'] ); ?></code></td>
						<td><?php echo esc_html( $cat['description'] ); ?></td>
						<td><?php echo esc_html( $cat['sort_order'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( self::_tab_url( array( 'edit_cat' => $cat['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'ecomcine' ); ?></a>
							&nbsp;|&nbsp;
							<a href="<?php echo esc_url( self::_tab_url( array( 'fields_cat' => $cat['id'] ) ) ); ?>"><?php esc_html_e( 'Fields', 'ecomcine' ); ?></a>
							&nbsp;|&nbsp;
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'ecomcine_category_delete' ); ?>
								<input type="hidden" name="action" value="ecomcine_category_delete" />
								<input type="hidden" name="cat_id" value="<?php echo esc_attr( $cat['id'] ); ?>" />
								<button type="submit" class="button-link" style="color:#d63638;"
									onclick="return confirm('<?php esc_attr_e( 'Delete this category and all person assignments?', 'ecomcine' ); ?>');">
									<?php esc_html_e( 'Delete', 'ecomcine' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No categories yet. Add one below.', 'ecomcine' ); ?></p>
			<?php endif; ?>

			<?php /* Edit form (shown when ?edit_cat=N is in URL) */ ?>
			<?php if ( $edit_row ) : ?>
			<h3><?php esc_html_e( 'Edit Category', 'ecomcine' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:500px;">
				<?php wp_nonce_field( 'ecomcine_category_update' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_update" />
				<input type="hidden" name="cat_id" value="<?php echo esc_attr( $edit_row['id'] ); ?>" />
				<?php self::_render_form_fields( $edit_row ); ?>
				<?php submit_button( __( 'Update Category', 'ecomcine' ), 'primary', 'submit', false ); ?>
				&nbsp;<a href="<?php echo esc_url( self::_tab_url() ); ?>"><?php esc_html_e( 'Cancel', 'ecomcine' ); ?></a>
			</form>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Add New Category', 'ecomcine' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:500px;">
				<?php wp_nonce_field( 'ecomcine_category_create' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_create" />
				<?php self::_render_form_fields(); ?>
				<?php submit_button( __( 'Add Category', 'ecomcine' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( taxonomy_exists( 'store_category' ) ) : ?>
			<hr style="margin:32px 0 16px;">
			<h3><?php esc_html_e( 'Migrate from Dokan', 'ecomcine' ); ?></h3>
			<p><?php esc_html_e( 'Copy existing Dokan store_category term assignments for all sellers into the EcomCine category tables. Existing EcomCine assignments are replaced. Safe to run more than once.', 'ecomcine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ecomcine_category_migrate' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_migrate" />
				<?php submit_button( __( 'Run Migration', 'ecomcine' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php endif; ?>

			<hr style="margin:32px 0 16px;">
			<h3><?php esc_html_e( 'Recover Legacy Cameraman Fields', 'ecomcine' ); ?></h3>
			<p><?php esc_html_e( 'Import Cameraman category field definitions from legacy Dokan Category Attributes storage into EcomCine.', 'ecomcine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ecomcine_category_recover_cameraman' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_recover_cameraman" />
				<?php submit_button( __( 'Recover Cameraman + Fields', 'ecomcine' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php
			$manage_category = $fields_cat > 0 ? EcomCine_Person_Category_Registry::get_category( $fields_cat ) : null;
			if ( $manage_category ) :
				$fields = EcomCine_Person_Category_Registry::get_fields_for_category( $fields_cat );
			?>
			<hr style="margin:32px 0 16px;">
			<h3><?php echo esc_html( sprintf( __( 'Custom Fields: %s', 'ecomcine' ), $manage_category['name'] ) ); ?></h3>
			<p><?php esc_html_e( 'Add dropdowns or other inputs for this category. For options, use one per line (value:Label or just value).', 'ecomcine' ); ?></p>

			<?php if ( ! empty( $fields ) ) : ?>
			<table class="widefat striped" style="margin-bottom:16px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Label', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Type', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Order', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Public', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Filters', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'ecomcine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
					<tr>
						<td><code><?php echo esc_html( $field['field_key'] ); ?></code></td>
						<td><?php echo esc_html( $field['field_label'] ); ?></td>
						<td><?php echo esc_html( $field['field_type'] ); ?></td>
						<td><?php echo esc_html( (string) $field['sort_order'] ); ?></td>
						<td><?php echo ! empty( $field['show_in_public'] ) ? 'yes' : 'no'; ?></td>
						<td><?php echo ! empty( $field['show_in_filters'] ) ? 'yes' : 'no'; ?></td>
						<td>
							<a href="<?php echo esc_url( self::_tab_url( array( 'fields_cat' => $fields_cat, 'edit_field' => $field['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'ecomcine' ); ?></a>
							&nbsp;|&nbsp;
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'ecomcine_category_field_delete' ); ?>
								<input type="hidden" name="action" value="ecomcine_category_field_delete" />
								<input type="hidden" name="cat_id" value="<?php echo esc_attr( $fields_cat ); ?>" />
								<input type="hidden" name="field_id" value="<?php echo esc_attr( $field['id'] ); ?>" />
								<button type="submit" class="button-link" style="color:#d63638;" onclick="return confirm('<?php esc_attr_e( 'Delete this custom field?', 'ecomcine' ); ?>');"><?php esc_html_e( 'Delete', 'ecomcine' ); ?></button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>

			<?php if ( $edit_field_row ) : ?>
			<h4><?php esc_html_e( 'Edit Field', 'ecomcine' ); ?></h4>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;">
				<?php wp_nonce_field( 'ecomcine_category_field_update' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_field_update" />
				<input type="hidden" name="cat_id" value="<?php echo esc_attr( $fields_cat ); ?>" />
				<input type="hidden" name="field_id" value="<?php echo esc_attr( $edit_field_row['id'] ); ?>" />
				<?php self::_render_field_form_fields( $edit_field_row ); ?>
				<?php submit_button( __( 'Update Field', 'ecomcine' ), 'primary', 'submit', false ); ?>
				&nbsp;<a href="<?php echo esc_url( self::_tab_url( array( 'fields_cat' => $fields_cat ) ) ); ?>"><?php esc_html_e( 'Cancel', 'ecomcine' ); ?></a>
			</form>
			<?php endif; ?>

			<h4><?php esc_html_e( 'Add New Field', 'ecomcine' ); ?></h4>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;">
				<?php wp_nonce_field( 'ecomcine_category_field_create' ); ?>
				<input type="hidden" name="action" value="ecomcine_category_field_create" />
				<input type="hidden" name="cat_id" value="<?php echo esc_attr( $fields_cat ); ?>" />
				<?php self::_render_field_form_fields(); ?>
				<?php submit_button( __( 'Add Field', 'ecomcine' ), 'primary', 'submit', false ); ?>
			</form>
			<?php endif; ?>

		</div>
		<?php
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Render the shared name/slug/description/sort_order form fields.
	 *
	 * @param array|null $row  Existing row data for editing; null for new.
	 */
	private static function _render_form_fields( ?array $row = null ): void {
		$name        = isset( $row['name'] )        ? esc_attr( $row['name'] )        : '';
		$slug        = isset( $row['slug'] )        ? esc_attr( $row['slug'] )        : '';
		$description = isset( $row['description'] ) ? esc_attr( $row['description'] ) : '';
		$sort_order  = isset( $row['sort_order'] )  ? (int) $row['sort_order']        : 0;
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cat_name_field"><?php esc_html_e( 'Name', 'ecomcine' ); ?> <span style="color:red;">*</span></label></th>
				<td><input id="cat_name_field" type="text" name="cat_name" value="<?php echo $name; ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="cat_slug_field"><?php esc_html_e( 'Slug', 'ecomcine' ); ?></label></th>
				<td>
					<input id="cat_slug_field" type="text" name="cat_slug" value="<?php echo $slug; ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'URL-safe identifier. Auto-generated from name if left blank.', 'ecomcine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cat_desc_field"><?php esc_html_e( 'Description', 'ecomcine' ); ?></label></th>
				<td><textarea id="cat_desc_field" name="cat_description" rows="3" class="large-text"><?php echo $description; ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="cat_order_field"><?php esc_html_e( 'Order', 'ecomcine' ); ?></label></th>
				<td><input id="cat_order_field" type="number" name="cat_sort_order" value="<?php echo esc_attr( $sort_order ); ?>" class="small-text" min="0" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render category custom field form.
	 *
	 * @param array|null $row
	 */
	private static function _render_field_form_fields( ?array $row = null ): void {
		$field_key   = isset( $row['field_key'] ) ? esc_attr( $row['field_key'] ) : '';
		$field_label = isset( $row['field_label'] ) ? esc_attr( $row['field_label'] ) : '';
		$field_type  = isset( $row['field_type'] ) ? sanitize_key( $row['field_type'] ) : 'select';
		$field_icon  = isset( $row['field_icon'] ) ? esc_attr( $row['field_icon'] ) : '';
		$field_order = isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0;
		$required    = ! empty( $row['required'] );
		$public      = array_key_exists( 'show_in_public', (array) $row ) ? ! empty( $row['show_in_public'] ) : true;
		$filters     = array_key_exists( 'show_in_filters', (array) $row ) ? ! empty( $row['show_in_filters'] ) : true;

		$options_text = '';
		if ( isset( $row['options_map'] ) && is_array( $row['options_map'] ) ) {
			$lines = array();
			foreach ( $row['options_map'] as $value => $label ) {
				$lines[] = ( $value === $label ) ? $value : ( $value . ':' . $label );
			}
			$options_text = implode( "\n", $lines );
		}
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="field_key"><?php esc_html_e( 'Field Key', 'ecomcine' ); ?> <span style="color:red;">*</span></label></th>
				<td>
					<input id="field_key" type="text" name="field_key" value="<?php echo $field_key; ?>" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'Meta key used for save/filter (example: camera_type).', 'ecomcine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="field_label"><?php esc_html_e( 'Field Label', 'ecomcine' ); ?> <span style="color:red;">*</span></label></th>
				<td><input id="field_label" type="text" name="field_label" value="<?php echo $field_label; ?>" class="regular-text" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="field_type"><?php esc_html_e( 'Field Type', 'ecomcine' ); ?></label></th>
				<td>
					<select id="field_type" name="field_type">
						<?php foreach ( array( 'select', 'text', 'textarea', 'number', 'radio', 'checkbox' ) as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="field_icon"><?php esc_html_e( 'Icon', 'ecomcine' ); ?></label></th>
				<td><input id="field_icon" type="text" name="field_icon" value="<?php echo $field_icon; ?>" class="regular-text" placeholder="dashicon or emoji" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="field_options"><?php esc_html_e( 'Field Options', 'ecomcine' ); ?></label></th>
				<td>
					<textarea id="field_options" name="field_options" rows="6" class="large-text"><?php echo esc_textarea( $options_text ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Use one per line: value:Label or plain value. Mainly for select/radio/checkbox.', 'ecomcine' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="field_sort_order"><?php esc_html_e( 'Order', 'ecomcine' ); ?></label></th>
				<td><input id="field_sort_order" type="number" name="field_sort_order" value="<?php echo esc_attr( (string) $field_order ); ?>" class="small-text" min="0" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Flags', 'ecomcine' ); ?></th>
				<td>
					<label><input type="checkbox" name="field_required" value="1" <?php checked( $required ); ?> /> <?php esc_html_e( 'Required', 'ecomcine' ); ?></label><br />
					<label><input type="checkbox" name="field_show_in_public" value="1" <?php checked( $public ); ?> /> <?php esc_html_e( 'Show in public profile panel', 'ecomcine' ); ?></label><br />
					<label><input type="checkbox" name="field_show_in_filters" value="1" <?php checked( $filters ); ?> /> <?php esc_html_e( 'Show in store listing filters', 'ecomcine' ); ?></label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Normalize options textarea to consistent lines.
	 *
	 * @param mixed $raw
	 * @return string
	 */
	private static function normalize_options_text( $raw ): string {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$clean = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$clean[] = $line;
		}

		return implode( "\n", $clean );
	}

	/**
	 * Build the categories tab URL, optionally with extra query args.
	 *
	 * @param array $extra_args
	 * @return string
	 */
	private static function _tab_url( array $extra_args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => 'ecomcine-settings', 'tab' => 'categories' ), $extra_args ),
			admin_url( 'admin.php' )
		);
	}
}
