<?php
/**
 * Controller for rendering the Separator block.
 * 
 * @since 3.0.0
 *
 * @package Spectra\Blocks\Separator
 */

use Spectra\Helpers\BlockAttributes;

// Set the attributes with fallback values.
$separator_style = $attributes['separatorStyle'] ?? 'solid';
$separator_align = $attributes['separatorAlign'] ?? 'center';
$separator_color = $attributes['separatorColor'] ?? '';

// Check if it's a custom SVG style.
$is_custom_svg = in_array( $separator_style, array( 'rectangles', 'parallelogram', 'slash', 'leaves' ) );

// Generate separator line styles based on separator type.
$separator_styles = array(
	'margin-left'  => 'left' === $separator_align ? '0' : 'auto',
	'margin-right' => 'left' === $separator_align ? 'auto' : ( 'right' === $separator_align ? '0' : 'auto' ),
);

// Add appearance styles based on separator type.
if ( $is_custom_svg ) {
	// Generate custom SVG pattern with black color for mask.
	$encoded_color                             = rawurlencode( 'black' );
	$custom_svg_patterns                       = array(
		'parallelogram' => "url(\"data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6.4 0H16L9.6 16H0L6.4 0Z' fill='{$encoded_color}'/%3E%3C/svg%3E\")",
		'rectangles'    => "url(\"data:image/svg+xml,%3Csvg width='8' height='16' viewBox='0 0 8 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='8' height='16' fill='{$encoded_color}'/%3E%3C/svg%3E\")",
		'slash'         => "url(\"data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6.29312 16.9999L17 6.29302M14.2931 16.9999L17 14.293M-0.707031 15.9999L16.0002 -0.707153M8.00017 -0.707153L-0.706882 7.9999' stroke='{$encoded_color}'/%3E%3C/svg%3E\")",
		'leaves'        => "url(\"data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cg clip-path='url(%23clip0_2356_5631)'%3E%3Cpath d='M15 1C10.5 1 9 2.5 9 7C13.5 7 15 5.5 15 1Z' stroke='{$encoded_color}'/%3E%3Cpath d='M1 1C5.5 1 7 2.5 7 7C2.5 7 1 5.5 1 1Z' stroke='{$encoded_color}'/%3E%3Cpath d='M15 15C10.5 15 9 13.5 9 9C13.5 9 15 10.5 15 15Z' stroke='{$encoded_color}'/%3E%3Cpath d='M1 15C5.5 15 7 13.5 7 9C2.5 9 1 10.5 1 15Z' stroke='{$encoded_color}'/%3E%3C/g%3E%3Cdefs%3E%3CclipPath id='clip0_2356_5631'%3E%3Crect width='16' height='16' fill='white'/%3E%3C/clipPath%3E%3C/defs%3E%3C/svg%3E\")",
	);
	$separator_styles['background-color']      = $separator_color ? $separator_color : 'currentColor';
	$separator_styles['mask']                  = isset( $custom_svg_patterns[ $separator_style ] ) ? $custom_svg_patterns[ $separator_style ] : '';
	$separator_styles['mask-repeat']           = 'repeat-x';
	$separator_styles['mask-position']         = 'center';
	$separator_styles['mask-size']             = 'var(--spectra-separator-size, 5px) 100%';
	$separator_styles['-webkit-mask']          = isset( $custom_svg_patterns[ $separator_style ] ) ? $custom_svg_patterns[ $separator_style ] : '';
	$separator_styles['-webkit-mask-repeat']   = 'repeat-x';
	$separator_styles['-webkit-mask-position'] = 'center';
	$separator_styles['-webkit-mask-size']     = 'var(--spectra-separator-size, 5px) 100%';
} elseif ( 'solid' === $separator_style ) {
	$separator_styles['background-color'] = $separator_color ? $separator_color : 'currentColor';
} else {
	// For border styles, use CSS variable for height that will be set by responsive controls.
	$separator_styles['border-top']       = 'var(--spectra-separator-height, 3px) ' . $separator_style . ' ' . ( $separator_color ? $separator_color : 'currentColor' );
	$separator_styles['background-color'] = 'transparent';
}

// Convert styles to CSS string.
$separator_css = '';
foreach ( $separator_styles as $property => $value ) {
	if ( 'none' !== $value && '0' !== $value && '' !== $value ) {
		$separator_css .= $property . ': ' . $value . '; ';
	}
}

// Wrapper styles.
$wrapper_styles = array(
	'display'         => 'flex',
	'justify-content' => 'left' === $separator_align ? 'flex-start' : ( 'right' === $separator_align ? 'flex-end' : 'center' ),
);

// Convert wrapper styles to CSS string.
$wrapper_css = '';
foreach ( $wrapper_styles as $property => $value ) {
	$wrapper_css .= $property . ': ' . $value . '; ';
}

// Style and class configurations.
$config = array(
	array( 'key' => 'separatorColor' ),
);

// Custom classes.
$custom_classes = array( 'wp-block-spectra-separator' );

// Element attributes.
$element_attributes = array(
	'style' => $wrapper_css,
);

// Get the block wrapper attributes - responsive CSS is handled automatically.
$wrapper_attributes = BlockAttributes::get_wrapper_attributes( $attributes, $config, $element_attributes, $custom_classes );

// Return the view.
return 'file:./view.php';
