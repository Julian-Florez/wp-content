<?php
/**
 * View for rendering the block.
 * 
 * @since 3.0.0
 *
 * @package Spectra\Blocks\Separator
 */

?>
<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<div class="spectra-separator-line" style="<?php echo esc_attr( $separator_css ); ?>"></div>
</div>
