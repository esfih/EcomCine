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
		$banner_url = '';
		$store_url  = '';
		$person_info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $vendor_id ) : array();

		$name  = isset( $person_info['store_name'] ) ? (string) $person_info['store_name'] : '';
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

		$banner_id = isset( $person_info['banner_id'] ) ? (int) $person_info['banner_id'] : 0;
		if ( $banner_id > 0 ) {
			$src = wp_get_attachment_image_src( $banner_id, 'full' );
			$banner_url = $src ? $src[0] : '';
		}

		$avatar_id = isset( $person_info['avatar_id'] ) ? (int) $person_info['avatar_id'] : 0;
		if ( $avatar_id > 0 ) {
			$src = wp_get_attachment_image_src( $avatar_id, array( 80, 80 ) );
			$avatar_url = $src ? $src[0] : '';
		}
		if ( ! $avatar_url ) {
			$avatar_url = get_avatar_url( $vendor_id, [ 'size' => 80 ] );
		}

		return [
			'vendor_id'  => $vendor_id,
			'name'       => $name,
			'avatar_url' => $avatar_url,
			'banner_url' => $banner_url,
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
