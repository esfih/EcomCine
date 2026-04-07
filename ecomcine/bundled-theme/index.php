<?php
/**
 * EcomCine Base Theme — fallback index template.
 *
 * This file is required by WordPress theme standards.
 * All real page templates are provided by the EcomCine plugin.
 */
get_header();
?>
<main id="tm-main" style="padding: 40px 20px; font-family: sans-serif;">
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; else : ?>
		<p><?php esc_html_e( 'No content found.', 'ecomcine-base' ); ?></p>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
