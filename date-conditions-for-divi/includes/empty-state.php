<?php
/**
 * Empty state: replace an empty Loop container with a Divi Library item (SPEC 5.3),
 * and keep its styles correct as results change over time (SPEC 6.5, SPIKE-REPORT Q8).
 *
 * @package DCFD
 */

namespace DCFD;

/**
 * A published Divi Library item, or null. Re-checked at render time (SPEC 6.4, 8).
 *
 * @param mixed $id Untrusted ID.
 */
function get_library_item( $id ): ?\WP_Post {
	$id = absint( $id );
	if ( ! $id ) {
		return null;
	}

	$post = get_post( $id );
	if ( ! $post || 'et_pb_layout' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	return $post;
}

/**
 * `divi_loop_rendered_output` fires for every module render, so bail fast.
 *
 * Divi only reaches this filter for parent-level containers; child modules (accordion
 * item, slide, tab) return early when empty (SPIKE-REPORT Q6).
 *
 * @param string $output Rendered container HTML, including Divi's "No Results Found".
 * @param array  $attrs  Block attributes.
 */
function replace_empty_loop( $output, $attrs ) {
	if ( empty( $attrs['__loop_no_results'] ) || empty( $attrs['dcfdEmptyState'] ) ) {
		return $output;
	}

	$item = get_library_item( $attrs['dcfdEmptyState']['innerContent']['desktop']['value'] ?? 0 );
	if ( ! $item ) {
		return $output;
	}

	// A Library item whose own empty Loop points back at itself would recurse forever.
	static $rendering = [];
	if ( isset( $rendering[ $item->ID ] ) ) {
		return $output;
	}

	$rendering[ $item->ID ] = true;
	try {
		// do_blocks(), not et_builder_render_layout(): same styles, no extra wrapper divs (SPIKE-REPORT Q7).
		$html = do_blocks( $item->post_content );
	} finally {
		unset( $rendering[ $item->ID ] );
	}

	return global_data_styles( $item->post_content ) . $html;
}
add_filter( 'divi_loop_rendered_output', __NAMESPACE__ . '\replace_empty_loop', 10, 2 );

/**
 * CSS definitions for the global variables and colours a Library item uses.
 *
 * Divi prints `:root` definitions only for the global variables and colours it detects in
 * the page and Theme Builder content (FrontEnd::enqueue_global_numeric_and_fonts_vars,
 * DynamicAssetsListBuilder), so a Library item swapped in here would reference undefined
 * `var(--gvid-…)` / `var(--gcid-…)` values. Re-printing one Divi already printed is harmless:
 * same value.
 *
 * @param string $content Library item block content.
 */
function global_data_styles( string $content ): string {
	$detect = '\ET\Builder\FrontEnd\Assets\DetectFeature';
	$style  = '\ET\Builder\FrontEnd\Module\Style';
	if ( ! class_exists( $detect ) || ! class_exists( $style ) ) {
		return '';
	}

	$css = '';

	$variable_ids = $detect::get_page_global_variable_ids( $content );
	if ( $variable_ids ) {
		$css .= $style::get_global_numeric_and_fonts_vars_style( $variable_ids );
	}

	$color_ids = array_values( array_unique( array_merge( $detect::get_global_color_ids( $content ), $detect::get_preset_global_color_ids( $content ) ) ) );
	if ( $color_ids ) {
		$css .= $style::get_global_colors_style( $color_ids );
	}

	return '' === $css ? '' : '<style class="dcfd-global-data">' . $css . '</style>';
}

/**
 * Force inline builder CSS on pages using this plugin (approved decision, PLAN.md).
 *
 * Divi caches each page's CSS in a static file and only generates module styles when that
 * file isn't already in use (Module.php). Date rules change results without a save, so a
 * cached file would miss a newly shown Library item's styles, or carry stale per-item CSS
 * such as featured-image backgrounds. Divi handles random-order Loops the same way
 * (StaticCSS::setup_styles_manager). Page caching must be off for these pages anyway.
 *
 * Like Divi, swap in the separate 'builder' / 'module-design' resource rather than changing
 * the 'core' / 'unified' one Divi picked: that resource also carries the Theme Customizer CSS,
 * which Divi skips adding when the unified file already exists (et_divi_add_customizer_css).
 * Forcing it inline dropped the Customizer CSS from the page (0.1.1 and earlier).
 *
 * @param array $data { manager, deferred?, add_hooks }.
 */
function force_inline_styles( $data ) {
	if ( ! is_array( $data ) || empty( $data['manager'] ) || ! request_uses_plugin() ) {
		return $data;
	}
	if ( ! empty( $data['manager']->forced_inline ) || ! function_exists( 'et_core_page_resource_get' ) || ! function_exists( 'et_theme_builder_decorate_page_resource_slug' ) ) {
		return $data; // Divi already forces inline, on its own resource.
	}

	// Same post ID and slug Divi uses for its forced-inline resource (StaticCSS::setup_styles_manager).
	// ponytail: skips Divi's WP-editor-template slug decoration; add if a WP editor template ever uses date rules.
	$post_id = et_core_page_resource_is_singular() ? (int) et_core_page_resource_get_the_ID() : 0;
	$slug    = et_theme_builder_decorate_page_resource_slug( $post_id, 'module-design' );

	foreach ( [ 'manager' => '', 'deferred' => '-deferred' ] as $key => $suffix ) {
		if ( empty( $data[ $key ] ) ) {
			continue;
		}
		$manager                      = et_core_page_resource_get( 'builder', $slug . $suffix, $post_id, 40 );
		$manager->forced_inline       = true;
		$manager->write_file_location = 'footer';
		$manager->set_output_location( 'footer' );
		$data[ $key ] = $manager;
	}
	$data['add_hooks'] = true;

	return $data;
}
add_filter( 'divi_frontend_assets_static_css_module_style_manager', __NAMESPACE__ . '\force_inline_styles' );

/**
 * Whether the current page's content, or any Theme Builder layout used for this request,
 * contains date rules or an empty state.
 */
function request_uses_plugin(): bool {
	static $result = null;
	if ( null !== $result ) {
		return $result;
	}

	$ids = [];

	$post_id = function_exists( 'et_core_page_resource_get_the_ID' ) ? et_core_page_resource_get_the_ID() : get_the_ID();
	if ( $post_id ) {
		$ids[] = (int) $post_id;
	}

	if ( function_exists( 'et_theme_builder_get_template_layouts' ) ) {
		foreach ( (array) et_theme_builder_get_template_layouts() as $layout ) {
			if ( is_array( $layout ) && ! empty( $layout['enabled'] ) && ! empty( $layout['id'] ) ) {
				$ids[] = (int) $layout['id'];
			}
		}
	}

	$result = false;
	foreach ( array_unique( $ids ) as $id ) {
		$content = (string) get_post_field( 'post_content', $id );
		if ( false !== strpos( $content, '"dcfdDateRules"' ) || false !== strpos( $content, '"dcfdEmptyState"' ) ) {
			$result = true;
			break;
		}
	}

	return $result;
}
