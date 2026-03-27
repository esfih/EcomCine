<?php
/**
 * Shop footer template override.
 *
 * Reuse the child theme footer override so get_footer( 'shop' )
 * never falls back to Astra's footer markup.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'footer' );
