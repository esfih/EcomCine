<?php
/**
 * Compatibility adapter: vendor identity projector using Dokan store info.
 *
 * @package EcomCine_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Compat_Vendor_Identity_Projector implements THO_Vendor_Identity_Projector {

	public function project_vendor_identity( int $vendor_id ): array {
		if ( $vendor_id <= 0 ) {
			return [ 'vendor_id' => 0, 'name' => '', 'avatar_url' => '', 'banner_url' => '', 'store_url' => '' ];
		}

		$name       = '';
		$avatar_url = '';
		$store_url  = '';

		if ( function_exists( 'dokan_get_store_info' ) ) {
			$info  = dokan_get_store_info( $vendor_id );
			$name  = $info['store_name'] ?? '';
		}
		if ( ! $name ) {
			$u    = get_userdata( $vendor_id );
			$name = $u ? $u->display_name : '';
		}

		if ( function_exists( 'dokan_get_store_url' ) ) {
			$store_url = dokan_get_store_url( $vendor_id );
		}
		if ( ! $store_url ) {
			$store_url = get_author_posts_url( $vendor_id );
		}

		// Avatar: Dokan banner → WP avatar.
		if ( function_exists( 'dokan' ) ) {
			try {
				$banner_url = dokan()->vendor->get( $vendor_id )->get_banner();
				if ( $banner_url && filter_var( $banner_url, FILTER_VALIDATE_URL ) ) {
					// Banner is a full URL to hero image; use avatar-sized attachment instead.
					$banner_id = (int) get_user_meta( $vendor_id, 'dokan_store_banner_id', true );
					if ( $banner_id ) {
						$src        = wp_get_attachment_image_src( $banner_id, [ 80, 80 ] );
						$avatar_url = $src ? $src[0] : '';
					}
				}
			} catch ( \Throwable $e ) {
				// Ignore.
			}
		}
		if ( ! $avatar_url ) {
			$avatar_url = get_avatar_url( $vendor_id, [ 'size' => 80 ] );
		}

		return [
			'vendor_id'  => $vendor_id,
			'name'       => $name,
			'avatar_url' => $avatar_url,
			'banner_url' => '',
			'store_url'  => $store_url,
		];
	}

	public function render_vendor_identity_block( int $vendor_id, string $context = 'product_loop' ): string {
		if ( $vendor_id <= 0 ) {
			return '';
		}

		$identity = $this->project_vendor_identity( $vendor_id );
		if ( ! $identity['name'] ) {
			return '';
		}

		ob_start();
		?>
		<div class="tm-vendor-identity tm-vendor-identity--<?php echo esc_attr( $context ); ?>">
			<?php if ( $identity['avatar_url'] ) : ?>
			<a class="tm-vendor-identity__avatar-link" href="<?php echo esc_url( $identity['store_url'] ); ?>">
				<img class="tm-vendor-identity__avatar"
					src="<?php echo esc_url( $identity['avatar_url'] ); ?>"
					alt="<?php echo esc_attr( $identity['name'] ); ?>"
					width="40" height="40" loading="lazy">
			</a>
			<?php endif; ?>
			<a class="tm-vendor-identity__name" href="<?php echo esc_url( $identity['store_url'] ); ?>">
				<?php echo esc_html( $identity['name'] ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
}
