<?php
/**
 * EcomCine admin settings and feature toggles.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Admin_Settings {
	const OPTION_KEY = 'ecomcine_settings';
	const DEFAULT_RUNTIME_MODE = 'wp_cpt';

	/** @var array<string,bool> Prevent duplicate runtime fallback logs per request. */
	private static array $runtime_mode_log_flags = array();

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_dequeue_external_admin_assets' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'render_style_tokens_css' ), 30 );
		add_action( 'init', array( __CLASS__, 'register_bootstrap_shortcodes' ) );
		add_action( 'admin_post_ecomcine_create_bootstrap_pages', array( __CLASS__, 'handle_create_bootstrap_pages' ) );
		add_action( 'admin_post_ecomcine_install_activate_theme', array( __CLASS__, 'handle_install_activate_theme' ) );
	}

	/**
	 * Keep wp_cpt mode admin pages isolated from Dokan global admin bundles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function maybe_dequeue_external_admin_assets( $hook_suffix ) {
		if ( 'wp_cpt' !== self::get_runtime_mode() ) {
			return;
		}

		if ( ! self::is_ecomcine_admin_page( $hook_suffix ) ) {
			return;
		}

		$explicit_script_handles = array(
			'dokan-pro-react-admin',
			'dokan-pro-admin-dashboard',
			'dokan-seller-badge-admin',
			'dokan-seller-badge-admin-vendor',
			'dokan-seller-badge-vendor-tab',
		);

		$explicit_style_handles = array(
			'dokan-pro-admin-dashboard',
			'dokan-seller-badge-admin',
			'dokan-seller-badge-vendor-tab',
		);

		foreach ( $explicit_script_handles as $handle ) {
			wp_dequeue_script( $handle );
		}

		foreach ( $explicit_style_handles as $handle ) {
			wp_dequeue_style( $handle );
		}

		global $wp_scripts, $wp_styles;

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( ! is_string( $handle ) ) {
					continue;
				}

				if ( 0 === strpos( $handle, 'dokan-' ) || false !== strpos( $handle, 'dokan' ) ) {
					wp_dequeue_script( $handle );
				}
			}
		}

		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				if ( ! is_string( $handle ) ) {
					continue;
				}

				if ( 0 === strpos( $handle, 'dokan-' ) || false !== strpos( $handle, 'dokan' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	/**
	 * Determine whether current admin request is an EcomCine-managed page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	private static function is_ecomcine_admin_page( $hook_suffix ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' !== $page && ( 'ecomcine-settings' === $page || 0 === strpos( $page, 'ecomcine-' ) ) ) {
			return true;
		}

		return is_string( $hook_suffix ) && false !== strpos( $hook_suffix, 'ecomcine' );
	}

	/**
	 * Register helper shortcodes used by auto-generated onboarding pages.
	 */
	public static function register_bootstrap_shortcodes() {
		if ( ! shortcode_exists( 'ecomcine_categories' ) ) {
			add_shortcode( 'ecomcine_categories', array( __CLASS__, 'shortcode_categories' ) );
		}

		if ( ! shortcode_exists( 'ecomcine_locations' ) ) {
			add_shortcode( 'ecomcine_locations', array( __CLASS__, 'shortcode_locations' ) );
		}
	}

	/**
	 * Render native EcomCine categories for onboarding and category landing pages.
	 */
	public static function shortcode_categories() {
		if ( ! class_exists( 'EcomCine_Person_Category_Registry' ) ) {
			return '<p>No categories found yet.</p>';
		}

		$categories = EcomCine_Person_Category_Registry::get_all();
		$grid_settings = self::get_category_grid_settings();

		if ( empty( $categories ) ) {
			return '<p>No categories found yet.</p>';
		}

		$talents_page = get_page_by_path( 'talents', OBJECT, 'page' );
		$talents_url  = $talents_page instanceof WP_Post ? get_permalink( $talents_page ) : home_url( '/talents/' );
		$grid_rows = max( 1, min( 12, (int) ( $grid_settings['rows'] ?? 2 ) ) );
		$grid_columns = max( 1, min( 6, (int) ( $grid_settings['columns'] ?? 4 ) ) );
		$card_gap = max( 0, min( 80, (int) ( $grid_settings['card_gap'] ?? 18 ) ) );
		$border_width = max( 0, min( 20, (int) ( $grid_settings['border_width'] ?? 1 ) ) );
		$card_radius = max( 0, min( 80, (int) ( $grid_settings['card_radius'] ?? 24 ) ) );
		$border_style = sanitize_key( (string) ( $grid_settings['border_style'] ?? 'solid' ) );
		$border_color = sanitize_hex_color( (string) ( $grid_settings['border_color'] ?? '#D6C3A5' ) );
		if ( ! in_array( $border_style, array( 'none', 'solid', 'dotted', 'dashed', 'double' ), true ) ) {
			$border_style = 'solid';
		}
		if ( ! $border_color ) {
			$border_color = '#D6C3A5';
		}
		$card_background_color = sanitize_hex_color( (string) ( $grid_settings['card_background_color'] ?? '#FFF8F0' ) );
		if ( ! $card_background_color ) {
			$card_background_color = '#FFF8F0';
		}
		$card_background_hover_color = sanitize_hex_color( (string) ( $grid_settings['card_background_hover_color'] ?? $card_background_color ) );
		if ( ! $card_background_hover_color ) {
			$card_background_hover_color = $card_background_color;
		}
		$title_color = sanitize_hex_color( (string) ( $grid_settings['title_color'] ?? '#111827' ) );
		if ( ! $title_color ) {
			$title_color = '#111827';
		}
		$cat_sort_key = isset( $_GET['ecomcine_cat_order'] ) ? sanitize_key( wp_unslash( $_GET['ecomcine_cat_order'] ) ) : 'name_az';
		if ( ! in_array( $cat_sort_key, array( 'name_az', 'name_za' ), true ) ) {
			$cat_sort_key = 'name_az';
		}
		if ( 'name_za' === $cat_sort_key ) {
			usort( $categories, function( $a, $b ) {
				return strcmp( strtolower( (string) ( $b['name'] ?? '' ) ), strtolower( (string) ( $a['name'] ?? '' ) ) );
			} );
		} else {
			usort( $categories, function( $a, $b ) {
				return strcmp( strtolower( (string) ( $a['name'] ?? '' ) ), strtolower( (string) ( $b['name'] ?? '' ) ) );
			} );
		}
		$per_page = max( 1, $grid_rows * $grid_columns );
		$total_categories = count( $categories );
		$total_pages = max( 1, (int) ceil( $total_categories / $per_page ) );
		$current_page = isset( $_GET['ecomcine_category_page'] ) ? absint( wp_unslash( $_GET['ecomcine_category_page'] ) ) : 1;
		$current_page = max( 1, min( $total_pages, $current_page ) );
		$visible_categories = array_slice( $categories, ( $current_page - 1 ) * $per_page, $per_page );
		$showcase_page = get_page_by_path( 'showcase', OBJECT, 'page' );
		$showcase_url  = $showcase_page instanceof WP_Post ? get_permalink( $showcase_page ) : home_url( '/showcase/' );
		$showcase_ids  = array();
		if ( function_exists( 'tm_store_ui_collect_person_ids_for_listing' ) ) {
			$showcase_ids = array_values( array_filter( array_map( 'intval', (array) tm_store_ui_collect_person_ids_for_listing() ) ) );
		} elseif ( function_exists( 'ecomcine_get_persons' ) ) {
			$people = ecomcine_get_persons( array( 'fields' => 'ids', 'number' => -1 ) );
			foreach ( (array) $people as $person_id ) {
				$person_id = (int) $person_id;
				if ( $person_id < 1 ) {
					continue;
				}
				if ( function_exists( 'ecomcine_is_person_enabled' ) && ! ecomcine_is_person_enabled( $person_id ) ) {
					continue;
				}
				$showcase_ids[] = $person_id;
			}
		}
		$showcase_ids = array_values( array_unique( $showcase_ids ) );
		$showcase_cta_url = ! empty( $showcase_ids ) ? add_query_arg( 'tm_ids', implode( ',', $showcase_ids ), $showcase_url ) : '';
		$prev_page_url = $current_page > 1 ? add_query_arg( 'ecomcine_category_page', $current_page - 1, remove_query_arg( 'ecomcine_category_page' ) ) : '';
		$next_page_url = $current_page < $total_pages ? add_query_arg( 'ecomcine_category_page', $current_page + 1, remove_query_arg( 'ecomcine_category_page' ) ) : '';

		$style = '<style>
		.ecomcine-category-grid{display:grid;gap:' . esc_html( (string) $card_gap ) . 'px;grid-template-columns:repeat(' . esc_html( (string) $grid_columns ) . ',minmax(0,1fr));margin:0;padding:0 20px;list-style:none;}
		.ecomcine-category-card{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;min-height:190px;padding:24px 22px;border:' . esc_html( (string) $border_width ) . 'px ' . esc_html( $border_style ) . ' ' . esc_html( $border_color ) . ';border-radius:' . esc_html( (string) $card_radius ) . 'px;background:' . esc_html( $card_background_color ) . ';color:#111827;text-decoration:none;overflow:hidden;text-align:center;transition:transform .18s ease;}
		.ecomcine-category-card:hover,.ecomcine-category-card:focus-visible{transform:translateY(-3px);background:' . esc_html( $card_background_hover_color ) . ';outline:none;}
		.ecomcine-category-card__icon{display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:22px;background:#111827;color:#fff;box-shadow:0 10px 24px rgba(17,24,39,.18);}
		.ecomcine-category-card__icon img{display:block;max-height:100%;max-width:100%;object-fit:contain;}
		.ecomcine-category-card__icon .tm-icon{width:30px;height:30px;}
		.ecomcine-category-card__title{margin:0;font-size:1.05rem;line-height:1.25;font-weight:700;letter-spacing:-.02em;max-width:14ch;color:' . esc_html( $title_color ) . ';}
		.tm-card-arrow{position:static;z-index:auto;width:42px;height:42px;background:rgba(0,0,0,0.6);border:2px solid rgba(212,175,55,0.5);border-radius:50%;color:#D4AF37;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;transition:background 0.2s,border-color 0.2s,transform 0.15s;box-shadow:0 2px 14px rgba(0,0,0,0.55);flex-shrink:0;text-decoration:none;}
		.tm-card-arrow svg{width:20px;height:20px;pointer-events:none;}
		.tm-card-arrow:hover:not([disabled]){background:rgba(212,175,55,0.18);border-color:#D4AF37;transform:scale(1.12);}
		.tm-card-arrow[disabled]{opacity:0.18;cursor:default;pointer-events:none;}
		#tm-pager-bar{margin-top:60px;margin-bottom:20px;z-index:20;display:flex;justify-content:center;align-items:center;gap:10px;}
		#tm-pager-bar>.tm-card-arrow:first-child{margin-right:25px;}
		#tm-pager-bar>.tm-card-arrow:last-child{margin-left:25px;}
		#tm-sort-wrap{position:relative;}
		#tm-sort-btn{display:flex;align-items:center;justify-content:center;width:42px;height:42px;background:rgba(0,0,0,0.6);border:1px solid rgba(212,175,55,0.5);border-radius:4px;color:#D4AF37;cursor:pointer;padding:0;transition:background 0.2s,border-color 0.2s;box-shadow:0 2px 14px rgba(0,0,0,0.55);}
		#tm-sort-btn svg{width:18px;height:18px;pointer-events:none;}
		#tm-sort-btn:hover,#tm-sort-btn.is-open{background:rgba(212,175,55,0.15);border-color:#D4AF37;}
		#tm-sort-dropdown{display:none;position:absolute;bottom:calc(100% + 8px);left:0;min-width:165px;background:#111;border:1px solid rgba(212,175,55,0.4);border-radius:4px;list-style:none;margin:0;padding:4px 0;z-index:30;box-shadow:0 4px 20px rgba(0,0,0,0.7);}
		#tm-sort-dropdown.is-open{display:block;}
		#tm-sort-dropdown li{padding:10px 16px;font-size:11px;letter-spacing:0.07em;text-transform:uppercase;color:rgba(212,175,55,0.7);cursor:pointer;transition:background 0.15s,color 0.15s;white-space:nowrap;}
		#tm-sort-dropdown li:hover{background:rgba(212,175,55,0.1);color:#D4AF37;}
		#tm-sort-dropdown li.selected{color:#D4AF37;font-weight:600;}
		#tm-sort-dropdown li.selected::before{content:"\2713\00a0";}
		#tm-showcase-btn{display:inline-flex;align-items:center;height:42px;padding:0 22px;background:transparent;border:1px solid #D4AF37;border-radius:4px;color:#D4AF37;font-size:11px;letter-spacing:0.08em;text-transform:uppercase;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background 0.2s,color 0.2s;box-shadow:0 2px 14px rgba(0,0,0,0.55);}
		#tm-showcase-btn:hover{background:#D4AF37;color:#000;}
		@media (max-width: 900px){.ecomcine-category-grid{grid-template-columns:repeat(' . esc_html( (string) min( 2, $grid_columns ) ) . ',minmax(0,1fr));}}
		@media (max-width: 640px){.ecomcine-category-grid{grid-template-columns:1fr;}.ecomcine-category-card{min-height:168px;padding:20px 18px;border-radius:20px;}.ecomcine-category-card__icon{width:64px;height:64px;border-radius:20px;}#tm-pager-bar{gap:12px;margin-top:22px;}#tm-showcase-btn{width:100%;}}
		</style>';

		$html = $style . '<ul class="ecomcine-category-grid ecomcine-term-list ecomcine-term-list--categories">';
		foreach ( $visible_categories as $category ) {
			$slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );
			$name = trim( (string) ( $category['name'] ?? '' ) );
			if ( '' === $slug || '' === $name ) {
				continue;
			}

			$link = add_query_arg( 'ecomcine_person_category', $slug, $talents_url );
			$icon = '';
			$icon_url = EcomCine_Person_Category_Registry::get_category_icon_url( $category );
			if ( '' !== $icon_url ) {
				$icon = '<img src="' . esc_url( $icon_url ) . '" alt="" />';
			}
			$icon_key = EcomCine_Person_Category_Registry::sanitize_icon_key( (string) ( $category['icon_key'] ?? '' ) );
			if ( '' === $icon && '' !== $icon_key && class_exists( 'TM_Icons' ) ) {
				$icon = TM_Icons::svg( $icon_key, '', $name );
			}
			if ( '' === $icon && class_exists( 'TM_Icons' ) ) {
				$icon = TM_Icons::svg( 'circle-user', '', $name );
			}

			$html .= '<li>';
			$html .= '<a class="ecomcine-category-card" href="' . esc_url( $link ) . '">';
			$html .= '<span class="ecomcine-category-card__icon">' . $icon . '</span>';
			$html .= '<h3 class="ecomcine-category-card__title">' . esc_html( $name ) . '</h3>';
			$html .= '</a>';
			$html .= '</li>';
		}
		$html .= '</ul>';
		if ( $total_pages > 1 || '' !== $showcase_cta_url ) {
			$sort_options = array(
				'name_az' => 'Name A \u2192 Z',
				'name_za' => 'Name Z \u2192 A',
			);
			$html .= '<div id="tm-pager-bar">';
			// Prev arrow
			if ( '' !== $prev_page_url ) {
				$html .= '<a class="tm-card-arrow" rel="prev" href="' . esc_url( $prev_page_url ) . '" aria-label="' . esc_attr__( 'Previous categories page', 'ecomcine' ) . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>';
			}
			// Sort button + dropdown
			$html .= '<div id="tm-sort-wrap">';
			$html .= '<button id="tm-sort-btn" aria-label="' . esc_attr__( 'Sort order', 'ecomcine' ) . '" type="button">';
			$html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="9" y2="18"/></svg>';
			$html .= '</button>';
			$html .= '<ul id="tm-sort-dropdown">';
			foreach ( $sort_options as $val => $label ) {
				$sort_url      = add_query_arg( 'ecomcine_cat_order', $val, remove_query_arg( array( 'ecomcine_cat_order', 'ecomcine_category_page' ) ) );
				$sel_class     = $val === $cat_sort_key ? ' class="selected"' : '';
				$html .= '<li data-value="' . esc_attr( $val ) . '" data-url="' . esc_url( $sort_url ) . '"' . $sel_class . '>' . esc_html( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) . '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
			// Showcase CTA
			if ( '' !== $showcase_cta_url ) {
				$showcase_count = count( $showcase_ids );
				$html .= '<a id="tm-showcase-btn" href="' . esc_url( $showcase_cta_url ) . '">';
				$html .= '&#9654;&#8201;' . esc_html( sprintf( _n( 'Showcase this %d talent', 'Showcase these %d talents', $showcase_count, 'ecomcine' ), $showcase_count ) );
				$html .= '</a>';
			}
			// Next arrow
			if ( '' !== $next_page_url ) {
				$html .= '<a class="tm-card-arrow" rel="next" href="' . esc_url( $next_page_url ) . '" aria-label="' . esc_attr__( 'Next categories page', 'ecomcine' ) . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg></a>';
			}
			$html .= '</div>';
			// Inline JS: sort dropdown toggle (vanilla, no jQuery dependency)
			$html .= '<script>(function(){var btn=document.getElementById("tm-sort-btn"),drop=document.getElementById("tm-sort-dropdown");if(!btn||!drop)return;btn.addEventListener("click",function(e){e.stopPropagation();drop.classList.toggle("is-open");btn.classList.toggle("is-open");});drop.querySelectorAll("li[data-url]").forEach(function(li){li.addEventListener("click",function(e){e.stopPropagation();window.location.href=li.dataset.url;});});document.addEventListener("click",function(){drop.classList.remove("is-open");btn.classList.remove("is-open");});})();</script>';
		}

		return $html;
	}

	/**
	 * Render basic locations list for onboarding pages.
	 */
	public static function shortcode_locations() {
		$candidate_taxonomies = array( 'location', 'product_location', 'pa_location' );
		$taxonomy = '';
		foreach ( $candidate_taxonomies as $candidate ) {
			if ( taxonomy_exists( $candidate ) ) {
				$taxonomy = $candidate;
				break;
			}
		}

		if ( '' === $taxonomy ) {
			return '<p>No location taxonomy is available yet.</p>';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 30,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '<p>No locations found yet.</p>';
		}

		$html = '<ul class="ecomcine-term-list ecomcine-term-list--locations">';
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Admin action: create default onboarding pages.
	 */
	public static function handle_create_bootstrap_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'ecomcine' ) );
		}

		check_admin_referer( 'ecomcine_bootstrap_pages' );

		$pages = array(
			array(
				'title'     => 'Showcase',
				'slug'      => 'showcase',
				'shortcode' => '[tm_talent_showcase]',
				'template'  => 'tm-store-ui/template-talent-showcase',
			),
			array(
				'title'     => 'Talents',
				'slug'      => 'talents',
				'shortcode' => '[ecomcine-stores]',
				'template'  => 'tm-store-ui/page-platform',
			),
			array(
				'title'     => 'Categories',
				'slug'      => 'categories',
				'shortcode' => '[ecomcine_categories]',
				'template'  => 'tm-store-ui/page-platform',
			),
			array(
				'title'     => 'Locations',
				'slug'      => 'locations',
				'shortcode' => '[vendors_map]',
				'template'  => 'tm-store-ui/page-platform',
			),
		);

		$created = 0;
		$updated = 0;
		foreach ( $pages as $page ) {
			$template = $page['template'] ?? 'tm-store-ui/page-platform';
			$post = get_page_by_path( $page['slug'], OBJECT, 'page' );
			if ( $post ) {
				$content = is_string( $post->post_content ) ? $post->post_content : '';
				if ( false === strpos( $content, $page['shortcode'] ) ) {
					wp_update_post(
						array(
							'ID'           => (int) $post->ID,
							'post_content' => trim( $content . "\n\n" . $page['shortcode'] ),
						)
					);
					$updated++;
				}
				// Always ensure the correct page template is set.
				update_post_meta( (int) $post->ID, '_wp_page_template', $template );
				continue;
			}

			$new_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => $page['shortcode'],
				)
			);

			if ( ! is_wp_error( $new_id ) && $new_id > 0 ) {
				update_post_meta( $new_id, '_wp_page_template', $template );
				$created++;
			}
		}

		$redirect = add_query_arg(
			array(
				'page'                => 'ecomcine-settings',
				'tab'                 => 'settings',
				'ecomcine_pages_done' => 1,
				'ecomcine_created'    => $created,
				'ecomcine_updated'    => $updated,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Admin action: install (if needed) and activate the bundled EcomCine Base Theme.
	 *
	 * The theme files ship inside the plugin at bundled-theme/ and are always
	 * synced to the WP themes directory on each button click, so plugin updates
	 * also update the installed theme files. No external network request needed.
	 */
	public static function handle_install_activate_theme() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'ecomcine' ) );
		}

		check_admin_referer( 'ecomcine_bootstrap_theme' );

		$theme_slug  = 'ecomcine-base';
		$bundled_src = rtrim( ECOMCINE_DIR, '/\\' ) . DIRECTORY_SEPARATOR . 'bundled-theme';
		$themes_root = get_theme_root();
		$dest_dir    = trailingslashit( $themes_root ) . $theme_slug;

		$error_code = '';

		if ( ! is_dir( $bundled_src ) ) {
			$error_code = 'bundled_missing';
		} elseif ( ! wp_mkdir_p( $dest_dir ) ) {
			$error_code = 'dest_mkdir';
		} else {
			// Always sync the full bundled theme tree so plugin updates apply to
			// all theme assets, templates, and helper directories.
			$copy_ok  = true;
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $bundled_src, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $file_info ) {
				$relative = ltrim( str_replace( $bundled_src, '', $file_info->getPathname() ), DIRECTORY_SEPARATOR );
				$target   = $dest_dir . DIRECTORY_SEPARATOR . $relative;

				if ( $file_info->isDir() ) {
					if ( ! wp_mkdir_p( $target ) ) {
						$copy_ok    = false;
						$error_code = 'dest_mkdir';
						break;
					}
					continue;
				}

				if ( ! wp_mkdir_p( dirname( $target ) ) || ! copy( $file_info->getPathname(), $target ) ) {
					$copy_ok    = false;
					$error_code = 'copy_fail';
					break;
				}
			}

			if ( $copy_ok ) {
				// Clear WP theme caches so any newly added template files are discovered.
				if ( function_exists( 'wp_clean_themes_cache' ) ) {
					wp_clean_themes_cache();
				}
				delete_transient( 'theme_roots' );

				$installed = wp_get_theme( $theme_slug );
				if ( ! $installed->exists() ) {
					$error_code = 'verify_fail';
				} else {
					switch_theme( $theme_slug );
				}
			}
		}

		$args = array(
			'page'                => 'ecomcine-settings',
			'tab'                 => 'settings',
			'ecomcine_theme_done' => 1,
			'ecomcine_theme_slug' => '' === $error_code ? $theme_slug : wp_get_theme()->get_stylesheet(),
		);
		if ( '' !== $error_code ) {
			$args['ecomcine_theme_error'] = $error_code;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Default settings payload.
	 */
	public static function defaults() {
		return array(
			'runtime_mode' => self::DEFAULT_RUNTIME_MODE,
			'persons_grid' => array(
				'rows'    => 2,
				'columns' => 4,
			),
			'categories_grid' => array(
				'rows'         => 2,
				'columns'      => 4,
				'card_gap'     => 18,
				'card_radius'  => 24,
				'border_width' => 1,
				'border_style' => 'solid',
				'border_color' => '#D6C3A5',
				'card_background_color' => '#FFF8F0',
				'card_background_hover_color' => '#FFF8F0',
				'title_color' => '#111827',
			),
			'features' => array(
				'media_player'  => true,
				'account_panel' => true,
				'booking_modal' => true,
			),
			'labels' => array(
				'talent_label' => 'Talent',
				'location_label' => 'Location',
			),
			'style_tokens' => array(
				'accent_color'  => '#D4AF37',
				'surface_color' => '#111111',
				'text_color'    => '#F5F5F5',
			),
			'socials' => array(
				'label' => 'Follow us',
				'items' => self::default_header_social_items(),
			),
		);
	}

	/**
	 * Resolve merged settings with defaults.
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			self::log_runtime_mode_issue( 'settings_not_array', $stored );
			$stored = array();
		}

		$defaults = self::defaults();
		$settings = wp_parse_args( $stored, $defaults );
		$settings['runtime_mode'] = self::normalize_runtime_mode(
			isset( $stored['runtime_mode'] ) ? $stored['runtime_mode'] : null,
			'get_settings'
		);

		$settings['features'] = wp_parse_args(
			isset( $stored['features'] ) && is_array( $stored['features'] ) ? $stored['features'] : array(),
			$defaults['features']
		);
		$settings['persons_grid'] = wp_parse_args(
			isset( $stored['persons_grid'] ) && is_array( $stored['persons_grid'] ) ? $stored['persons_grid'] : array(),
			$defaults['persons_grid']
		);
		$settings['categories_grid'] = wp_parse_args(
			isset( $stored['categories_grid'] ) && is_array( $stored['categories_grid'] ) ? $stored['categories_grid'] : array(),
			$defaults['categories_grid']
		);
		$settings['style_tokens'] = wp_parse_args(
			isset( $stored['style_tokens'] ) && is_array( $stored['style_tokens'] ) ? $stored['style_tokens'] : array(),
			$defaults['style_tokens']
		);
		$settings['labels'] = wp_parse_args(
			isset( $stored['labels'] ) && is_array( $stored['labels'] ) ? $stored['labels'] : array(),
			$defaults['labels']
		);
		$settings['socials'] = array(
			'label' => isset( $stored['socials']['label'] ) && is_string( $stored['socials']['label'] ) && '' !== trim( $stored['socials']['label'] )
				? sanitize_text_field( $stored['socials']['label'] )
				: $defaults['socials']['label'],
			'items' => self::normalize_header_social_items(
				isset( $stored['socials']['items'] ) && is_array( $stored['socials']['items'] ) ? array_values( $stored['socials']['items'] ) : array()
			),
		);

		return $settings;
	}

	/**
	 * Default site-wide header social links.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function default_header_social_items(): array {
		return array(
			array(
				'enabled' => true,
				'icon'    => 'youtube',
				'label'   => 'YouTube',
				'url'     => 'https://www.youtube.com/@castingagencyco',
			),
			array(
				'enabled' => true,
				'icon'    => 'facebook',
				'label'   => 'Facebook',
				'url'     => 'https://www.facebook.com/castingagencyco',
			),
			array(
				'enabled' => true,
				'icon'    => 'instagram',
				'label'   => 'Instagram',
				'url'     => 'https://www.instagram.com/castingagencyco',
			),
			array(
				'enabled' => true,
				'icon'    => 'x-twitter',
				'label'   => 'X',
				'url'     => 'https://x.com/castingagencyco',
			),
			array(
				'enabled' => true,
				'icon'    => 'linkedin',
				'label'   => 'LinkedIn',
				'url'     => 'https://www.linkedin.com/company/castingagencyco',
			),
		);
	}

	/**
	 * Supported icon choices for the header socials tab.
	 *
	 * @return array<string,string>
	 */
	public static function get_header_social_icon_choices(): array {
		return array(
			'youtube'   => 'YouTube',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'x-twitter' => 'X',
			'linkedin'  => 'LinkedIn',
		);
	}

	/**
	 * Normalize site-wide header social settings.
	 *
	 * @param array<int,mixed> $items Raw items.
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_header_social_items( array $items ): array {
		$defaults = self::default_header_social_items();
		$choices  = self::get_header_social_icon_choices();
		$output   = array();

		foreach ( array_values( $defaults ) as $index => $default_item ) {
			$raw_item = isset( $items[ $index ] ) && is_array( $items[ $index ] ) ? $items[ $index ] : array();
			$icon     = isset( $raw_item['icon'] ) ? sanitize_key( (string) $raw_item['icon'] ) : (string) $default_item['icon'];
			$label    = isset( $raw_item['label'] ) ? sanitize_text_field( (string) $raw_item['label'] ) : (string) $default_item['label'];
			$url      = isset( $raw_item['url'] ) ? esc_url_raw( (string) $raw_item['url'], array( 'http', 'https' ) ) : (string) $default_item['url'];

			if ( ! isset( $choices[ $icon ] ) ) {
				$icon = (string) $default_item['icon'];
			}

			if ( '' === $label ) {
				$label = (string) $default_item['label'];
			}

			$enabled = array_key_exists( 'enabled', $raw_item )
				? ! empty( $raw_item['enabled'] )
				: ( empty( $raw_item ) ? ! empty( $default_item['enabled'] ) : false );

			$output[] = array(
				'enabled' => (bool) $enabled,
				'icon'    => $icon,
				'label'   => $label,
				'url'     => $url,
			);
		}

		return $output;
	}

	/**
	 * Determine whether a feature toggle is enabled.
	 */
	public static function is_feature_enabled( $feature_key ) {
		$settings = self::get_settings();
		if ( empty( $settings['features'][ $feature_key ] ) ) {
			return false;
		}

		return (bool) $settings['features'][ $feature_key ];
	}

	/**
	 * Get current runtime mode.
	 */
	public static function get_runtime_mode() {
		$settings = self::get_settings();
		return self::normalize_runtime_mode(
			isset( $settings['runtime_mode'] ) ? $settings['runtime_mode'] : null,
			'get_runtime_mode'
		);
	}

	/**
	 * Normalize runtime mode and surface missing/invalid values instead of
	 * silently dropping into a legacy marketplace stack.
	 *
	 * @param mixed  $runtime_mode Raw runtime mode value.
	 * @param string $context      Call-site context for diagnostics.
	 * @return string
	 */
	private static function normalize_runtime_mode( $runtime_mode, string $context ): string {
		if ( ! is_string( $runtime_mode ) || '' === trim( $runtime_mode ) ) {
			self::log_runtime_mode_issue( 'missing_runtime_mode', $context );
			return self::DEFAULT_RUNTIME_MODE;
		}

		$runtime_mode = trim( $runtime_mode );
		if ( ! in_array( $runtime_mode, self::allowed_modes(), true ) ) {
			self::log_runtime_mode_issue( 'invalid_runtime_mode', $runtime_mode . ' @ ' . $context );
			return self::DEFAULT_RUNTIME_MODE;
		}

		return $runtime_mode;
	}

	/**
	 * Emit a one-time per-request log entry for runtime mode issues.
	 *
	 * @param string $reason Diagnostic reason key.
	 * @param mixed  $detail Optional detail payload.
	 * @return void
	 */
	private static function log_runtime_mode_issue( string $reason, $detail = null ): void {
		$key = $reason . '|' . ( is_scalar( $detail ) ? (string) $detail : gettype( $detail ) );
		if ( isset( self::$runtime_mode_log_flags[ $key ] ) ) {
			return;
		}

		self::$runtime_mode_log_flags[ $key ] = true;
		$message = '[EcomCine runtime] ' . $reason . ' -> defaulting runtime_mode to ' . self::DEFAULT_RUNTIME_MODE;
		if ( null !== $detail ) {
			if ( is_scalar( $detail ) ) {
				$message .= ' | detail=' . (string) $detail;
			} else {
				$message .= ' | detail_type=' . gettype( $detail );
			}
		}
		error_log( $message );
	}

	/**
	 * Canonical set of valid mode slugs.
	 */
	public static function allowed_modes(): array {
		return array(
			'wp_cpt',
			'wp_woo',
			'wp_woo_booking',
			'wp_woo_dokan',
			'wp_woo_dokan_booking',
			'wp_fluentcart',
		);
	}

	/**
	 * Return the plugin capabilities required for a given mode.
	 * Keys match EcomCine_Plugin_Capability::snapshot() keys.
	 *
	 * @param string $mode
	 * @return string[]
	 */
	public static function mode_prerequisites( string $mode ): array {
		switch ( $mode ) {
			case 'wp_woo':
				return array( 'woocommerce' );
			case 'wp_woo_booking':
				return array( 'woocommerce', 'wc_bookings' );
			case 'wp_woo_dokan':
				return array( 'woocommerce', 'dokan' );
			case 'wp_woo_dokan_booking':
				return array( 'woocommerce', 'dokan', 'wc_bookings' );
			case 'wp_fluentcart':
				return array( 'fluentcart' );
			default: // wp_cpt
				return array();
		}
	}

	/**
	 * Return true when all prerequisites for a mode are currently satisfied.
	 *
	 * @param string $mode
	 * @return bool
	 */
	public static function mode_prerequisites_met( string $mode ): bool {
		if ( ! class_exists( 'EcomCine_Plugin_Capability', false ) ) {
			return true; // can't check — allow
		}
		$caps = EcomCine_Plugin_Capability::snapshot();
		foreach ( self::mode_prerequisites( $mode ) as $cap ) {
			if ( empty( $caps[ $cap ]['present'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get admin-managed labels.
	 */
	public static function get_labels() {
		$settings = self::get_settings();
		return isset( $settings['labels'] ) && is_array( $settings['labels'] )
			? $settings['labels']
			: self::defaults()['labels'];
	}

	/**
	 * Resolve single label with fallback.
	 */
	public static function get_label( $key, $fallback = '' ) {
		$labels = self::get_labels();
		if ( isset( $labels[ $key ] ) && '' !== trim( (string) $labels[ $key ] ) ) {
			return (string) $labels[ $key ];
		}

		return (string) $fallback;
	}

	/**
	 * Get style token settings.
	 */
	public static function get_style_tokens() {
		$settings = self::get_settings();
		return isset( $settings['style_tokens'] ) && is_array( $settings['style_tokens'] )
			? $settings['style_tokens']
			: self::defaults()['style_tokens'];
	}

	/**
	 * Get site-wide cinematic header social settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_header_socials(): array {
		$settings = self::get_settings();
		return isset( $settings['socials'] ) && is_array( $settings['socials'] )
			? $settings['socials']
			: self::defaults()['socials'];
	}

	/**
	 * Get category grid settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_category_grid_settings(): array {
		$settings = self::get_settings();
		return isset( $settings['categories_grid'] ) && is_array( $settings['categories_grid'] )
			? $settings['categories_grid']
			: self::defaults()['categories_grid'];
	}

	/**
	 * Register option and sanitization callback.
	 */
	public static function register_settings() {
		register_setting(
			'ecomcine_settings_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Add EcomCine settings page.
	 */
	public static function register_menu() {
		add_menu_page(
			'EcomCine Settings',
			'EcomCine',
			'manage_options',
			'ecomcine-settings',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-format-video',
			56
		);
	}

	/**
	 * Sanitize submitted settings.
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		if ( ! is_array( $input ) ) {
			self::log_runtime_mode_issue( 'sanitize_non_array_input', gettype( $input ) );
			return $defaults;
		}

		$current   = self::get_settings();
		$sanitized = wp_parse_args( $current, $defaults );

		$runtime_mode = isset( $input['runtime_mode'] ) ? sanitize_text_field( $input['runtime_mode'] ) : (string) $sanitized['runtime_mode'];

		if ( ! in_array( $runtime_mode, self::allowed_modes(), true ) ) {
			self::log_runtime_mode_issue( 'sanitize_invalid_runtime_mode', $runtime_mode );
			add_settings_error(
				self::OPTION_KEY,
				'runtime_mode_invalid',
				__( 'Runtime mode not saved as requested: the submitted value is missing, obsolete, or invalid. EcomCine fell back to the canonical wp_cpt mode.', 'ecomcine' ),
				'error'
			);
			$runtime_mode = $defaults['runtime_mode'];
		}

		// Reject if prerequisites are not met; keep existing saved mode instead.
		if ( ! self::mode_prerequisites_met( $runtime_mode ) ) {
			$previous = self::get_runtime_mode();
			$runtime_mode = in_array( $previous, self::allowed_modes(), true ) ? $previous : $defaults['runtime_mode'];
			add_settings_error(
				self::OPTION_KEY,
				'runtime_mode_prereqs',
				__( 'Runtime mode not saved: one or more required plugins for the selected mode are not active. Activate the missing plugins first.', 'ecomcine' ),
				'error'
			);
		}

		$sanitized['runtime_mode'] = $runtime_mode;

		$feature_keys = array_keys( $defaults['features'] );
		if ( isset( $input['features'] ) && is_array( $input['features'] ) ) {
			foreach ( $feature_keys as $feature_key ) {
				$sanitized['features'][ $feature_key ] = ! empty( $input['features'][ $feature_key ] );
			}
		}

		if ( isset( $input['persons_grid'] ) && is_array( $input['persons_grid'] ) ) {
			$rows = isset( $input['persons_grid']['rows'] ) ? absint( $input['persons_grid']['rows'] ) : (int) $defaults['persons_grid']['rows'];
			$cols = isset( $input['persons_grid']['columns'] ) ? absint( $input['persons_grid']['columns'] ) : (int) $defaults['persons_grid']['columns'];

			$sanitized['persons_grid']['rows']    = max( 1, min( 12, $rows ) );
			$sanitized['persons_grid']['columns'] = max( 1, min( 6, $cols ) );
		}

		if ( isset( $input['categories_grid'] ) && is_array( $input['categories_grid'] ) ) {
			$rows         = isset( $input['categories_grid']['rows'] ) ? absint( $input['categories_grid']['rows'] ) : (int) $defaults['categories_grid']['rows'];
			$cols         = isset( $input['categories_grid']['columns'] ) ? absint( $input['categories_grid']['columns'] ) : (int) $defaults['categories_grid']['columns'];
			$card_gap     = isset( $input['categories_grid']['card_gap'] ) ? absint( $input['categories_grid']['card_gap'] ) : (int) $defaults['categories_grid']['card_gap'];
			$card_radius  = isset( $input['categories_grid']['card_radius'] ) ? absint( $input['categories_grid']['card_radius'] ) : (int) $defaults['categories_grid']['card_radius'];
			$border_width = isset( $input['categories_grid']['border_width'] ) ? absint( $input['categories_grid']['border_width'] ) : (int) $defaults['categories_grid']['border_width'];
			$border_style = isset( $input['categories_grid']['border_style'] ) ? sanitize_key( $input['categories_grid']['border_style'] ) : (string) $defaults['categories_grid']['border_style'];
			$border_color = isset( $input['categories_grid']['border_color'] ) ? sanitize_hex_color( $input['categories_grid']['border_color'] ) : '';
			$card_background_color = isset( $input['categories_grid']['card_background_color'] ) ? sanitize_hex_color( $input['categories_grid']['card_background_color'] ) : '';
			$card_background_hover_color = isset( $input['categories_grid']['card_background_hover_color'] ) ? sanitize_hex_color( $input['categories_grid']['card_background_hover_color'] ) : '';
			$title_color = isset( $input['categories_grid']['title_color'] ) ? sanitize_hex_color( $input['categories_grid']['title_color'] ) : '';

			if ( ! in_array( $border_style, array( 'none', 'solid', 'dotted', 'dashed', 'double' ), true ) ) {
				$border_style = $defaults['categories_grid']['border_style'];
			}

			$sanitized['categories_grid']['rows']         = max( 1, min( 12, $rows ) );
			$sanitized['categories_grid']['columns']      = max( 1, min( 6, $cols ) );
			$sanitized['categories_grid']['card_gap']     = max( 0, min( 80, $card_gap ) );
			$sanitized['categories_grid']['card_radius']  = max( 0, min( 80, $card_radius ) );
			$sanitized['categories_grid']['border_width'] = max( 0, min( 20, $border_width ) );
			$sanitized['categories_grid']['border_style'] = $border_style;
			$sanitized['categories_grid']['border_color'] = $border_color ? $border_color : $defaults['categories_grid']['border_color'];
			$sanitized['categories_grid']['card_background_color'] = $card_background_color ? $card_background_color : $defaults['categories_grid']['card_background_color'];
			$sanitized['categories_grid']['card_background_hover_color'] = $card_background_hover_color ? $card_background_hover_color : $defaults['categories_grid']['card_background_hover_color'];
			$sanitized['categories_grid']['title_color'] = $title_color ? $title_color : $defaults['categories_grid']['title_color'];
		}

		$style_keys = array_keys( $defaults['style_tokens'] );
		if ( isset( $input['style_tokens'] ) && is_array( $input['style_tokens'] ) ) {
			foreach ( $style_keys as $style_key ) {
				$raw = isset( $input['style_tokens'][ $style_key ] ) ? $input['style_tokens'][ $style_key ] : '';
				$color = sanitize_hex_color( $raw );
				$sanitized['style_tokens'][ $style_key ] = $color ? $color : $defaults['style_tokens'][ $style_key ];
			}
		}

		$label_keys = array_keys( $defaults['labels'] );
		if ( isset( $input['labels'] ) && is_array( $input['labels'] ) ) {
			foreach ( $label_keys as $label_key ) {
				$raw_label = isset( $input['labels'][ $label_key ] ) ? sanitize_text_field( $input['labels'][ $label_key ] ) : '';
				$sanitized['labels'][ $label_key ] = '' !== $raw_label ? $raw_label : $defaults['labels'][ $label_key ];
			}
		}

		if ( isset( $input['socials'] ) && is_array( $input['socials'] ) ) {
			$raw_social_label       = isset( $input['socials']['label'] ) ? sanitize_text_field( (string) $input['socials']['label'] ) : '';
			$sanitized['socials']   = array();
			$sanitized['socials']['label'] = '' !== $raw_social_label ? $raw_social_label : $defaults['socials']['label'];
			$sanitized['socials']['items'] = self::normalize_header_social_items(
				isset( $input['socials']['items'] ) && is_array( $input['socials']['items'] ) ? array_values( $input['socials']['items'] ) : array()
			);
		}

		// Mapbox token — store as-is; only public tokens (pk.*) allowed at this boundary.
		if ( array_key_exists( 'mapbox_token', $input ) ) {
			$mapbox_raw = sanitize_text_field( (string) $input['mapbox_token'] );
			// Only accept Mapbox public tokens (prefix pk.) or empty string.
			if ( '' !== $mapbox_raw && 0 !== strpos( $mapbox_raw, 'pk.' ) ) {
				$mapbox_raw = '';
				add_settings_error(
					self::OPTION_KEY,
					'mapbox_token_invalid',
					__( 'Mapbox token not saved: only public tokens beginning with “pk.” are accepted.', 'ecomcine' ),
					'error'
				);
			}
			$sanitized['mapbox_token'] = $mapbox_raw;
		}

		return $sanitized;
	}

	/**
	 * Print style token CSS variables for frontend consumption.
	 */
	public static function render_style_tokens_css() {
		if ( is_admin() ) {
			return;
		}

		$tokens = self::get_style_tokens();
		$accent = isset( $tokens['accent_color'] ) ? $tokens['accent_color'] : '#D4AF37';
		$surface = isset( $tokens['surface_color'] ) ? $tokens['surface_color'] : '#111111';
		$text = isset( $tokens['text_color'] ) ? $tokens['text_color'] : '#F5F5F5';

		echo '<style id="ecomcine-style-tokens">:root{--ecomcine-accent-color:' . esc_html( $accent ) . ';--ecomcine-surface-color:' . esc_html( $surface ) . ';--ecomcine-text-color:' . esc_html( $text ) . ';}</style>';
	}

	/**
	 * Render a read-only "Plugin Requirements" panel above the settings form.
	 * Shows which third-party plugins are active and what each is required by.
	 */
	public static function render_plugin_capabilities_section(): void {
		if ( ! class_exists( 'EcomCine_Plugin_Capability', false ) ) {
			return;
		}

		$caps   = EcomCine_Plugin_Capability::snapshot();
		$labels = array(
			'woocommerce'    => 'WooCommerce',
			'wc_bookings'    => 'WooCommerce Bookings',
			'dokan'          => 'Dokan Lite',
			'dokan_pro'      => 'Dokan Pro',
			'fluentcart'     => 'FluentCart',
			'fluentcart_pro' => 'FluentCart Pro',
		);
		?>
		<h2>Plugin Requirements</h2>
		<p>Read-only status of plugins that EcomCine features depend on. Install or activate missing plugins to unlock the corresponding features.</p>
		<table class="widefat striped" style="max-width:700px;margin-bottom:24px;">
			<thead>
				<tr>
					<th>Plugin</th>
					<th>Required by</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $caps as $key => $info ) : ?>
					<tr>
						<td><?php echo esc_html( $labels[ $key ] ?? $key ); ?></td>
						<td><code><?php echo esc_html( $info['required_by'] ); ?></code></td>
						<td>
							<?php if ( $info['present'] ) : ?>
								<span style="color:#00a32a;font-weight:600;">&#10003; Active</span>
							<?php else : ?>
								<span style="color:#d63638;">&#10007; Not detected</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render admin settings page (tabbed: Settings | Licensing).
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$settings   = self::get_settings();
		$features   = $settings['features'];
		$persons_grid = isset( $settings['persons_grid'] ) && is_array( $settings['persons_grid'] ) ? $settings['persons_grid'] : self::defaults()['persons_grid'];
		$tokens     = $settings['style_tokens'];
		$labels     = $settings['labels'];
		$socials    = isset( $settings['socials'] ) && is_array( $settings['socials'] ) ? $settings['socials'] : self::defaults()['socials'];
		$created_pages = isset( $_GET['ecomcine_created'] ) ? absint( $_GET['ecomcine_created'] ) : 0;
		$updated_pages = isset( $_GET['ecomcine_updated'] ) ? absint( $_GET['ecomcine_updated'] ) : 0;
		$theme_slug = isset( $_GET['ecomcine_theme_slug'] ) ? sanitize_key( wp_unslash( $_GET['ecomcine_theme_slug'] ) ) : '';
		$theme_error = isset( $_GET['ecomcine_theme_error'] ) ? sanitize_key( wp_unslash( $_GET['ecomcine_theme_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1>EcomCine</h1>

			<?php if ( isset( $_GET['ecomcine_pages_done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Bootstrap pages processed. Created: %d, Updated: %d.', $created_pages, $updated_pages ) ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['ecomcine_theme_done'] ) && '' === $theme_error ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Theme activated: %s', $theme_slug ) ); ?>
				</p></div>
			<?php elseif ( isset( $_GET['ecomcine_theme_done'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Theme setup could not complete (%s). Please install/activate theme manually.', $theme_error ) ); ?>
				</p></div>
			<?php endif; ?>


			<nav class="nav-tab-wrapper" style="margin-bottom:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Settings</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=categories' ) ); ?>"
			   class="nav-tab <?php echo 'categories' === $active_tab ? 'nav-tab-active' : ''; ?>">Categories</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=persons-grid' ) ); ?>"
			   class="nav-tab <?php echo 'persons-grid' === $active_tab ? 'nav-tab-active' : ''; ?>">Persons Grid</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=socials' ) ); ?>"
			   class="nav-tab <?php echo 'socials' === $active_tab ? 'nav-tab-active' : ''; ?>">Socials</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=licensing' ) ); ?>"
			   class="nav-tab <?php echo 'licensing' === $active_tab ? 'nav-tab-active' : ''; ?>">Licensing</a>
		</nav>

		<?php if ( 'categories' === $active_tab ) : ?>
			<?php if ( class_exists( 'EcomCine_Admin_Categories_Tab', false ) ) : ?>
				<?php EcomCine_Admin_Categories_Tab::render(); ?>
			<?php else : ?>
				<p style="margin-top:16px;">Categories module not loaded.</p>
			<?php endif; ?>

		<?php elseif ( 'licensing' === $active_tab ) : ?>
			<?php if ( class_exists( 'EcomCine_Licensing', false ) ) : ?>
				<?php EcomCine_Licensing::render_tab_content(); ?>
			<?php else : ?>
				<p style="margin-top:16px;">Licensing module not loaded.</p>
			<?php endif; ?>

		<?php elseif ( 'persons-grid' === $active_tab ) : ?>
			<div style="margin-top:16px; max-width:700px; background:#fff; border:1px solid #ccd0d4; border-radius:3px; padding:18px 20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
				<h2 style="margin-top:0;">Persons Grid</h2>
				<p class="description" style="margin-bottom:16px;">
					Define how many rows and columns are displayed per Talents page.
					Cards per page are computed as <strong>rows x columns</strong>.
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'ecomcine_settings_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ecomcine-persons-grid-rows">Rows</label></th>
							<td>
								<input id="ecomcine-persons-grid-rows" type="number" min="1" max="12" step="1"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[persons_grid][rows]"
									value="<?php echo esc_attr( (string) ( (int) $persons_grid['rows'] ) ); ?>"
									class="small-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ecomcine-persons-grid-columns">Columns</label></th>
							<td>
								<input id="ecomcine-persons-grid-columns" type="number" min="1" max="6" step="1"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[persons_grid][columns]"
									value="<?php echo esc_attr( (string) ( (int) $persons_grid['columns'] ) ); ?>"
									class="small-text" />
								<p class="description" style="margin:8px 0 0;">Desktop card width follows the selected columns. Tablet/mobile responsive breakpoints still apply.</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Save Persons Grid Settings' ); ?>
				</form>
			</div>

		<?php elseif ( 'socials' === $active_tab ) : ?>
			<?php
			$social_items  = isset( $socials['items'] ) && is_array( $socials['items'] ) ? $socials['items'] : self::default_header_social_items();
			$icon_choices  = self::get_header_social_icon_choices();
			$social_label  = isset( $socials['label'] ) ? (string) $socials['label'] : self::defaults()['socials']['label'];
			?>
			<div style="margin-top:16px; max-width:980px; background:#fff; border:1px solid #ccd0d4; border-radius:3px; padding:18px 20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
				<h2 style="margin-top:0;">Header Socials</h2>
				<p class="description" style="margin-bottom:16px;">Manage the social icons and links rendered in the cinematic header.</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'ecomcine_settings_group' ); ?>
					<table class="form-table" role="presentation" style="max-width:560px; margin-bottom:20px;">
						<tr>
							<th scope="row"><label for="ecomcine-socials-label">Header Label</label></th>
							<td>
								<input id="ecomcine-socials-label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[socials][label]" value="<?php echo esc_attr( $social_label ); ?>" class="regular-text" />
								<p class="description" style="margin:8px 0 0;">Shown to the left of the icon row in the site header.</p>
							</td>
						</tr>
					</table>

					<table class="widefat striped" style="max-width:100%;">
						<thead>
							<tr>
								<th style="width:90px;">Enabled</th>
								<th style="width:180px;">Icon</th>
								<th style="width:220px;">Label</th>
								<th>URL</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $social_items as $index => $item ) : ?>
								<tr>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[socials][items][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?> />
											Show
										</label>
									</td>
									<td>
										<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[socials][items][<?php echo esc_attr( (string) $index ); ?>][icon]">
											<?php foreach ( $icon_choices as $icon_value => $icon_label ) : ?>
												<option value="<?php echo esc_attr( $icon_value ); ?>" <?php selected( isset( $item['icon'] ) ? $item['icon'] : '', $icon_value ); ?>><?php echo esc_html( $icon_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[socials][items][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( isset( $item['label'] ) ? (string) $item['label'] : '' ); ?>" />
									</td>
									<td>
										<input type="url" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[socials][items][<?php echo esc_attr( (string) $index ); ?>][url]" value="<?php echo esc_attr( isset( $item['url'] ) ? (string) $item['url'] : '' ); ?>" placeholder="https://example.com/profile" />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description" style="margin-top:12px;">Rows render in the order shown above. Leave a URL blank or disable a row to hide it from the header.</p>
					<?php submit_button( 'Save Social Settings' ); ?>
				</form>
			</div>

		<?php else : ?>

				<style>
				.ecomcine-settings-grid {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 20px;
					max-width: 1060px;
					margin-top: 20px;
				}
				.ecomcine-settings-card {
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 3px;
					padding: 16px 20px 12px;
					box-shadow: 0 1px 1px rgba(0,0,0,.04);
				}
				.ecomcine-settings-card h2 {
					margin-top: 0;
					padding-bottom: 8px;
					border-bottom: 1px solid #eee;
					font-size: 14px;
					font-weight: 600;
				}
				.ecomcine-settings-card .form-table {
					margin: 0;
				}
				.ecomcine-settings-card .form-table th {
					width: 130px;
					padding-left: 0;
					font-weight: 500;
				}
				.ecomcine-settings-card .form-table td {
					padding-right: 0;
				}
				.ecomcine-settings-full {
					grid-column: 1 / -1;
				}
				.ecomcine-bootstrap-actions {
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 3px;
					padding: 16px 20px 12px;
					box-shadow: 0 1px 1px rgba(0,0,0,.04);
					margin: 0 0 20px;
					max-width: 1060px;
				}
				.ecomcine-bootstrap-actions h2 {
					margin-top: 0;
					padding-bottom: 8px;
					border-bottom: 1px solid #eee;
					font-size: 14px;
					font-weight: 600;
				}
				@media ( max-width: 782px ) {
					.ecomcine-settings-grid { grid-template-columns: 1fr; }
				}
				</style>

				<?php self::render_plugin_capabilities_section(); ?>

				<div class="ecomcine-bootstrap-actions">
					<h2>Site Bootstrap</h2>
					<p>Create baseline pages and activate a supported theme for fresh WordPress installs.</p>
					<p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:10px;">
							<?php wp_nonce_field( 'ecomcine_bootstrap_pages' ); ?>
							<input type="hidden" name="action" value="ecomcine_create_bootstrap_pages" />
							<?php submit_button( 'Create Pages', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Create/patch Showcase, Talents, Categories, and Locations pages with their shortcodes?');" ) ); ?>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
							<?php wp_nonce_field( 'ecomcine_bootstrap_theme' ); ?>
							<input type="hidden" name="action" value="ecomcine_install_activate_theme" />
							<?php submit_button( 'Install + Activate Theme', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Install (if needed) and activate the recommended theme now?');" ) ); ?>
						</form>
					</p>
					<p class="description">Creates baseline pages: Showcase [tm_talent_showcase], Talents [ecomcine-stores], Categories [ecomcine_categories], Locations [ecomcine_locations]. To import demo vendor profiles, use <a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-demo-data' ) ); ?>">EcomCine &rarr; Demo Data</a>.</p>
				</div>

				<form method="post" action="options.php">
					<?php settings_fields( 'ecomcine_settings_group' ); ?>

					<div class="ecomcine-settings-grid">

						<div class="ecomcine-settings-card">
							<h2>Runtime Preset</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ecomcine-runtime-mode">Stack</label></th>
									<td>
										<?php
										$mode_options = array(
											'wp_cpt'               => 'WordPress CPT (Blog / Directory mode)',
											'wp_woo'               => 'WP + WooCommerce (Single Store mode)',
											'wp_woo_booking'       => 'WP + Woo + Booking (Single Store booking mode)',
											'wp_woo_dokan'         => 'WP + Woo + Dokan (Marketplace Mode)',
											'wp_woo_dokan_booking' => 'WP + Woo + Dokan + Booking (Marketplace Mode)',
										);
										?>
										<select id="ecomcine-runtime-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[runtime_mode]">
											<?php foreach ( $mode_options as $slug => $mode_label ) :
												$prereqs_met = self::mode_prerequisites_met( $slug );
											?>
												<option value="<?php echo esc_attr( $slug ); ?>"
													<?php selected( $settings['runtime_mode'], $slug ); ?>
													<?php disabled( false === $prereqs_met && $settings['runtime_mode'] !== $slug ); ?>>
													<?php echo esc_html( $mode_label . ( $prereqs_met ? '' : ' — prerequisites missing' ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<?php
										if ( class_exists( 'EcomCine_Plugin_Capability', false ) ) {
											$missing      = array();
											$caps         = EcomCine_Plugin_Capability::snapshot();
											$prereq_labels = array(
												'woocommerce' => 'WooCommerce',
												'wc_bookings' => 'WooCommerce Bookings',
												'dokan'       => 'Dokan Lite',											'fluentcart'  => 'FluentCart',											);
											foreach ( self::mode_prerequisites( $settings['runtime_mode'] ) as $cap ) {
												if ( empty( $caps[ $cap ]['present'] ) ) {
													$missing[] = $prereq_labels[ $cap ] ?? $cap;
												}
											}
											if ( ! empty( $missing ) ) {
												echo '<p class="description" style="color:#d63638;">Missing: ' . esc_html( implode( ', ', $missing ) ) . '</p>';
											} else {
												echo '<p class="description" style="color:#00a32a;">All prerequisites active.</p>';
											}
										}
										?>
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-card">
							<h2>Feature Toggles</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">Modules</th>
									<td>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][media_player]" value="1" <?php checked( ! empty( $features['media_player'] ) ); ?> />
											Media player
										</label><br />
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][account_panel]" value="1" <?php checked( ! empty( $features['account_panel'] ) ); ?> />
											Account panel
										</label><br />
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][booking_modal]" value="1" <?php checked( ! empty( $features['booking_modal'] ) ); ?> />
											Booking modal
										</label>
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-card">
							<h2>Labels</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ecomcine-talent-label">Talent Label</label></th>
									<td>
										<input id="ecomcine-talent-label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[labels][talent_label]" value="<?php echo esc_attr( $labels['talent_label'] ); ?>" class="regular-text" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="ecomcine-location-label">Location Label</label></th>
									<td>
										<input id="ecomcine-location-label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[labels][location_label]" value="<?php echo esc_attr( $labels['location_label'] ); ?>" class="regular-text" />
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-card">
							<h2>Style Tokens</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ecomcine-accent-color">Accent Color</label></th>
									<td>
										<input id="ecomcine-accent-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][accent_color]" value="<?php echo esc_attr( $tokens['accent_color'] ); ?>" class="regular-text" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="ecomcine-surface-color">Surface Color</label></th>
									<td>
										<input id="ecomcine-surface-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][surface_color]" value="<?php echo esc_attr( $tokens['surface_color'] ); ?>" class="regular-text" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="ecomcine-text-color">Text Color</label></th>
									<td>
										<input id="ecomcine-text-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][text_color]" value="<?php echo esc_attr( $tokens['text_color'] ); ?>" class="regular-text" />
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-card">
							<h2>Mapbox</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ecomcine-mapbox-token">Public Token</label></th>
									<td>
										<input id="ecomcine-mapbox-token" type="text"
											name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mapbox_token]"
											value="<?php echo esc_attr( $settings['mapbox_token'] ?? '' ); ?>"
											class="regular-text"
											placeholder="pk.…" />
										<p class="description">Mapbox public token (pk.…) for geocoding and map embeds. Leave blank to disable.</p>
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-full">
							<?php submit_button( 'Save EcomCine Settings' ); ?>
						</div>

					</div><!-- .ecomcine-settings-grid -->
				</form>

			<?php endif; ?>
		</div>
		<?php
	}
}
