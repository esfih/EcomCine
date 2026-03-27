<?php
/**
 * Footer template override.
 *
 * Keeps the required closing wrappers and wp_footer() call,
 * but intentionally omits Astra footer markup site-wide.
 *
 * @package Astra Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
		</div><!-- #content -->
	</div><!-- #page -->
<?php wp_footer(); ?>
</body>
</html>
