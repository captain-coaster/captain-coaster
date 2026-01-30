import BaseController from './base_controller.js';

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
        this.renderStars();
        this.setupEventListeners();
    }

    renderStars() {
        const container = this.element;
        container.classList.add('rating-stars');

        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.className =
                'rating-star transition-transform duration-150 hover:scale-110 cursor-pointer';
            star.dataset.ratingTarget = 'star';
            star.dataset.value = i;
            star.innerHTML = this.getStarSVG('empty');
            container.appendChild(star);
        }

        this.updateStarDisplay(this.currentValueValue || 0);
    }

    getStarSVG(type) {
        const baseClasses = 'w-8 h-8';

        switch (type) {
            case 'filled':
                return `<svg class="${baseClasses} text-cc-warm-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="m8.243 7.34l-6.38.925l-.113.023a1 1 0 0 0-.44 1.684l4.622 4.499l-1.09 6.355l-.013.11a1 1 0 0 0 1.464.944l5.706-3l5.693 3l.1.046a1 1 0 0 0 1.352-1.1l-1.091-6.355l4.624-4.5l.078-.085a1 1 0 0 0-.633-1.62l-6.38-.926l-2.852-5.78a1 1 0 0 0-1.794 0z"/>
                </svg>`;
            case 'half':
                return `<svg class="${baseClasses} text-cc-warm-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 1a1 1 0 0 1 .823.443l.067.116l2.852 5.781l6.38.925c.741.108 1.08.94.703 1.526l-.07.095l-.078.086l-4.624 4.499l1.09 6.355a1 1 0 0 1-1.249 1.135l-.101-.035l-.101-.046l-5.693-3l-5.706 3q-.158.082-.32.106l-.106.01a1.003 1.003 0 0 1-1.038-1.06l.013-.11l1.09-6.355l-4.623-4.5a1 1 0 0 1 .328-1.647l.113-.036l.114-.023l6.379-.925l2.853-5.78A.97.97 0 0 1 12 1m0 3.274V16.75a1 1 0 0 1 .239.029l.115.036l.112.05l4.363 2.299l-.836-4.873a1 1 0 0 1 .136-.696l.07-.099l.082-.09l3.546-3.453l-4.891-.708a1 1 0 0 1-.62-.344l-.073-.097l-.06-.106z"/>
                </svg>`;
            default: // empty
                return `<svg class="${baseClasses} text-neutral-300 dark:text-neutral-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m12 17.75l-6.172 3.245l1.179-6.873l-5-4.867l6.9-1l3.086-6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/>
                </svg>`;
        }
    }

    setupEventListeners() {
        if (this.readonlyValue) return;

        const isMobile = 'ontouchstart' in window;

        if (isMobile) {
            this.setupMobileEvents();
        } else {
            this.setupDesktopEvents();
        }
    }

    setupMobileEvents() {
        this.touchStartY = 0;
        this.touchStartX = 0;
        this.isScrolling = false;
        this.hoverValue = 0;

        this.element.addEventListener(
            'touchstart',
            (e) => {
                this.touchStartY = e.touches[0].clientY;
                this.touchStartX = e.touches[0].clientX;
                this.isScrolling = false;

                const value = this.getValueFromEvent(e.touches[0]);
                this.hoverValue = value;
                this.updateStarDisplay(value);
            },
            { passive: true }
        );

        this.element.addEventListener(
            'touchmove',
            (e) => {
                const touchY = e.touches[0].clientY;
                const touchX = e.touches[0].clientX;
                const deltaY = Math.abs(touchY - this.touchStartY);
                const deltaX = Math.abs(touchX - this.touchStartX);

                // If vertical movement is greater than horizontal, user is scrolling
                if (deltaY > 10 && deltaY > deltaX) {
                    this.isScrolling = true;
                    this.updateStarDisplay(this.currentValueValue || 0);
                } else if (!this.isScrolling) {
                    // Update preview if not scrolling
                    const value = this.getValueFromEvent(e.touches[0]);
                    this.hoverValue = value;
                    this.updateStarDisplay(value);
                }
            },
            { passive: true }
        );

        this.element.addEventListener('touchend', (e) => {
            if (!this.isScrolling && this.hoverValue > 0) {
                this.setRating(this.hoverValue);
            } else {
                this.updateStarDisplay(this.currentValueValue || 0);
            }

            this.isScrolling = false;
            this.hoverValue = 0;
        });

        this.element.addEventListener('touchcancel', () => {
            this.isScrolling = false;
            this.hoverValue = 0;
            this.updateStarDisplay(this.currentValueValue || 0);
        });
    }

    setupDesktopEvents() {
        this.element.addEventListener('mousemove', (e) => {
            const value = this.getValueFromEvent(e);
            this.updateStarDisplay(value);
        });

        this.element.addEventListener('mouseleave', () => {
            this.updateStarDisplay(this.currentValueValue || 0);
        });

        this.element.addEventListener('click', (e) => {
            const value = this.getValueFromEvent(e);
            if (value > 0) {
                this.setRating(value);
            }
        });
    }

    getValueFromEvent(event) {
        const rect = this.element.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const width = rect.width;
        const rawValue = (x / width) * 5;
        return Math.max(0.5, Math.min(5, Math.round(rawValue * 2) / 2));
    }

    updateStarDisplay(value) {
        this.starTargets.forEach((star, index) => {
            const starValue = index + 1;

            if (value >= starValue) {
                // Full star
                star.innerHTML = this.getStarSVG('filled');
            } else if (value >= starValue - 0.5) {
                // Half star
                star.innerHTML = this.getStarSVG('half');
            } else {
                // Empty star
                star.innerHTML = this.getStarSVG('empty');
            }
        });
    }

    async setRating(value) {
        if (value === this.currentValueValue) return;

        const previousValue = this.currentValueValue;
        this.currentValueValue = value;
        this.updateStarDisplay(value);

        // Check if we're in form mode (has a form field to update)
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

            // Use base controller's CSRF token method
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

            // Add sparkle effect
            this.element.classList.add('rating-confirmed');
            setTimeout(() => {
                this.element.classList.remove('rating-confirmed');
            }, 600);

            this.dispatch(wasNew ? 'created' : 'updated', {
                detail: { ratingId: data.id || this.ratingIdValue },
                bubbles: true,
            });
        } catch (error) {
            console.error('Rating save failed:', error);

            // Revert to previous value
            this.currentValueValue = previousValue;
            this.updateStarDisplay(previousValue || 0);

            const errorMsg = error.message.includes('Network')
                ? 'Network error. Rating not saved.'
                : 'Unable to save rating. Please try again.';

            // Use base controller's error notification
            this.showError(errorMsg);
        }
    }

    resetToZero() {
        this.currentValueValue = 0;
        this.ratingIdValue = null;
        this.updateStarDisplay(0);
        this.dispatch('deleted', { bubbles: true });
    }
}
