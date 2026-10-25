<?php
/** Native block registration. */

defined( 'ABSPATH' ) || exit;

function content_cards_register_block(): void {
	register_block_type(
		dirname( __DIR__ ) . '/blocks/content-cards',
		array( 'render_callback' => 'content_cards_render' )
	);
}
