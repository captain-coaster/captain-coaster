import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        multiple: Boolean,
        placeholder: String,
        searchable: Boolean,
        maximumSelectionLength: Number,
    };

    connect() {
        if (this.element.tagName !== 'SELECT') return;
        this.originalSelect = this.element;
        this.selectedValues = new Set();
        this.options = [];
        this.isOpen = false;
        this.init();
    }

    disconnect() {
        if (this.container) this.container.remove();
        if (this.originalSelect) this.originalSelect.style.display = '';
        document.removeEventListener('click', this.boundOutsideClick);
    }

    init() {
        this.options = Array.from(this.originalSelect.options).map((o) => ({
            value: o.value,
            text: o.textContent.trim(),
            selected: o.selected,
        }));
        this.options.forEach((o) => {
            if (o.selected && o.value) this.selectedValues.add(o.value);
        });
        this.build();
        this.bindEvents();
        this.refresh();
    }

    build() {
        this.originalSelect.style.display = 'none';

        this.container = document.createElement('div');
        this.container.style.cssText = 'position:relative;width:100%';

        this.display = document.createElement('div');
        this.display.style.cssText = `
            display:flex;flex-wrap:wrap;align-items:center;gap:6px;
            min-height:42px;width:100%;padding:8px 12px;
            background:#fff;border:1px solid #d4d4d4;border-radius:8px;
            cursor:pointer;font-size:14px;box-sizing:border-box;
        `.replace(/\s+/g, '');

        this.dropdown = document.createElement('div');
        this.dropdown.style.cssText = `
            position:absolute;top:100%;left:0;right:0;margin-top:4px;
            background:#fff;border:1px solid #e5e5e5;border-radius:8px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:1000;display:none;
        `.replace(/\s+/g, '');

        if (this.searchableValue) {
            const sw = document.createElement('div');
            sw.style.cssText = 'padding:8px;border-bottom:1px solid #e5e5e5';
            this.searchInput = document.createElement('input');
            this.searchInput.type = 'text';
            this.searchInput.placeholder = 'Search...';
            this.searchInput.style.cssText = `
                width:100%;padding:8px 12px;border:1px solid #e5e5e5;
                border-radius:6px;font-size:14px;outline:none;box-sizing:border-box;
            `.replace(/\s+/g, '');
            this.searchInput.addEventListener('input', (e) =>
                this.filter(e.target.value)
            );
            sw.appendChild(this.searchInput);
            this.dropdown.appendChild(sw);
        }

        this.list = document.createElement('div');
        this.list.style.cssText =
            'max-height:200px;overflow-y:auto;padding:4px 0';
        this.options.forEach((o) => {
            if (!o.value) return;
            const el = document.createElement('div');
            el.dataset.value = o.value;
            el.textContent = o.text;
            el.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:14px';
            el.addEventListener('click', () => this.select(o.value));
            el.addEventListener('mouseenter', () => {
                if (!el.dataset.disabled) el.style.background = '#f5f5f5';
            });
            el.addEventListener('mouseleave', () => {
                el.style.background = this.selectedValues.has(o.value)
                    ? '#e0efff'
                    : '';
            });
            this.list.appendChild(el);
        });

        this.dropdown.appendChild(this.list);
        this.container.appendChild(this.display);
        this.container.appendChild(this.dropdown);
        this.originalSelect.parentNode.insertBefore(
            this.container,
            this.originalSelect.nextSibling
        );
    }

    bindEvents() {
        this.display.addEventListener('click', () => this.toggle());
        this.boundOutsideClick = (e) => {
            if (!this.container.contains(e.target)) this.close();
        };
        document.addEventListener('click', this.boundOutsideClick);
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.dropdown.style.display = 'block';
        this.display.style.borderColor = '#0c8ce9';
        this.display.style.boxShadow = '0 0 0 2px rgba(12,140,233,0.2)';
        if (this.searchInput) setTimeout(() => this.searchInput.focus(), 10);
    }

    close() {
        this.isOpen = false;
        this.dropdown.style.display = 'none';
        this.display.style.borderColor = '#d4d4d4';
        this.display.style.boxShadow = '';
        if (this.searchInput) {
            this.searchInput.value = '';
            this.filter('');
        }
    }

    select(value) {
        if (this.multipleValue) {
            if (this.selectedValues.has(value)) {
                this.selectedValues.delete(value);
            } else {
                if (
                    this.hasMaximumSelectionLengthValue &&
                    this.selectedValues.size >= this.maximumSelectionLengthValue
                )
                    return;
                this.selectedValues.add(value);
            }
        } else {
            this.selectedValues.clear();
            this.selectedValues.add(value);
            this.close();
        }
        this.refresh();
        this.sync();
    }

    remove(value, e) {
        e.stopPropagation();
        this.selectedValues.delete(value);
        this.refresh();
        this.sync();
    }

    refresh() {
        this.display.innerHTML = '';
        if (this.selectedValues.size === 0) {
            const ph = document.createElement('span');
            ph.textContent = this.placeholderValue || 'Select...';
            ph.style.color = '#a3a3a3';
            this.display.appendChild(ph);
        } else if (this.multipleValue) {
            this.selectedValues.forEach((v) => {
                const o = this.options.find((x) => x.value === v);
                if (!o) return;
                const tag = document.createElement('span');
                tag.style.cssText = `
                    display:inline-flex;align-items:center;gap:4px;
                    padding:2px 8px;background:#e0efff;color:#0066cc;
                    border-radius:4px;font-size:13px;font-weight:500;
                `.replace(/\s+/g, '');
                tag.innerHTML = `<span>${o.text}</span>`;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = '×';
                btn.style.cssText =
                    'background:none;border:none;padding:0;cursor:pointer;font-size:16px;color:#0066cc';
                btn.addEventListener('click', (e) => this.remove(v, e));
                tag.appendChild(btn);
                this.display.appendChild(tag);
            });
        } else {
            const v = Array.from(this.selectedValues)[0];
            const o = this.options.find((x) => x.value === v);
            if (o) {
                const t = document.createElement('span');
                t.textContent = o.text;
                this.display.appendChild(t);
            }
        }
        this.refreshOptions();
    }

    refreshOptions() {
        const max =
            this.hasMaximumSelectionLengthValue &&
            this.selectedValues.size >= this.maximumSelectionLengthValue;
        this.list.querySelectorAll('[data-value]').forEach((el) => {
            const sel = this.selectedValues.has(el.dataset.value);
            el.style.background = sel ? '#e0efff' : '';
            el.style.fontWeight = sel ? '500' : '';
            if (!sel && max && this.multipleValue) {
                el.style.opacity = '0.5';
                el.style.cursor = 'not-allowed';
                el.dataset.disabled = 'true';
            } else {
                el.style.opacity = '';
                el.style.cursor = 'pointer';
                delete el.dataset.disabled;
            }
        });
    }

    filter(q) {
        const lq = q.toLowerCase();
        this.list.querySelectorAll('[data-value]').forEach((el) => {
            el.style.display = el.textContent.toLowerCase().includes(lq)
                ? ''
                : 'none';
        });
    }

    sync() {
        Array.from(this.originalSelect.options).forEach((o) => {
            o.selected = this.selectedValues.has(o.value);
        });
        this.originalSelect.dispatchEvent(
            new Event('change', { bubbles: true })
        );
    }
}
