import BaseController from './base_controller.js';

/**
 * Rating Controller
 * Renders interactive star rating widget
 * Supports both form mode (updates hidden field) and API mode (saves to backend)
 */
export default class extends BaseController {
    static targets = ['star'];
    static values = {
        coasterId: Number,
        currentValue: Number,
        ratingId: Number,
        locale: String,
        readonly: Boolean,
        formFieldId: String,
    };

    connect() {
        this.render();
    }

    render() {
        // Clear any existing content
        this.element.innerHTML = '';

        // Create wrapper with inline flex layout (guaranteed to work)
        this.wrapper = document.createElement('div');
        this.wrapper.style.display = 'inline-flex';
        this.wrapper.style.alignItems = 'center';
        this.wrapper.style.gap = '2px';

        // Create 5 stars
        for (let i = 1; i <= 5; i++) {
            const star = this.createStar(i);
            this.wrapper.appendChild(star);
        }

        this.element.appendChild(this.wrapper);
        this.updateDisplay(this.currentValueValue || 0);
        this.setupEvents();
    }

    createStar(value) {
        const star = document.createElement('button');
        star.type = 'button';
        star.dataset.ratingTarget = 'star';
        star.dataset.value = value;
        star.style.background = 'none';
        star.style.border = 'none';
        star.style.padding = '2px';
        star.style.cursor = this.readonlyValue ? 'default' : 'pointer';
        star.style.transition = 'transform 150ms ease';
        star.style.display = 'flex';
        star.style.alignItems = 'center';
        star.style.justifyContent = 'center';
        star.setAttribute('aria-label', `Rate ${value} stars`);

        if (!this.readonlyValue) {
            star.addEventListener('mouseenter', () => {
                star.style.transform = 'scale(1.15)';
            });
            star.addEventListener('mouseleave', () => {
                star.style.transform = 'scale(1)';
            });
        }

        return star;
    }

    getStarSVG(type, size = 32) {
        const colors = {
            filled: '#f1d065', // cc-warm-400
            half: '#f1d065',
            empty: '#d4d4d4', // neutral-300
        };

        const color = colors[type] || colors.empty;

        if (type === 'filled') {
            return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="${color}" xmlns="http://www.w3.org/2000/svg">
                <path d="m8.243 7.34l-6.38.925l-.113.023a1 1 0 0 0-.44 1.684l4.622 4.499l-1.09 6.355l-.013.11a1 1 0 0 0 1.464.944l5.706-3l5.693 3l.1.046a1 1 0 0 0 1.352-1.1l-1.091-6.355l4.624-4.5l.078-.085a1 1 0 0 0-.633-1.62l-6.38-.926l-2.852-5.78a1 1 0 0 0-1.794 0z"/>
            </svg>`;
        } else if (type === 'half') {
            return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="${color}" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1a1 1 0 0 1 .823.443l.067.116l2.852 5.781l6.38.925c.741.108 1.08.94.703 1.526l-.07.095l-.078.086l-4.624 4.499l1.09 6.355a1 1 0 0 1-1.249 1.135l-.101-.035l-.101-.046l-5.693-3l-5.706 3q-.158.082-.32.106l-.106.01a1.003 1.003 0 0 1-1.038-1.06l.013-.11l1.09-6.355l-4.623-4.5a1 1 0 0 1 .328-1.647l.113-.036l.114-.023l6.379-.925l2.853-5.78A.97.97 0 0 1 12 1m0 3.274V16.75a1 1 0 0 1 .239.029l.115.036l.112.05l4.363 2.299l-.836-4.873a1 1 0 0 1 .136-.696l.07-.099l.082-.09l3.546-3.453l-4.891-.708a1 1 0 0 1-.62-.344l-.073-.097l-.06-.106z"/>
            </svg>`;
        } else {
            return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2" xmlns="http://www.w3.org/2000/svg">
                <path d="m12 17.75l-6.172 3.245l1.179-6.873l-5-4.867l6.9-1l3.086-6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/>
            </svg>`;
        }
    }

    setupEvents() {
        if (this.readonlyValue) return;

        // Click on individual stars
        this.starTargets.forEach((star) => {
            star.addEventListener('click', (e) => {
                e.preventDefault();
                const value = parseInt(star.dataset.value, 10);
                this.setRating(value);
            });
        });

        // Hover preview
        this.wrapper.addEventListener('mouseleave', () => {
            this.updateDisplay(this.currentValueValue || 0);
        });

        this.starTargets.forEach((star) => {
            star.addEventListener('mouseenter', () => {
                const value = parseInt(star.dataset.value, 10);
                this.updateDisplay(value);
            });
        });
    }

    updateDisplay(value) {
        this.starTargets.forEach((star, index) => {
            const starValue = index + 1;
            let type = 'empty';

            if (value >= starValue) {
                type = 'filled';
            } else if (value >= starValue - 0.5) {
                type = 'half';
            }

            star.innerHTML = this.getStarSVG(type);
        });
    }

    async setRating(value) {
        if (value === this.currentValueValue) return;

        const previousValue = this.currentValueValue;
        this.currentValueValue = value;
        this.updateDisplay(value);

        // Form mode: update hidden field
        if (this.hasFormFieldIdValue) {
            const field = document.getElementById(this.formFieldIdValue);
            if (field) {
                field.value = value;
            }
            return;
        }

        // API mode: save to backend
        const wasNew = !this.ratingIdValue;

        try {
            const url = Routing.generate('rating_edit', {
                id: this.coasterIdValue,
                _locale: this.localeValue,
            });

            const body = this.addCsrfToBody(`value=${value}`);

            const response = await fetch(url.replace(/^http:/, 'https:'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body,
            });

            if (!response.ok) throw new Error('Failed to save rating');

            const data = await response.json();
            if (data.id) this.ratingIdValue = data.id;

            // Visual feedback
            this.wrapper.style.animation = 'pulse 300ms ease';
            setTimeout(() => {
                this.wrapper.style.animation = '';
            }, 300);

            this.dispatch(wasNew ? 'created' : 'updated', {
                detail: { ratingId: data.id || this.ratingIdValue },
                bubbles: true,
            });
        } catch (error) {
            console.error('Rating save failed:', error);
            this.currentValueValue = previousValue;
            this.updateDisplay(previousValue || 0);
            this.showError('Unable to save rating. Please try again.');
        }
    }

    resetToZero() {
        this.currentValueValue = 0;
        this.ratingIdValue = null;
        this.updateDisplay(0);
        this.dispatch('deleted', { bubbles: true });
    }
}
