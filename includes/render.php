<?php
/** Defensive value handling and server-side markup. */

defined( 'ABSPATH' ) || exit;

function content_cards_text( $value ): string {
	return is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
}

/** Limit card destinations to absolute web URLs, including on imported content. */
function content_cards_url( $value ): string {
	if ( ! is_string( $value ) ) {
		return '';
	}

	$value = trim( $value );
	if ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! in_array( strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
		return '';
	}

	return esc_url_raw( $value, array( 'http', 'https' ) );
}

function content_cards_render( $attributes ): string {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$heading = content_cards_text( $attributes['section_heading'] ?? '' );
	$rows    = $attributes['cards'] ?? array();
	$cards   = array();

	// Imports and programmatic updates can bypass editor controls.
	foreach ( is_array( $rows ) ? array_slice( $rows, 0, 3 ) : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = content_cards_text( $row['title'] ?? '' );
		if ( '' === $title ) {
			continue;
		}
		$cards[] = array(
			'title'       => $title,
			'description' => is_string( $row['description'] ?? null ) ? trim( sanitize_textarea_field( $row['description'] ) ) : '',
			'url'         => content_cards_url( $row['url'] ?? '' ),
			'link_label'  => content_cards_text( $row['link_label'] ?? '' ),
		);
	}

	if ( ! $cards ) {
		return '';
	}

	$heading_id = wp_unique_id( 'content-cards-heading-' );
	ob_start();
	?>
	<?php if ( '' !== $heading ) : ?>
		<section class="content-cards" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
		<h2 class="content-cards__heading" id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $heading ); ?></h2>
	<?php else : ?>
		<div class="content-cards">
	<?php endif; ?>
		<ul class="content-cards__grid" role="list">
			<?php foreach ( $cards as $card ) : ?>
				<li class="content-cards__card">
					<?php if ( '' !== $heading ) : ?>
						<h3 class="content-cards__title"><?php echo esc_html( $card['title'] ); ?></h3>
					<?php else : ?>
						<h2 class="content-cards__title"><?php echo esc_html( $card['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $card['description'] ) : ?>
						<p class="content-cards__description"><?php echo esc_html( $card['description'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $card['url'] && '' !== $card['link_label'] ) : ?>
						<a class="content-cards__link" href="<?php echo esc_url( $card['url'], array( 'http', 'https' ) ); ?>"><?php echo esc_html( $card['link_label'] ); ?><span class="content-cards__link-context"><?php
							/* translators: %s: card title, appended to the visible link label for screen readers. */
							echo esc_html( sprintf( __( ' — %s', 'content-cards' ), $card['title'] ) );
						?></span></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php if ( '' !== $heading ) : ?>
		</section>
	<?php else : ?>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}
