import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rating'];
    static values = { coasterId: Number, currentValue: Number, ratingId: Number, locale: String, readonly: Boolean };
    static outlets = ['csrf-protection'];

    async connect() {
        try {
            await import('jquery.rateit');
            this.initRating();
            this.setupMobileLongPress();
        } catch (error) {
            console.error('Failed to load rateit:', error);
        }
    }

    setupMobileLongPress() {
        if (!this.hasRatingTarget || !('ontouchstart' in window)) return;
        
        this.touchTimer = null;
        this.touchEnabled = false;
        this.touchStartTime = 0;
        this.pendingRatingValue = null;
        
        this.ratingTarget.addEventListener('touchstart', (e) => {
            this.touchEnabled = false;
            this.touchStartTime = Date.now();
            this.touchTimer = setTimeout(() => {
                this.touchEnabled = true;
                // If there's a pending rating, process it now
                if (this.pendingRatingValue !== null) {
                    this.processPendingRating();
                }
            }, 200);
        }, { passive: true });
        
        this.ratingTarget.addEventListener('touchend', () => {
            const touchDuration = Date.now() - this.touchStartTime;
            if (touchDuration >= 200) {
                this.touchEnabled = true;
            }
            clearTimeout(this.touchTimer);
        }, { passive: true });
        
        this.ratingTarget.addEventListener('touchcancel', () => {
            clearTimeout(this.touchTimer);
            this.touchEnabled = false;
            this.pendingRatingValue = null;
        }, { passive: true });
    }
    
    processPendingRating() {
        if (this.pendingRatingValue !== null && this.touchEnabled) {
            this.handleChange();
            this.pendingRatingValue = null;
        }
    }

    disconnect() {
        if (this.hasRatingTarget && window.$ && $(this.ratingTarget).data('rateit')) {
            $(this.ratingTarget).rateit('destroy');
        }
    }

    initRating() {
        if (!this.hasRatingTarget || !window.$) return;
        
        const $rating = $(this.ratingTarget);
        $rating.rateit({
            max: 5,
            step: 0.5,
            resetable: false,
            mode: 'font',
            value: this.currentValueValue || 0,
            readonly: this.readonlyValue || false
        });

        // Only bind change handler if not readonly
        if (!this.readonlyValue) {
            $rating.on('rated', () => {
                // On mobile, only allow rating if touch was held for 200ms
                if ('ontouchstart' in window) {
                    const newValue = $rating.rateit('value');
                    this.pendingRatingValue = newValue;
                    
                    if (!this.touchEnabled) {
                        // Revert to previous value and wait for long press
                        $rating.rateit('value', this.currentValueValue || 0);
                        return;
                    }
                }
                this.handleChange();
                // Reset touch state after handling
                this.touchEnabled = false;
                this.pendingRatingValue = null;
            });
        }
    }

    async handleChange() {
        if (!window.$) return;
        
        const newValue = $(this.ratingTarget).rateit('value');
        if (newValue === null || newValue === this.currentValueValue) return;

        const wasNew = !this.ratingIdValue;
        
        try {
            const url = Routing.generate('rating_edit', {
                id: this.coasterIdValue,
                _locale: this.localeValue
            });
            
            const response = await fetch(url.replace(/^http:/, 'https:'), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: this.csrfProtectionOutlet ? 
                    this.csrfProtectionOutlet.addTokenToBody(`value=${newValue}`) : 
                    `value=${newValue}`
            });

            if (!response.ok) throw new Error('Failed to save rating');
            
            const data = await response.json();
            this.currentValueValue = newValue;
            if (data.id) this.ratingIdValue = data.id;

            // Add sparkle effect
            if (this.hasRatingTarget) {
                this.ratingTarget.classList.add('rating-confirmed');
                setTimeout(() => {
                    this.ratingTarget.classList.remove('rating-confirmed');
                }, 600);
            }

            this.dispatch(wasNew ? 'created' : 'updated', { 
                detail: { ratingId: data.id || this.ratingIdValue }, 
                bubbles: true 
            });
        } catch (error) {
            console.error('Rating save failed:', {
                coasterId: this.coasterIdValue,
                value: newValue,
                error: error.message,
                timestamp: new Date().toISOString()
            });
            
            // Revert rating to previous value
            $(this.ratingTarget).rateit('value', this.currentValueValue || 0);
            
            // Show user-friendly error
            const errorMsg = error.message.includes('Network') ? 
                'Network error. Rating not saved.' : 
                'Unable to save rating. Please try again.';
            
            this.dispatch('error', { detail: { message: errorMsg } });
        }
    }

    resetToZero() {
        this.currentValueValue = 0;
        this.ratingIdValue = null;
        if (this.hasRatingTarget && window.$) {
            $(this.ratingTarget).rateit('value', 0);
        }
        this.dispatch('deleted', { bubbles: true });
    }
}