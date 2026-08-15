import BaseController from './base_controller.js';
import { getComponent } from '@symfony/ux-live-component';
import { scaleUp } from '../js/utils/animation.js';

/**
 * StarRating — presentational half-star widget.
 * Hover/drag preview is always client-side; commit behaviour depends on `mode`:
 *   live       → call the parent Live Component's `rate` action
 *   standalone → POST to rating_edit (and emit ride:marked / rating:changed)
 *   form       → write the value into a hidden form field
 * Read-only (display): set readonly=true.
 */
export default class extends BaseController {
    static targets = ['star', 'value', 'clear'];
    static values = {
        value: Number,
        size: { type: Number, default: 20 },
        readonly: Boolean,
        showValue: Boolean,
        mode: { type: String, default: 'standalone' },
        coasterId: Number,
        ratingId: Number,
        formFieldId: String,
        locale: String,
    };

    connect() {
        this.render();
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'inline-flex items-center gap-0.5';
        for (let i = 1; i <= 5; i++) this.wrapper.appendChild(this.createStar(i));
        // Clear only the stars container (keep an external value target if present)
        const old = this.element.querySelector('[data-star-rating-stars]');
        if (old) old.remove();
        this.wrapper.setAttribute('data-star-rating-stars', '');
        this.element.prepend(this.wrapper);
        this.updateDisplay(this.valueValue || 0);
        this.setupEvents();
        this.toggleClear();
    }

    createStar(value) {
        const star = document.createElement('button');
        star.type = 'button';
        star.dataset.starRatingTarget = 'star';
        star.dataset.value = value;
        star.className =
            'inline-flex items-center justify-center bg-transparent border-0 p-0.5 transition-transform duration-150 ' +
            (this.readonlyValue ? 'cursor-default' : 'cursor-pointer');
        star.setAttribute('aria-label', 'Rate ' + value + ' stars');
        if (!this.readonlyValue) {
            star.addEventListener('mouseenter', () => star.classList.add('scale-110'));
            star.addEventListener('mouseleave', () => star.classList.remove('scale-110'));
        }
        return star;
    }

    getStarSVG(type, size) {
        size = size || 20;
        const cls =
            type === 'empty'
                ? 'text-neutral-300 dark:text-neutral-600'
                : 'text-cc-warm-400';
        if (type === 'filled') {
            return (
                '<svg width="' +
                size +
                '" height="' +
                size +
                '" viewBox="0 0 24 24" fill="currentColor" class="' +
                cls +
                '" xmlns="http://www.w3.org/2000/svg"><path d="m8.243 7.34l-6.38.925l-.113.023a1 1 0 0 0-.44 1.684l4.622 4.499l-1.09 6.355l-.013.11a1 1 0 0 0 1.464.944l5.706-3l5.693 3l.1.046a1 1 0 0 0 1.352-1.1l-1.091-6.355l4.624-4.5l.078-.085a1 1 0 0 0-.633-1.62l-6.38-.926l-2.852-5.78a1 1 0 0 0-1.794 0z"/></svg>'
            );
        }
        if (type === 'half') {
            return (
                '<svg width="' +
                size +
                '" height="' +
                size +
                '" viewBox="0 0 24 24" fill="currentColor" class="' +
                cls +
                '" xmlns="http://www.w3.org/2000/svg"><path d="M12 1a1 1 0 0 1 .823.443l.067.116l2.852 5.781l6.38.925c.741.108 1.08.94.703 1.526l-.07.095l-.078.086l-4.624 4.499l1.09 6.355a1 1 0 0 1-1.249 1.135l-.101-.035l-.101-.046l-5.693-3l-5.706 3q-.158.082-.32.106l-.106.01a1.003 1.003 0 0 1-1.038-1.06l.013-.11l1.09-6.355l-4.623-4.5a1 1 0 0 1 .328-1.647l.113-.036l.114-.023l6.379-.925l2.853-5.78A.97.97 0 0 1 12 1m0 3.274V16.75a1 1 0 0 1 .239.029l.115.036l.112.05l4.363 2.299l-.836-4.873a1 1 0 0 1 .136-.696l.07-.099l.082-.09l3.546-3.453l-4.891-.708a1 1 0 0 1-.62-.344l-.073-.097l-.06-.106z"/></svg>'
            );
        }
        return (
            '<svg width="' +
            size +
            '" height="' +
            size +
            '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="' +
            cls +
            '" xmlns="http://www.w3.org/2000/svg"><path d="m12 17.75l-6.172 3.245l1.179-6.873l-5-4.867l6.9-1l3.086-6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/></svg>'
        );
    }

    getValueFromClick(star, event) {
        const base = parseInt(star.dataset.value, 10);
        const rect = star.getBoundingClientRect();
        const x = event.clientX - rect.left;
        if (x < rect.width / 2) {
            return base - 0.5;
        }
        return base;
    }

    getValueFromHover(star, event) {
        const base = parseInt(star.dataset.value, 10);
        const rect = star.getBoundingClientRect();
        const x = event.clientX - rect.left;
        if (x < rect.width / 2) {
            return base - 0.5;
        }
        return base;
    }

    updateDisplay(value) {
        const size = this.sizeValue;
        this.starTargets.forEach((star, index) => {
            const starValue = index + 1;
            let type = 'empty';

            if (value >= starValue) {
                type = 'filled';
            } else if (value >= starValue - 0.5) {
                type = 'half';
            }

            star.innerHTML = this.getStarSVG(type, size);
        });
        if (this.hasValueTarget && this.showValueValue) {
            this.valueTarget.textContent = value > 0 ? value.toFixed(1) : '–';
        }
    }

    setupEvents() {
        if (this.readonlyValue) return;
        this.starTargets.forEach((star) => {
            star.addEventListener('click', (e) => {
                e.preventDefault();
                this.commit(this.getValueFromClick(star, e));
            });
            star.addEventListener('mousemove', (e) =>
                this.updateDisplay(this.getValueFromHover(star, e))
            );
        });
        this.wrapper.addEventListener('mouseleave', () =>
            this.updateDisplay(this.valueValue || 0)
        );
    }

    toggleClear() {
        if (!this.hasClearTarget) return;
        const show = this.modeValue === 'standalone' && !this.readonlyValue && (this.valueValue || 0) > 0;
        this.clearTarget.classList.toggle('hidden', !show);
    }

    async clear(event) {
        event?.preventDefault();
        if (!this.ratingIdValue) return;
        try {
            const url = Routing.generate('rating_clear', { id: this.ratingIdValue, _locale: this.localeValue });
            const response = await fetch(url.replace(/^http:/, 'https:'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: this.addCsrfToBody(''),
            });
            if (!response.ok) throw new Error('Failed to clear rating');
            this.valueValue = 0;
            this.updateDisplay(0);
            this.toggleClear();
            document.dispatchEvent(new CustomEvent('rating:changed', {
                detail: { coasterId: this.coasterIdValue, hasRating: false },
            }));
            document.dispatchEvent(new CustomEvent('rating:deleted', {
                detail: { coasterId: this.coasterIdValue, riddenCoasterId: this.ratingIdValue },
            }));
        } catch (e) {
            console.error(e);
            this.showError('Unable to clear rating.');
        }
    }

    async commit(value) {
        if (this._committing) return;
        if (value === this.valueValue) return;
        this._committing = true;
        const previous = this.valueValue;
        this.valueValue = value;
        this.updateDisplay(value);
        const clickedStar = this.starTargets[Math.ceil(value) - 1];
        if (clickedStar) scaleUp(clickedStar, 220);
        try {
            if (this.modeValue === 'form') {
                const field = document.getElementById(this.formFieldIdValue);
                if (field) field.value = value;
                else console.warn('[star-rating] form field not found:', this.formFieldIdValue);
                return;
            }
            if (this.modeValue === 'live') {
                const liveEl = this.element.closest('[data-controller~="live"]');
                if (!liveEl) throw new Error('[star-rating] No ancestor Live Component found');
                const component = await getComponent(liveEl);
                component.action('rate', { value });
                return;
            }
            // standalone
            await this.persistStandalone(value, previous);
        } catch (err) {
            console.error('StarRating commit failed:', err);
            this.valueValue = previous;
            this.updateDisplay(previous || 0);
            this.showError('Unable to save rating. Please try again.');
        } finally {
            this._committing = false;
        }
    }

    async persistStandalone(value, previous) {
        const wasNew = !this.ratingIdValue;
        const url = Routing.generate('rating_edit', {
            id: this.coasterIdValue,
            _locale: this.localeValue,
        });
        const response = await fetch(url.replace(/^http:/, 'https:'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: this.addCsrfToBody('value=' + value),
        });
        if (!response.ok) throw new Error('Failed to save rating');
        const data = await response.json();
        if (data.id) this.ratingIdValue = data.id;
        if (wasNew) {
            document.dispatchEvent(new CustomEvent('ride:marked', {
                detail: { coasterId: this.coasterIdValue, riddenCoasterId: this.ratingIdValue },
            }));
        }
        document.dispatchEvent(new CustomEvent('rating:changed', {
            detail: { coasterId: this.coasterIdValue, hasRating: true },
        }));
        this.dispatch(wasNew ? 'created' : 'updated', {
            detail: { ratingId: data.id || this.ratingIdValue }, bubbles: true,
        });
        this.toggleClear();
    }
}
