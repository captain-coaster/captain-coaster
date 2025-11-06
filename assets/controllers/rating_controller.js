import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rating'];
    static values = { coasterId: Number, currentValue: Number, ratingId: Number, locale: String, readonly: Boolean };

    async connect() {
        try {
            await import('jquery.rateit');
            this.initRating();
        } catch (error) {
            console.error('Failed to load rateit:', error);
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
            $rating.on('rated', this.handleChange.bind(this));
        }
    }

    async handleChange() {
        if (!window.$) return;
        
        const newValue = $(this.ratingTarget).rateit('value');
        if (newValue === null || newValue === this.currentValueValue) return;

        const wasNew = !this.ratingIdValue;
        
        try {
            const response = await fetch(Routing.generate('rating_edit', {
                id: this.coasterIdValue,
                _locale: this.localeValue
            }), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `value=${newValue}`
            });

            if (!response.ok) throw new Error('Failed to save rating');
            
            const data = await response.json();
            this.currentValueValue = newValue;
            if (data.id) this.ratingIdValue = data.id;

            this.dispatch(wasNew ? 'created' : 'updated', { 
                detail: { ratingId: data.id || this.ratingIdValue }, 
                bubbles: true 
            });
        } catch (error) {
            console.error('Rating error:', error);
            $(this.ratingTarget).rateit('value', this.currentValueValue || 0);
            alert('Error saving rating');
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