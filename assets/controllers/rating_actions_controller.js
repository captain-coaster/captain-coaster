import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['deleteButton'];
    static values = { ratingId: Number, locale: String };

    connect() {
        document.addEventListener('rating:created', this.showDelete.bind(this));
        document.addEventListener('rating:deleted', this.hideDelete.bind(this));
        this.updateDeleteButton();
    }

    disconnect() {
        document.removeEventListener('rating:created', this.showDelete.bind(this));
        document.removeEventListener('rating:deleted', this.hideDelete.bind(this));
    }

    async delete(event) {
        event.preventDefault();
        if (!this.ratingIdValue) return;
        if (!confirm('Delete this rating?')) return;

        try {
            const response = await fetch(Routing.generate('rating_delete', {
                id: this.ratingIdValue,
                _locale: this.localeValue
            }), { 
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error('Delete failed');

            // Find rating controller and reset it
            const ratingElement = document.querySelector('[data-controller*="rating"]:not([data-controller*="rating-"])');
            if (ratingElement) {
                const controller = this.application.getControllerForElementAndIdentifier(ratingElement, 'rating');
                if (controller && controller.resetToZero) {
                    controller.resetToZero();
                }
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Error deleting rating');
        }
    }

    showDelete(event) {
        this.ratingIdValue = event.detail.ratingId;
        this.updateDeleteButton();
    }

    hideDelete() {
        this.ratingIdValue = null;
        this.updateDeleteButton();
    }

    updateDeleteButton() {
        if (this.hasDeleteButtonTarget) {
            this.deleteButtonTarget.style.display = this.ratingIdValue ? 'inline-block' : 'none';
        }
    }
}