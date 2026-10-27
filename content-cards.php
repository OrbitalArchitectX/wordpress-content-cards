<?php
/**
 * Plugin Name: Content Cards
 * Description: A small, accessible native Content Cards block.
 * Version: 1.2.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: content-cards
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/block.php';

add_action( 'init', 'content_cards_register_block' );
