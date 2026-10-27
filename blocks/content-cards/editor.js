(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;

	function emptyCard() {
		return { title: '', description: '', url: '', link_label: '' };
	}

	function text(value) {
		return typeof value === 'string' ? value : '';
	}

	wp.blocks.registerBlockType('content-cards/content-cards', {
		edit: function (props) {
			var attributes = props.attributes;
			var rows = Array.isArray(attributes.cards) ? attributes.cards.slice(0, 3) : [];
			var cards = (rows.length ? rows : [emptyCard()]).map(function (row) {
				row = row && typeof row === 'object' ? row : {};
				return {
					title: text(row.title), description: text(row.description),
					url: text(row.url), link_label: text(row.link_label)
				};
			});

			function updateCard(index, field, value) {
				props.setAttributes({ cards: cards.map(function (card, position) {
					return position === index ? Object.assign({}, card, { [field]: value }) : card;
				}) });
			}

			return el('div', wp.blockEditor.useBlockProps(),
				el(TextControl, {
					label: __('Section heading', 'content-cards'),
					value: text(attributes.section_heading),
					onChange: function (value) { props.setAttributes({ section_heading: value }); }
				}),
				cards.map(function (card, index) {
					/* translators: %d: card number. */
					var cardName = sprintf(__('Card %d', 'content-cards'), index + 1);
					return el('fieldset', { key: index },
						el('legend', null, cardName),
						el(TextControl, {
							label: __('Title (required)', 'content-cards'), value: card.title,
							required: true,
							help: card.title.trim() ? undefined : __('Add a title to display this card.', 'content-cards'),
							onChange: function (value) { updateCard(index, 'title', value); }
						}),
						el(TextareaControl, {
							label: __('Description', 'content-cards'), value: card.description,
							help: __('Optional plain text. HTML is not supported.', 'content-cards'),
							onChange: function (value) { updateCard(index, 'description', value); }
						}),
						el(TextControl, {
							label: __('Link URL', 'content-cards'), type: 'url', value: card.url,
							help: __('Optional full HTTP/HTTPS URL. Invalid links are omitted.', 'content-cards'),
							onChange: function (value) { updateCard(index, 'url', value); }
						}),
						card.url.trim() ? el(TextControl, {
							label: __('Link label (required for a link)', 'content-cards'), value: card.link_label,
							required: true,
							help: __('Describe the destination. Links without a label are omitted.', 'content-cards'),
							onChange: function (value) { updateCard(index, 'link_label', value); }
						}) : null,
						el(Button, {
							variant: 'secondary', disabled: cards.length <= 1,
							/* translators: %d: card number. */
							'aria-label': sprintf(__('Remove card %d', 'content-cards'), index + 1),
							onClick: function () {
								if (cards.length > 1) {
									props.setAttributes({ cards: cards.filter(function (_, position) { return position !== index; }) });
								}
							}
						}, __('Remove card', 'content-cards'))
					);
				}),
				el(Button, {
					variant: 'secondary', disabled: cards.length >= 3,
					onClick: function () {
						if (cards.length < 3) { props.setAttributes({ cards: cards.concat([emptyCard()]) }); }
					}
				}, __('Add card', 'content-cards')),
				el('h3', null, __('Preview', 'content-cards')),
				el(wp.components.Disabled, null, el(ServerSideRender, {
					block: 'content-cards/content-cards',
					attributes: { section_heading: text(attributes.section_heading), cards: cards, className: text(attributes.className) },
					EmptyResponsePlaceholder: function () {
						return el('p', null, __('Add a title to preview your cards.', 'content-cards'));
					}
				}))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
