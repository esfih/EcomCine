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
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_category_icon_svg_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'allow_category_icon_svg_filetype' ), 10, 5 );
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
		$icon_data   = self::resolve_category_icon_submission();

		$error = '';
		if ( '' === $name ) {
			$error = 'name_required';
		} elseif ( '' === $slug ) {
			$error = 'slug_invalid';
		} elseif ( is_wp_error( $icon_data ) ) {
			$error = 'icon_upload_failed';
		} else {
			$result = EcomCine_Person_Category_Registry::create(
				$name,
				$slug,
				$description,
				$sort_order,
				'',
				(int) ( $icon_data['attachment_id'] ?? 0 ),
				(string) ( $icon_data['url'] ?? '' )
			);
			if ( ! $result ) {
				$error = 'create_failed';
			}
		}

		wp_safe_redirect( self::_tab_url( $error ? array( 'view' => 'add', 'cat_error' => $error ) : array( 'view' => 'add', 'cat_created' => 1 ) ) );
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
		$existing_row = $id > 0 ? EcomCine_Person_Category_Registry::get_category( $id ) : null;
		$icon_data    = self::resolve_category_icon_submission( is_array( $existing_row ) ? $existing_row : null );

		$error = '';
		if ( ! $id ) {
			$error = 'invalid_id';
		} elseif ( '' === $name ) {
			$error = 'name_required';
		} elseif ( is_wp_error( $icon_data ) ) {
			$error = 'icon_upload_failed';
		} else {
			$ok = EcomCine_Person_Category_Registry::update( $id, array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'icon_attachment_id' => (int) ( $icon_data['attachment_id'] ?? 0 ),
				'icon_url'    => (string) ( $icon_data['url'] ?? '' ),
				'icon_key'    => '',
				'sort_order'  => $sort_order,
			) );
			if ( ! $ok ) {
				$error = 'update_failed';
			}
		}

		$redirect_args = array( 'view' => 'edit', 'edit_cat' => $id );
		if ( '' !== $error ) {
			$redirect_args['cat_error'] = $error;
		} else {
			$redirect_args['cat_updated'] = 1;
		}

		wp_safe_redirect( self::_tab_url( $redirect_args ) );
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

		wp_safe_redirect( self::_tab_url( $error ? array( 'view' => 'edit', 'cat_error' => $error ) : array( 'view' => 'edit', 'cat_deleted' => 1 ) ) );
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

		wp_enqueue_media();

		$categories = EcomCine_Person_Category_Registry::get_all();
		$active_view = self::get_active_view();
		$edit_id    = isset( $_GET['edit_cat'] ) ? absint( $_GET['edit_cat'] ) : 0;
		$edit_row   = null;
		if ( $edit_id ) {
			foreach ( $categories as $cat ) {
				if ( (int) $cat['id'] === $edit_id ) {
					$edit_row = $cat;
					break;
				}
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
		if ( isset( $_GET['cat_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html( sprintf( __( 'Error: %s', 'ecomcine' ), sanitize_key( wp_unslash( $_GET['cat_error'] ) ) ) )
				. '</p></div>';
		}
		?>
		<div style="max-width:960px;margin-top:20px;">
			<style>
			.ecomcine-subtab-wrapper {
				margin: 0 0 20px;
			}
			.ecomcine-categories-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 20px 24px;
			}
			.ecomcine-categories-card.is-editing {
				padding: 16px 20px 20px;
			}
			.ecomcine-breadcrumb {
				margin: 0 0 14px;
			}
			.ecomcine-categories-card.is-editing .ecomcine-breadcrumb {
				margin-bottom: 10px;
			}
			.ecomcine-categories-card.is-editing h3 {
				margin-bottom: 12px;
			}
			.ecomcine-categories-card.is-editing .form-table {
				margin-top: 0;
			}
			.ecomcine-categories-card.is-editing .submit {
				margin-bottom: 0;
				padding-bottom: 0;
			}
			.ecomcine-category-grid-row {
				display: flex;
				gap: 16px;
				align-items: flex-end;
				flex-wrap: wrap;
			}
			.ecomcine-category-grid-row .ecomcine-grid-field {
				min-width: 140px;
			}
			.ecomcine-grid-field label {
				display: block;
				font-weight: 600;
				margin: 0 0 6px;
			}
			.ecomcine-color-row {
				display: flex;
				gap: 10px;
				align-items: center;
			}
			.ecomcine-category-icon-upload {
				align-items: start;
				display: grid;
				gap: 14px;
				grid-template-columns: 120px minmax(0, 1fr);
				max-width: 720px;
			}
			.ecomcine-category-icon-upload-preview {
				align-items: center;
				background: linear-gradient(180deg,#fff 0%,#f7f1ea 100%);
				border: 1px solid #ccd0d4;
				border-radius: 12px;
				display: flex;
				height: 112px;
				justify-content: center;
				overflow: hidden;
				padding: 10px;
				width: 112px;
			}
			.ecomcine-category-icon-upload-preview img {
				display: block;
				max-height: 100%;
				max-width: 100%;
				object-fit: contain;
			}
			.ecomcine-category-icon-upload-preview.is-empty {
				background: #f6f7f7;
				color: #6b7280;
				font-size: 12px;
				line-height: 1.4;
				text-align: center;
			}
			.ecomcine-category-icon-upload-controls input[type="file"] {
				max-width: 100%;
			}
			.ecomcine-category-icon-upload-controls button {
				margin-right: 8px;
			}
			.ecomcine-category-icon-upload-controls .description {
				margin: 8px 0 0;
			}
			.ecomcine-category-table-icon {
				align-items: center;
				background: linear-gradient(180deg,#fff 0%,#f7f1ea 100%);
				border: 1px solid #d0d7de;
				border-radius: 10px;
				display: inline-flex;
				height: 42px;
				justify-content: center;
				overflow: hidden;
				width: 42px;
			}
			.ecomcine-category-table-icon img {
				display: block;
				max-height: 100%;
				max-width: 100%;
				object-fit: contain;
			}
			.ecomcine-category-table-icon .tm-icon {
				height: 14px;
				width: 14px;
			}
			</style>

			<?php settings_errors( EcomCine_Admin_Settings::OPTION_KEY ); ?>

			<nav class="nav-tab-wrapper ecomcine-subtab-wrapper">
				<a href="<?php echo esc_url( self::_tab_url( array( 'view' => 'edit' ) ) ); ?>" class="nav-tab <?php echo 'edit' === $active_view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Edit Categories', 'ecomcine' ); ?></a>
				<a href="<?php echo esc_url( self::_tab_url( array( 'view' => 'add' ) ) ); ?>" class="nav-tab <?php echo 'add' === $active_view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Add Category', 'ecomcine' ); ?></a>
				<a href="<?php echo esc_url( self::_tab_url( array( 'view' => 'styling' ) ) ); ?>" class="nav-tab <?php echo 'styling' === $active_view ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Styling', 'ecomcine' ); ?></a>
			</nav>


			<?php if ( 'edit' === $active_view ) : ?>
			<div class="ecomcine-categories-card <?php echo $edit_row ? 'is-editing' : ''; ?>">
				<?php if ( $edit_row ) : ?>
					<p class="ecomcine-breadcrumb"><a href="<?php echo esc_url( self::_tab_url( array( 'view' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Back To Categories List', 'ecomcine' ); ?></a></p>
				<?php endif; ?>
				<?php if ( $edit_row ) : ?>
					<h3 style="margin-top:0;"><?php echo esc_html( sprintf( __( 'Edit Category: %s', 'ecomcine' ), $edit_row['name'] ) ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ecomcine_category_update' ); ?>
						<input type="hidden" name="action" value="ecomcine_category_update" />
						<input type="hidden" name="cat_id" value="<?php echo esc_attr( $edit_row['id'] ); ?>" />
						<?php self::_render_form_fields( $edit_row ); ?>
						<?php submit_button( __( 'Update Category', 'ecomcine' ), 'primary', 'submit', false ); ?>
					</form>
				<?php elseif ( ! empty( $categories ) ) : ?>
				<table class="widefat striped" style="margin-bottom:24px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Icon', 'ecomcine' ); ?></th>
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
							<td><?php echo self::render_category_icon_badge( $cat ); ?></td>
							<td><strong><?php echo esc_html( $cat['name'] ); ?></strong></td>
							<td><code><?php echo esc_html( $cat['slug'] ); ?></code></td>
							<td><?php echo esc_html( $cat['description'] ); ?></td>
							<td><?php echo esc_html( $cat['sort_order'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( self::_tab_url( array( 'view' => 'edit', 'edit_cat' => $cat['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'ecomcine' ); ?></a>
								&nbsp;|&nbsp;
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'ecomcine_category_delete' ); ?>
									<input type="hidden" name="action" value="ecomcine_category_delete" />
									<input type="hidden" name="cat_id" value="<?php echo esc_attr( $cat['id'] ); ?>" />
									<button type="submit" class="button-link" style="color:#d63638;" onclick="return confirm('<?php esc_attr_e( 'Delete this category and all person assignments?', 'ecomcine' ); ?>');"><?php esc_html_e( 'Delete', 'ecomcine' ); ?></button>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No categories found yet.', 'ecomcine' ); ?></p>
				<?php endif; ?>
			</div>
			<?php elseif ( 'add' === $active_view ) : ?>
			<div class="ecomcine-categories-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Add Category', 'ecomcine' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ecomcine_category_create' ); ?>
					<input type="hidden" name="action" value="ecomcine_category_create" />
					<?php self::_render_form_fields(); ?>
					<?php submit_button( __( 'Add Category', 'ecomcine' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
			<?php else : ?>
			<?php $category_grid = EcomCine_Admin_Settings::get_category_grid_settings(); ?>
			<div class="ecomcine-categories-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Category Grid Styling', 'ecomcine' ); ?></h3>
				<form method="post" action="options.php">
					<?php settings_fields( 'ecomcine_settings_group' ); ?>
					<div class="ecomcine-category-grid-row" style="margin-bottom:20px;">
						<div class="ecomcine-grid-field">
							<label for="ecomcine-categories-grid-rows"><?php esc_html_e( 'Rows', 'ecomcine' ); ?></label>
							<input id="ecomcine-categories-grid-rows" type="number" min="1" max="12" step="1" class="small-text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][rows]" value="<?php echo esc_attr( (string) (int) $category_grid['rows'] ); ?>" />
						</div>
						<div class="ecomcine-grid-field">
							<label for="ecomcine-categories-grid-columns"><?php esc_html_e( 'Columns', 'ecomcine' ); ?></label>
							<input id="ecomcine-categories-grid-columns" type="number" min="1" max="6" step="1" class="small-text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][columns]" value="<?php echo esc_attr( (string) (int) $category_grid['columns'] ); ?>" />
						</div>
					</div>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ecomcine-categories-grid-gap"><?php esc_html_e( 'Padding Between Cards', 'ecomcine' ); ?></label></th>
							<td><input id="ecomcine-categories-grid-gap" type="number" min="0" max="80" step="1" class="small-text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][card_gap]" value="<?php echo esc_attr( (string) (int) $category_grid['card_gap'] ); ?>" /> <span class="description"><?php esc_html_e( 'px', 'ecomcine' ); ?></span></td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-card-radius"><?php esc_html_e( 'Card Corner Radius', 'ecomcine' ); ?></label></th>
							<td><input id="ecomcine-categories-card-radius" type="number" min="0" max="80" step="1" class="small-text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][card_radius]" value="<?php echo esc_attr( (string) (int) ( $category_grid['card_radius'] ?? 24 ) ); ?>" /> <span class="description"><?php esc_html_e( 'px', 'ecomcine' ); ?></span></td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-border-width"><?php esc_html_e( 'Border Thickness', 'ecomcine' ); ?></label></th>
							<td><input id="ecomcine-categories-border-width" type="number" min="0" max="20" step="1" class="small-text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][border_width]" value="<?php echo esc_attr( (string) (int) $category_grid['border_width'] ); ?>" /> <span class="description"><?php esc_html_e( 'px', 'ecomcine' ); ?></span></td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-card-background"><?php esc_html_e( 'Card Background Color', 'ecomcine' ); ?></label></th>
							<td>
								<div class="ecomcine-color-row">
									<input id="ecomcine-categories-card-background-picker" class="ecomcine-color-sync" type="color" value="<?php echo esc_attr( (string) ( $category_grid['card_background_color'] ?? '#FFF8F0' ) ); ?>" data-target="#ecomcine-categories-card-background" />
									<input id="ecomcine-categories-card-background" class="regular-text ecomcine-color-sync-target" type="text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][card_background_color]" value="<?php echo esc_attr( (string) ( $category_grid['card_background_color'] ?? '#FFF8F0' ) ); ?>" />
								</div>
								<p class="description"><?php esc_html_e( 'Use the color picker or enter a hex code.', 'ecomcine' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-card-background-hover"><?php esc_html_e( 'Card Background Hover Color', 'ecomcine' ); ?></label></th>
							<td>
								<div class="ecomcine-color-row">
									<input id="ecomcine-categories-card-background-hover-picker" class="ecomcine-color-sync" type="color" value="<?php echo esc_attr( (string) ( $category_grid['card_background_hover_color'] ?? ( $category_grid['card_background_color'] ?? '#FFF8F0' ) ) ); ?>" data-target="#ecomcine-categories-card-background-hover" />
									<input id="ecomcine-categories-card-background-hover" class="regular-text ecomcine-color-sync-target" type="text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][card_background_hover_color]" value="<?php echo esc_attr( (string) ( $category_grid['card_background_hover_color'] ?? ( $category_grid['card_background_color'] ?? '#FFF8F0' ) ) ); ?>" />
								</div>
								<p class="description"><?php esc_html_e( 'Use the color picker or enter a hex code.', 'ecomcine' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-title-color"><?php esc_html_e( 'Title Text Color', 'ecomcine' ); ?></label></th>
							<td>
								<div class="ecomcine-color-row">
									<input id="ecomcine-categories-title-color-picker" class="ecomcine-color-sync" type="color" value="<?php echo esc_attr( (string) ( $category_grid['title_color'] ?? '#111827' ) ); ?>" data-target="#ecomcine-categories-title-color" />
									<input id="ecomcine-categories-title-color" class="regular-text ecomcine-color-sync-target" type="text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][title_color]" value="<?php echo esc_attr( (string) ( $category_grid['title_color'] ?? '#111827' ) ); ?>" />
								</div>
								<p class="description"><?php esc_html_e( 'Use the color picker or enter a hex code.', 'ecomcine' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-border-style"><?php esc_html_e( 'Border Style', 'ecomcine' ); ?></label></th>
							<td>
								<select id="ecomcine-categories-border-style" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][border_style]">
									<?php foreach ( array( 'none', 'solid', 'dotted', 'dashed', 'double' ) as $border_style ) : ?>
										<option value="<?php echo esc_attr( $border_style ); ?>" <?php selected( $category_grid['border_style'], $border_style ); ?>><?php echo esc_html( ucfirst( $border_style ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-categories-border-color"><?php esc_html_e( 'Border Color', 'ecomcine' ); ?></label></th>
							<td>
								<div class="ecomcine-color-row">
									<input id="ecomcine-categories-border-color-picker" class="ecomcine-color-sync" type="color" value="<?php echo esc_attr( (string) $category_grid['border_color'] ); ?>" data-target="#ecomcine-categories-border-color" />
									<input id="ecomcine-categories-border-color" class="regular-text ecomcine-color-sync-target" type="text" name="<?php echo esc_attr( EcomCine_Admin_Settings::OPTION_KEY ); ?>[categories_grid][border_color]" value="<?php echo esc_attr( (string) $category_grid['border_color'] ); ?>" />
								</div>
								<p class="description"><?php esc_html_e( 'Use the color picker or enter a hex code.', 'ecomcine' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Category Styling', 'ecomcine' ) ); ?>
				</form>
			</div>
			<?php endif; ?>

			<script>
			jQuery(function($){
				var categoryFrame;

				$('body').on('click', '.ecomcine-category-media-select', function(e){
					e.preventDefault();
					var container = $(this).closest('.ecomcine-category-icon-upload');
					if (!categoryFrame) {
						categoryFrame = wp.media({ title: 'Select Category Image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
					}

					categoryFrame.off('select');
					categoryFrame.on('select', function(){
						var attachment = categoryFrame.state().get('selection').first().toJSON();
						container.find('.ecomcine-category-icon-id').val(attachment.id || 0);
						container.find('.ecomcine-category-icon-url').val(attachment.url || '');
						container.find('.ecomcine-category-icon-remove-flag').prop('checked', false);
						container.find('.ecomcine-category-icon-upload-preview').removeClass('is-empty').html('<img src="' + (attachment.url || '') + '" alt="" />');
					});
					categoryFrame.open();
				});

				$('body').on('click', '.ecomcine-category-media-remove', function(e){
					e.preventDefault();
					var container = $(this).closest('.ecomcine-category-icon-upload');
					container.find('.ecomcine-category-icon-id').val('0');
					container.find('.ecomcine-category-icon-url').val('');
					container.find('.ecomcine-category-icon-remove-flag').prop('checked', true);
					container.find('.ecomcine-category-icon-upload-preview').addClass('is-empty').html('<span>No image selected</span>');
				});

				$('body').on('input change', '.ecomcine-color-sync', function(){
					var target = $(this).data('target');
					if (target) {
						$(target).val($(this).val());
					}
				});

				$('body').on('input change', '.ecomcine-color-sync-target', function(){
					var value = $(this).val();
					if (/^#[0-9a-fA-F]{6}$/.test(value)) {
						$('#ecomcine-categories-border-color-picker').val(value);
					}
				});
			});
			</script>

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
		$icon_attachment_id = isset( $row['icon_attachment_id'] ) ? absint( $row['icon_attachment_id'] ) : 0;
		$icon_url    = is_array( $row ) ? EcomCine_Person_Category_Registry::get_category_icon_url( $row ) : '';
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
				<th scope="row"><?php esc_html_e( 'Icon Image', 'ecomcine' ); ?></th>
				<td>
					<div class="ecomcine-category-icon-upload">
						<div class="ecomcine-category-icon-upload-preview <?php echo '' === $icon_url ? 'is-empty' : ''; ?>">
							<?php if ( '' !== $icon_url ) : ?>
								<img src="<?php echo esc_url( $icon_url ); ?>" alt="" />
							<?php else : ?>
								<span><?php esc_html_e( 'No image uploaded', 'ecomcine' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="ecomcine-category-icon-upload-controls">
							<button type="button" class="button button-secondary ecomcine-category-media-select"><?php esc_html_e( 'Select From Media Library', 'ecomcine' ); ?></button>
							<button type="button" class="button button-link-delete ecomcine-category-media-remove"><?php esc_html_e( 'Remove Image', 'ecomcine' ); ?></button>
							<input type="hidden" class="ecomcine-category-icon-id" name="cat_icon_existing_id" value="<?php echo esc_attr( (string) $icon_attachment_id ); ?>" />
							<input type="hidden" class="ecomcine-category-icon-url" name="cat_icon_existing_url" value="<?php echo esc_attr( $icon_url ); ?>" />
							<input type="checkbox" class="ecomcine-category-icon-remove-flag" name="cat_icon_remove" value="1" style="display:none;" />
							<p class="description"><?php esc_html_e( 'Use the WordPress media library to choose or upload a category image, including SVG files when available to admins.', 'ecomcine' ); ?></p>
						</div>
					</div>
				</td>
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
			array_merge( array( 'page' => 'ecomcine-settings', 'tab' => 'categories', 'view' => self::get_active_view() ), $extra_args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve the active categories sub-tab.
	 *
	 * @return string
	 */
	private static function get_active_view(): string {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'edit';
		return in_array( $view, array( 'edit', 'add', 'styling' ), true ) ? $view : 'edit';
	}

	/**
	 * Resolve uploaded category icon submission state.
	 *
	 * @param array|null $existing_row Existing row data for edits.
	 * @return array|WP_Error
	 */
	private static function resolve_category_icon_submission( ?array $existing_row = null ) {
		$posted_attachment_id = absint( $_POST['cat_icon_existing_id'] ?? 0 );
		$posted_url           = EcomCine_Person_Category_Registry::sanitize_icon_url( (string) wp_unslash( $_POST['cat_icon_existing_url'] ?? '' ) );

		$attachment_id = $posted_attachment_id;
		$url           = $posted_url;

		if ( is_array( $existing_row ) ) {
			if ( 0 === $attachment_id ) {
				$attachment_id = absint( $existing_row['icon_attachment_id'] ?? 0 );
			}
			if ( '' === $url ) {
				$url = EcomCine_Person_Category_Registry::get_category_icon_url( $existing_row );
			}
		}
		$remove        = ! empty( $_POST['cat_icon_remove'] );

		if ( $remove ) {
			$attachment_id = 0;
			$url           = '';
		}

		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new WP_Error( 'invalid_attachment', __( 'The selected media library item is no longer available.', 'ecomcine' ) );
			}

			$mime_type = (string) get_post_mime_type( $attachment_id );
			if ( 0 !== strpos( $mime_type, 'image/' ) ) {
				return new WP_Error( 'invalid_image', __( 'The selected media item must be an image.', 'ecomcine' ) );
			}

			$resolved_url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $resolved_url ) && '' !== $resolved_url ) {
				$url = EcomCine_Person_Category_Registry::sanitize_icon_url( $resolved_url );
			}
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $url,
		);
	}

	/**
	 * Render the icon badge shown in the category table.
	 *
	 * @param array $category Category row.
	 * @return string
	 */
	private static function render_category_icon_badge( array $category ): string {
		$icon_url = EcomCine_Person_Category_Registry::get_category_icon_url( $category );
		if ( '' !== $icon_url ) {
			return '<span class="ecomcine-category-table-icon"><img src="' . esc_url( $icon_url ) . '" alt="" /></span>';
		}

		$svg = self::render_category_icon_svg( (string) ( $category['icon_key'] ?? '' ) );
		if ( '' === $svg ) {
			return '';
		}

		return '<span class="ecomcine-category-table-icon">' . $svg . '</span>';
	}

	/**
	 * Render the raw SVG for a category icon.
	 *
	 * @param string $icon_key Icon key.
	 * @param string $title Optional accessible title.
	 * @return string
	 */
	private static function render_category_icon_svg( string $icon_key, string $title = '' ): string {
		$icon_key = EcomCine_Person_Category_Registry::sanitize_icon_key( $icon_key );
		if ( '' === $icon_key || ! class_exists( 'TM_Icons' ) ) {
			return '';
		}

		return TM_Icons::svg( $icon_key, '', $title );
	}

	/**
	 * Temporarily allow SVG uploads for category icon files.
	 *
	 * @param array $mimes Allowed MIME list.
	 * @return array
	 */
	public static function allow_category_icon_svg_mime( array $mimes ): array {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return $mimes;
		}

		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Normalize SVG file type validation during category icon uploads.
	 *
	 * @param array  $data File type data.
	 * @param string $file Full path to file.
	 * @param string $filename File name.
	 * @param array|null $mimes Allowed MIME list.
	 * @param string|false $real_mime Real MIME when available.
	 * @return array
	 */
	public static function allow_category_icon_svg_filetype( array $data, string $file, string $filename, ?array $mimes, $real_mime ): array {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return $data;
		}

		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'svg' === $extension ) {
			$data['ext'] = 'svg';
			$data['type'] = 'image/svg+xml';
		}

		return $data;
	}
}
