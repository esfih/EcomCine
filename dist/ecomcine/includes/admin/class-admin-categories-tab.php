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
