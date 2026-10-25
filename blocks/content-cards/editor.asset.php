<?php
/** Hand-maintained dependencies for the unbundled editor script. */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
	'version'      => filemtime( __DIR__ . '/editor.js' ),
);
