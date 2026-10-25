<?php
/**
 * Isolated fixtures using real WordPress registration, attribute validation,
 * sanitization and escaping. Theme context is supplied by three small doubles. No database, wp-config.php, or site plugins are loaded.
 * Usage: php tests/verify.php /absolute/path/to/wordpress
 * This does not boot WordPress or test activation, REST requests, or the editor.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$wordpress = $argv[1] ?? '';
if ( ! is_file( $wordpress . '/wp-includes/formatting.php' ) ) {
	fwrite( STDERR, "Usage: php tests/verify.php /absolute/path/to/wordpress\n" );
	exit( 1 );
}

error_reporting( E_ALL );
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

define( 'ABSPATH', rtrim( $wordpress, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', false );
foreach ( array( 'plugin.php', 'compat.php', 'load.php', 'functions.php', 'formatting.php', 'kses.php', 'http.php', 'pomo/translations.php', 'l10n.php' ) as $file ) {
	require_once ABSPATH . WPINC . '/' . $file;
}
// WordPress 6.9+ splits UTF-8 helpers into their own file.
if ( is_file( ABSPATH . WPINC . '/utf8.php' ) ) {
	require_once ABSPATH . WPINC . '/utf8.php';
}
add_filter( 'pre_option_blog_charset', static fn() => 'UTF-8' );
$GLOBALS['l10n']['content-cards'] = new NOOP_Translations();
$GLOBALS['l10n']['default'] = new NOOP_Translations();
$GLOBALS['wp_plugin_paths'] = array();
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
define( 'WP_PLUGIN_URL', 'https://example.test/plugins' );
define( 'WPMU_PLUGIN_URL', 'https://example.test/mu-plugins' );
add_filter( 'pre_option_siteurl', static fn() => 'https://example.test' );
// Asset URL resolution needs theme context, but no theme should be loaded here.
function get_template() { return 'fixture'; }
function get_template_directory() { return ABSPATH . WPINC; }
function is_child_theme() { return false; }
foreach ( array( 'class-wp-error.php', 'class-wp-block-type.php', 'class-wp-block-type-registry.php', 'class-wp-block-metadata-registry.php', 'class-wp-dependency.php', 'class-wp-dependencies.php', 'class-wp-scripts.php', 'class-wp-styles.php', 'functions.wp-scripts.php', 'functions.wp-styles.php', 'link-template.php', 'blocks.php', 'rest-api.php' ) as $file ) {
	if ( is_file( ABSPATH . WPINC . '/' . $file ) ) {
		require_once ABSPATH . WPINC . '/' . $file;
	}
}


function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$GLOBALS['checks'];
}

$GLOBALS['checks'] = 0;
require dirname( __DIR__ ) . '/content-cards.php';
check( false !== has_action( 'init', 'content_cards_register_block' ), 'Initialization hook must exist.' );
do_action( 'init' );
$block = WP_Block_Type_Registry::get_instance()->get_registered( 'content-cards/content-cards' );
check( $block instanceof WP_Block_Type, 'Native block must register without third-party APIs.' );
check( $block->is_dynamic(), 'Native block must have a render callback.' );
check( 3 === $block->api_version, 'Block must use API version 3.' );
check( 'array' === $block->attributes['cards']['type'], 'Cards must be native attributes.' );
check( 1 === $block->attributes['cards']['minItems'] && 3 === $block->attributes['cards']['maxItems'], 'Card schema must define limits.' );
check( '' === $block->render(), 'Default attributes must render nothing until a title exists.' );
check( '' === $block->render( array( 'cards' => 'invalid' ) ), 'WordPress must safely default invalid attribute types.' );
check( count( $block->editor_script_handles ) === 1 && empty( $block->view_script_handles ) && empty( $block->script_handles ), 'JavaScript must be editor-only.' );
$script = wp_scripts()->registered[ $block->editor_script_handles[0] ];
check( str_ends_with( $script->src, '/editor.js' ), 'Editor asset URL must resolve.' );
check( in_array( 'wp-server-side-render', $script->deps, true ), 'Editor preview must declare its WordPress dependency.' );
check( 'content-cards' === $script->textdomain, 'Editor script must support translations.' );
check( count( $block->style_handles ) === 1, 'Shared stylesheet must register.' );

function render_fixture( $rows, $heading = '' ): string {
	return content_cards_render( array( 'cards' => $rows, 'section_heading' => $heading ) );
}

$card = array( 'title' => 'Services & support', 'description' => "Two lines\nof text", 'url' => 'https://example.org/?a=1&b=2', 'link_label' => 'Explore' );
foreach ( array( 1, 2, 3 ) as $count ) {
	$html = render_fixture( array_fill( 0, $count, $card ), 'Our services' );
	check( $count === substr_count( $html, '<li ' ), "Must render $count cards." );
}
$html = render_fixture( array( $card ), 'Our services' );
check( str_contains( $html, '<section ' ) && str_contains( $html, '<h3 ' ), 'Named section must have nested card headings.' );
check( str_contains( $html, 'Services &amp; support' ), 'Text must be escaped.' );
check( str_contains( $html, '&#038;b=2' ), 'URL attributes must be escaped.' );
check( str_contains( $html, ' — Services &amp; support' ), 'Link must include card context.' );
preg_match( '/aria-labelledby="([^"]+)"/', $html, $label );
check( str_contains( $html, 'id="' . $label[1] . '"' ), 'Section label must reference its heading.' );
check( ! str_contains( render_fixture( array( $card ), 'Other' ), $label[1] ), 'Multiple blocks must have unique heading IDs.' );
check( str_contains( render_fixture( array( $card ) ), '<h2 ' ), 'Cards without a section heading must use h2.' );
check( 3 === substr_count( render_fixture( array_fill( 0, 4, $card ) ), '<li ' ), 'Imported content must be capped at three cards.' );
foreach ( array( false, 'invalid', array(), array( null, array( 'title' => ' ' ) ) ) as $rows ) {
	check( '' === render_fixture( $rows ), 'Malformed or empty content must render nothing.' );
}
check( '' === content_cards_render( null ), 'Malformed top-level attributes must be safe.' );
check( '' === content_cards_render( array() ), 'Missing attributes must render nothing.' );
check( str_contains( $block->render( array( 'cards' => array( $card ) ) ), 'Services &amp; support' ), 'Real block API must prepare attributes and render content.' );
foreach ( array( 'javascript:alert(1)', 'data:text/html,test', '/relative', 'https://', array( 'bad' ) ) as $url ) {
	$invalid        = $card;
	$invalid['url'] = $url;
	check( ! str_contains( render_fixture( array( $invalid ) ), '<a ' ), 'Invalid URL must omit the link.' );
}
$invalid               = $card;
$invalid['link_label'] = ' ';
check( ! str_contains( render_fixture( array( $invalid ) ), '<a ' ), 'Missing link label must omit the link.' );
$html = render_fixture( array( array( 'title' => '<img src=x onerror=alert(1)>Safe', 'description' => array(), 'url' => array(), 'link_label' => array() ) ), '<script>alert(1)</script>Heading' );
check( ! str_contains( $html, '<img' ) && ! str_contains( $html, '<script' ), 'HTML injection must be stripped.' );
check( ! str_contains( $html, '<p ' ), 'Invalid description must be omitted.' );

check( ! str_contains( render_fixture( array( array( 'title' => 'No link', 'link_label' => 'Explore' ) ) ), '<a ' ), 'A label without a URL must not create a link.' );
check( str_contains( render_fixture( array( array( 'title' => '0' ) ) ), '>0</h2>' ), 'String zero is a valid title.' );
check( ! str_contains( render_fixture( array( $card ), array( 'bad' ) ), '<section ' ), 'Malformed heading must be omitted.' );
check( '' === $block->render( array( 'cards' => array_fill( 0, 4, $card ) ) ), 'WordPress attribute schema must reject excess rows.' );
check( '' === $block->render( array( 'cards' => array( array( 'title' => array() ) ) ) ), 'WordPress must reject malformed nested attributes.' );

printf( "Passed %d isolated checks using real WordPress APIs (no database or editor).\n", $GLOBALS['checks'] );
