/**
 * GrabWP Tenancy - tenant tag selector.
 *
 * Turns the server-rendered tag checkboxes into chips with a search box.
 * The checkboxes stay in the form (hidden) and remain the posted values, so
 * the form works the same with or without this script. Typing a name that
 * does not exist offers "Add new tag", created through admin-ajax.
 */
(function () {
	'use strict';

	var config = window.grabwpTenantTags || {};
	var i18n = config.i18n || {};
	var counter = 0;

	function format(text, value) {
		// A function replacement keeps "$&" and similar in a tag name literal.
		return String(text || '').replace('%s', function () {
			return value;
		});
	}

	function TagBox(root) {
		this.root = root;
		this.list = root.querySelector('.grabwp-tenant-tags__list');
		if (!this.list) {
			return;
		}
		this.id = 'grabwp-tenant-tags-' + (++counter);
		this.rows = [];
		this.highlight = -1;
		this.busy = false;
		this.build();
		this.bind();
		this.renderChips();
		root.classList.add('is-enhanced');
	}

	TagBox.prototype.build = function () {
		this.box = document.createElement('div');
		this.box.className = 'grabwp-tenant-tags__box';

		this.chips = document.createElement('ul');
		this.chips.className = 'grabwp-tenant-tags__chips';

		this.input = document.createElement('input');
		this.input.type = 'text';
		this.input.className = 'grabwp-tenant-tags__input';
		this.input.placeholder = i18n.search || '';
		this.input.setAttribute('autocomplete', 'off');
		this.input.setAttribute('role', 'combobox');
		this.input.setAttribute('aria-autocomplete', 'list');
		this.input.setAttribute('aria-expanded', 'false');
		this.input.setAttribute('aria-controls', this.id + '-listbox');
		var title = document.getElementById('grabwp-tenant-tags-title');
		if (title) {
			this.input.setAttribute('aria-labelledby', title.id);
		}

		this.dropdown = document.createElement('ul');
		this.dropdown.className = 'grabwp-tenant-tags__dropdown';
		this.dropdown.id = this.id + '-listbox';
		this.dropdown.setAttribute('role', 'listbox');
		this.dropdown.hidden = true;

		this.status = document.createElement('p');
		this.status.className = 'grabwp-tenant-tags__status';
		this.status.setAttribute('aria-live', 'polite');

		this.box.appendChild(this.chips);
		this.box.appendChild(this.input);
		this.box.appendChild(this.dropdown);
		this.root.insertBefore(this.box, this.root.firstChild);
		this.root.appendChild(this.status);
	};

	TagBox.prototype.bind = function () {
		var self = this;

		this.box.addEventListener('click', function (e) {
			if (e.target === self.box || e.target === self.chips) {
				self.input.focus();
			}
		});
		this.input.addEventListener('focus', function () {
			self.show();
		});
		this.input.addEventListener('input', function () {
			self.show();
		});
		this.input.addEventListener('keydown', function (e) {
			self.onKey(e);
		});
		document.addEventListener('click', function (e) {
			if (!self.root.contains(e.target)) {
				self.hide();
			}
		});
	};

	TagBox.prototype.onKey = function (e) {
		if ('ArrowDown' === e.key) {
			e.preventDefault();
			if (this.dropdown.hidden) {
				this.show();
			}
			this.setHighlight(Math.min(this.highlight + 1, this.rows.length - 1));
		} else if ('ArrowUp' === e.key) {
			e.preventDefault();
			this.setHighlight(Math.max(this.highlight - 1, 0));
		} else if ('Enter' === e.key) {
			// Never submit the tenant form from the tag box.
			e.preventDefault();
			if (this.highlight >= 0 && this.rows[this.highlight]) {
				this.pick(this.rows[this.highlight]);
			}
		} else if ('Escape' === e.key) {
			if (!this.dropdown.hidden) {
				e.preventDefault();
				this.hide();
			}
		} else if ('Backspace' === e.key && '' === this.input.value) {
			var selected = this.options().filter(function (o) { return o.input.checked; });
			if (selected.length) {
				this.toggle(selected[selected.length - 1], false);
			}
		}
	};

	/** Every tag as { slug, name, input } from the checkbox list. */
	TagBox.prototype.options = function () {
		var out = [];
		this.list.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
			out.push({
				slug: input.value,
				name: (input.parentNode.textContent || '').trim(),
				input: input
			});
		});
		return out;
	};

	TagBox.prototype.show = function () {
		var query = this.input.value.trim();
		var lower = query.toLowerCase();
		var all = this.options();
		var exact = false;

		this.rows = all.filter(function (o) {
			if (o.name.toLowerCase() === lower) {
				exact = true;
			}
			if (o.input.checked) {
				return false;
			}
			return '' === lower || -1 !== o.name.toLowerCase().indexOf(lower) || -1 !== o.slug.indexOf(lower);
		});
		if ('' !== query && !exact) {
			this.rows.push({ create: true, name: query });
		}

		this.dropdown.textContent = '';
		if (!this.rows.length) {
			var empty = document.createElement('li');
			empty.className = 'grabwp-tenant-tags__empty';
			empty.textContent = i18n.noTags || '';
			this.dropdown.appendChild(empty);
		}

		var self = this;
		this.rows.forEach(function (row, index) {
			var li = document.createElement('li');
			li.className = 'grabwp-tenant-tags__option' + (row.create ? ' grabwp-tenant-tags__option--create' : '');
			li.id = self.id + '-option-' + index;
			li.setAttribute('role', 'option');
			li.textContent = row.create ? format(i18n.addNew, row.name) : row.name;
			li.addEventListener('mousedown', function (e) {
				e.preventDefault();
				self.pick(row);
			});
			self.dropdown.appendChild(li);
		});

		this.dropdown.hidden = false;
		this.input.setAttribute('aria-expanded', 'true');
		this.setHighlight('' === query || !this.rows.length ? -1 : 0);
	};

	TagBox.prototype.hide = function () {
		this.dropdown.hidden = true;
		this.input.setAttribute('aria-expanded', 'false');
		this.input.removeAttribute('aria-activedescendant');
		this.highlight = -1;
	};

	TagBox.prototype.setHighlight = function (index) {
		var items = this.dropdown.querySelectorAll('.grabwp-tenant-tags__option');
		this.highlight = index;
		items.forEach(function (el, i) {
			var on = i === index;
			el.classList.toggle('is-highlighted', on);
			el.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		if (items[index]) {
			this.input.setAttribute('aria-activedescendant', items[index].id);
			items[index].scrollIntoView({ block: 'nearest' });
		} else {
			this.input.removeAttribute('aria-activedescendant');
		}
	};

	TagBox.prototype.pick = function (row) {
		if (row.create) {
			this.create(row.name);
			return;
		}
		this.toggle(row, true);
		this.input.value = '';
		this.show();
		this.input.focus();
	};

	TagBox.prototype.toggle = function (option, checked) {
		option.input.checked = checked;
		this.renderChips();
	};

	TagBox.prototype.create = function (name) {
		var self = this;
		if (this.busy || !config.ajaxUrl) {
			return;
		}
		this.busy = true;
		this.status.textContent = '';
		this.status.classList.remove('is-error');

		var body = new URLSearchParams();
		body.append('action', 'grabwp_tenancy_create_tag');
		body.append('_ajax_nonce', config.nonce || '');
		body.append('name', name);

		fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (response) {
				return response.json();
			})
			.then(function (res) {
				if (!res || !res.success || !res.data || !res.data.slug) {
					throw new Error(res && 'string' === typeof res.data ? res.data : i18n.error);
				}
				var input = self.ensureOption(res.data.slug, res.data.name);
				input.checked = true;
				self.renderChips();
				self.input.value = '';
				self.status.textContent = format(i18n.added, res.data.name);
				self.show();
				self.input.focus();
			})
			.catch(function (err) {
				self.status.textContent = err && err.message ? err.message : (i18n.error || '');
				self.status.classList.add('is-error');
			})
			.then(function () {
				self.busy = false;
			});
	};

	/** The checkbox for a slug, added to the list when the tag is new. */
	TagBox.prototype.ensureOption = function (slug, name) {
		var found = null;
		this.options().forEach(function (o) {
			if (o.slug === slug) {
				found = o.input;
			}
		});
		if (found) {
			return found;
		}

		var none = this.list.querySelector('.grabwp-tenant-tags__none');
		if (none) {
			none.parentNode.removeChild(none);
		}
		var label = document.createElement('label');
		label.className = 'grabwp-tenant-tags__option';
		var input = document.createElement('input');
		input.type = 'checkbox';
		input.name = 'tenant_tags[]';
		input.value = slug;
		label.appendChild(input);
		label.appendChild(document.createTextNode(' ' + name));
		this.list.appendChild(label);
		return input;
	};

	TagBox.prototype.renderChips = function () {
		var self = this;
		this.chips.textContent = '';
		this.options().forEach(function (o) {
			if (!o.input.checked) {
				return;
			}
			var li = document.createElement('li');
			li.className = 'grabwp-tag-chip';

			var text = document.createElement('span');
			text.textContent = o.name;

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'grabwp-tag-chip__remove';
			btn.textContent = '×';
			btn.setAttribute('aria-label', format(i18n.remove, o.name));
			btn.addEventListener('click', function () {
				self.toggle(o, false);
				self.input.focus();
			});

			li.appendChild(text);
			li.appendChild(btn);
			self.chips.appendChild(li);
		});
	};

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-grabwp-tenant-tags]').forEach(function (el) {
			new TagBox(el);
		});
	});
})();
