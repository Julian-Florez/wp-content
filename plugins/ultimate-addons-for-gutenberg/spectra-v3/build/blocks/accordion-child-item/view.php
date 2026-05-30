<?php
/**
 * View for rendering the block.
 * 
 * @since 3.0.0
 * 
 * @package Spectra\Blocks\AccordionChildItem
 */

use Spectra\Helpers\HtmlSanitizer;

?>
<div
	<?php echo wp_kses_data( $wrapper_attributes ); ?>
	<?php echo wp_kses_data( wp_interactivity_data_wp_context( $accordion_item_contexts, 'spectra/accordion' ) ); ?>
	data-wp-watch--toggle="spectra/accordion::callbacks.isToggled"
	data-wp-watch--animate="spectra/accordion::callbacks.isAnimated"
>
	<?php HtmlSanitizer::render( $content ); ?>
</div>
