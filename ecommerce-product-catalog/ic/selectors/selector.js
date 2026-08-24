/**
 * Shared AJAX selector control.
 *
 * Enhances a native <select> into a search-as-you-type field served by the
 * ic_selector_search endpoint. Without JavaScript the native control stays
 * usable, so this file only ever adds behaviour.
 *
 * @package impleCode\IC
 * (c) 2026 Norbert Dreszer - https://implecode.com
 */

(function () {
	'use strict';

	var INITIALISED = 'icSelectorReady';
	var DEBOUNCE = 250;

	/**
	 * Reads a data attribute with a fallback.
	 *
	 * @param {HTMLElement} node    Container element.
	 * @param {string}      name    Attribute name without the data- prefix.
	 * @param {string}      fallback Value used when the attribute is absent.
	 * @return {string} Attribute value.
	 */
	function data(node, name, fallback) {
		var value = node.getAttribute('data-' + name);
		return (value === null || value === '') ? fallback : value;
	}

	/**
	 * Creates an element with an optional class name and text.
	 *
	 * @param {string} tag       Tag name.
	 * @param {string} className Class attribute.
	 * @param {string} text      Text content.
	 * @return {HTMLElement} The new element.
	 */
	function make(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text !== undefined && text !== null) {
			node.textContent = text;
		}
		return node;
	}

	/**
	 * Finds an existing option by value without relying on CSS.escape.
	 *
	 * @param {HTMLSelectElement} select Native select element.
	 * @param {string}            value  Option value.
	 * @return {HTMLOptionElement|null} The matching option.
	 */
	function findOption(select, value) {
		var i;
		for (i = 0; i < select.options.length; i++) {
			if (select.options[i].value === String(value)) {
				return select.options[i];
			}
		}
		return null;
	}

	/**
	 * Selector control instance.
	 *
	 * @param {HTMLElement} container Wrapper carrying the data attributes.
	 * @constructor
	 */
	function Selector(container) {
		this.container = container;
		this.select = container.querySelector('select');
		if (!this.select) {
			return;
		}
		this.source = data(container, 'source', '');
		this.nonce = data(container, 'nonce', '');
		this.ajaxUrl = data(container, 'ajax-url', '');
		this.perPage = parseInt(data(container, 'per-page', '20'), 10) || 20;
		this.placeholder = data(container, 'placeholder', '');
		this.multiple = data(container, 'multiple', '0') === '1' || this.select.multiple;
		this.strings = {
			searching: data(container, 'searching', 'Searching...'),
			empty: data(container, 'empty', 'No matches found.'),
			error: data(container, 'error', 'The list could not be loaded.'),
			more: data(container, 'more', 'Load more')
		};
		this.page = 1;
		this.term = '';
		this.hasMore = false;
		this.loading = false;
		this.requestId = 0;
		this.timer = null;
		this.active = -1;
		this.build();
	}

	Selector.prototype.build = function () {
		var self = this;

		this.select.classList.add('ic-selector-native-hidden');
		this.select.setAttribute('tabindex', '-1');
		this.select.setAttribute('aria-hidden', 'true');

		this.control = make('div', 'ic-selector-control');
		this.tokens = make('div', 'ic-selector-tokens');
		this.input = make('input', 'ic-selector-search');
		this.input.type = 'text';
		this.input.autocomplete = 'off';
		this.input.placeholder = this.placeholder;
		this.input.setAttribute('role', 'combobox');
		this.input.setAttribute('aria-expanded', 'false');
		this.input.setAttribute('aria-autocomplete', 'list');

		this.list = make('ul', 'ic-selector-results');
		this.list.setAttribute('role', 'listbox');
		this.list.hidden = true;

		this.control.appendChild(this.tokens);
		this.control.appendChild(this.input);
		this.container.appendChild(this.control);
		this.container.appendChild(this.list);

		this.syncFromSelect();

		this.input.addEventListener('focus', function () {
			self.open();
		});
		this.input.addEventListener('input', function () {
			self.schedule(self.input.value);
		});
		this.input.addEventListener('keydown', function (event) {
			self.onKeyDown(event);
		});
		this.list.addEventListener('mousedown', function (event) {
			event.preventDefault();
		});
		this.list.addEventListener('click', function (event) {
			var item = event.target.closest ? event.target.closest('[data-value]') : null;
			if (item) {
				self.choose(item.getAttribute('data-value'), item.getAttribute('data-label'));
				return;
			}
			if (event.target.classList.contains('ic-selector-more')) {
				self.fetch(self.term, self.page + 1, true);
			}
		});
		this.list.addEventListener('scroll', function () {
			if (self.hasMore && !self.loading && (self.list.scrollTop + self.list.clientHeight) >= (self.list.scrollHeight - 8)) {
				self.fetch(self.term, self.page + 1, true);
			}
		});
		this.tokens.addEventListener('click', function (event) {
			var remove = event.target.getAttribute('data-remove');
			if (remove !== null) {
				self.deselect(remove);
			}
		});
		document.addEventListener('click', function (event) {
			if (!self.container.contains(event.target)) {
				self.close();
			}
		});
	};

	/**
	 * Mirrors the native select state into the control.
	 */
	Selector.prototype.syncFromSelect = function () {
		var selected = [];
		var i;
		for (i = 0; i < this.select.options.length; i++) {
			if (this.select.options[i].selected && this.select.options[i].value !== '') {
				selected.push({
					value: this.select.options[i].value,
					label: this.select.options[i].textContent
				});
			}
		}
		this.renderTokens(selected);
	};

	/**
	 * Renders the current selection.
	 *
	 * @param {Array} selected Selected value/label pairs.
	 */
	Selector.prototype.renderTokens = function (selected) {
		var self = this;
		this.tokens.textContent = '';

		if (!this.multiple) {
			this.input.value = selected.length ? selected[0].label : '';
			this.container.classList.toggle('ic-selector-filled', selected.length > 0);
			return;
		}

		selected.forEach(function (item) {
			var token = make('span', 'ic-selector-token', item.label);
			var close = make('button', 'ic-selector-token-remove', '×');
			close.type = 'button';
			close.setAttribute('data-remove', item.value);
			close.setAttribute('aria-label', item.label);
			token.appendChild(close);
			self.tokens.appendChild(token);
		});
		this.input.placeholder = selected.length ? '' : this.placeholder;
	};

	/**
	 * Debounces a search.
	 *
	 * @param {string} term Search term.
	 */
	Selector.prototype.schedule = function (term) {
		var self = this;
		window.clearTimeout(this.timer);
		this.timer = window.setTimeout(function () {
			self.fetch(term, 1, false);
		}, DEBOUNCE);
	};

	Selector.prototype.open = function () {
		this.list.hidden = false;
		this.input.setAttribute('aria-expanded', 'true');
		this.container.classList.add('ic-selector-open');
		if (!this.list.childNodes.length) {
			this.fetch(this.multiple ? '' : this.input.value, 1, false);
		}
	};

	Selector.prototype.close = function () {
		this.list.hidden = true;
		this.input.setAttribute('aria-expanded', 'false');
		this.container.classList.remove('ic-selector-open');
		this.active = -1;
		if (!this.multiple) {
			this.syncFromSelect();
		}
	};

	/**
	 * Requests one page of results.
	 *
	 * @param {string}  term   Search term.
	 * @param {number}  page   Page number.
	 * @param {boolean} append Whether to append to the current list.
	 */
	Selector.prototype.fetch = function (term, page, append) {
		var self = this;
		var requestId = ++this.requestId;
		var body = new FormData();

		if (!this.ajaxUrl || !this.source) {
			return;
		}

		this.term = term;
		this.loading = true;
		if (!append) {
			this.page = 1;
			this.list.textContent = '';
			this.list.appendChild(make('li', 'ic-selector-message', this.strings.searching));
		}
		this.list.hidden = false;

		body.append('action', 'ic_selector_search');
		body.append('source', this.source);
		body.append('nonce', this.nonce);
		body.append('s', term || '');
		body.append('page', page);
		body.append('per_page', this.perPage);

		window.fetch(this.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			return response.json().catch(function () {
				return null;
			});
		}).then(function (payload) {
			if (requestId !== self.requestId) {
				return;
			}
			self.loading = false;
			if (!payload || !payload.results) {
				self.renderMessage(self.strings.error);
				return;
			}
			self.page = page;
			self.hasMore = !!(payload.pagination && payload.pagination.more);
			self.renderResults(payload.results, append);
		}).catch(function () {
			if (requestId !== self.requestId) {
				return;
			}
			self.loading = false;
			self.renderMessage(self.strings.error);
		});
	};

	/**
	 * Replaces the list with a single message row.
	 *
	 * @param {string} message Message text.
	 */
	Selector.prototype.renderMessage = function (message) {
		this.list.textContent = '';
		this.list.appendChild(make('li', 'ic-selector-message', message));
	};

	/**
	 * Renders one page of results.
	 *
	 * @param {Array}   results Result rows.
	 * @param {boolean} append  Whether to append.
	 */
	Selector.prototype.renderResults = function (results, append) {
		var self = this;
		var existingMore = this.list.querySelector('.ic-selector-more');

		if (!append) {
			this.list.textContent = '';
		} else if (existingMore) {
			this.list.removeChild(existingMore);
		}

		if (!results.length && !append) {
			this.renderMessage(this.strings.empty);
			return;
		}

		results.forEach(function (result) {
			var item = make('li', 'ic-selector-result');
			item.setAttribute('role', 'option');
			item.setAttribute('data-value', result.id);
			item.setAttribute('data-label', result.text);
			item.appendChild(make('span', 'ic-selector-result-label', result.text));
			item.appendChild(make('span', 'ic-selector-result-id', '#' + result.id));
			self.list.appendChild(item);
		});

		if (this.hasMore) {
			this.list.appendChild(make('li', 'ic-selector-more', this.strings.more));
		}
		this.active = -1;
	};

	/**
	 * Applies a chosen value to the native select.
	 *
	 * @param {string} value Record ID.
	 * @param {string} label Record label.
	 */
	Selector.prototype.choose = function (value, label) {
		var option = findOption(this.select, value);
		if (!option) {
			option = make('option', '', label);
			option.value = value;
			this.select.appendChild(option);
		}
		if (!this.multiple) {
			this.select.value = value;
		} else {
			option.selected = true;
		}
		this.select.dispatchEvent(new Event('change', { bubbles: true }));
		this.input.value = this.multiple ? '' : label;
		this.syncFromSelect();
		this.close();
	};

	/**
	 * Removes a value from the native select.
	 *
	 * @param {string} value Record ID.
	 */
	Selector.prototype.deselect = function (value) {
		var option = findOption(this.select, value);
		if (option) {
			option.selected = false;
		}
		this.select.dispatchEvent(new Event('change', { bubbles: true }));
		this.syncFromSelect();
	};

	/**
	 * Handles keyboard navigation.
	 *
	 * @param {KeyboardEvent} event Key event.
	 */
	Selector.prototype.onKeyDown = function (event) {
		var items = this.list.querySelectorAll('.ic-selector-result');

		if (event.key === 'Escape') {
			this.close();
			return;
		}
		if (event.key === 'Backspace' && this.multiple && this.input.value === '') {
			var tokens = this.tokens.querySelectorAll('[data-remove]');
			if (tokens.length) {
				this.deselect(tokens[tokens.length - 1].getAttribute('data-remove'));
			}
			return;
		}
		if (!items.length) {
			return;
		}
		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			this.active += (event.key === 'ArrowDown') ? 1 : -1;
			if (this.active < 0) {
				this.active = items.length - 1;
			}
			if (this.active >= items.length) {
				this.active = 0;
			}
			for (var i = 0; i < items.length; i++) {
				items[i].classList.toggle('ic-selector-active', i === this.active);
			}
			items[this.active].scrollIntoView({ block: 'nearest' });
			return;
		}
		if (event.key === 'Enter' && this.active > -1) {
			event.preventDefault();
			this.choose(items[this.active].getAttribute('data-value'), items[this.active].getAttribute('data-label'));
		}
	};

	/**
	 * Enhances every selector container that is not yet initialised.
	 *
	 * @param {ParentNode} scope Optional DOM scope.
	 */
	function init(scope) {
		var root = scope || document;
		var nodes = root.querySelectorAll('.ic-selector-enhanced[data-source]');
		Array.prototype.forEach.call(nodes, function (node) {
			if (node.dataset[INITIALISED]) {
				return;
			}
			node.dataset[INITIALISED] = '1';
			new Selector(node);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			init(document);
		});
	} else {
		init(document);
	}

	window.icSelector = { init: init };
}());
