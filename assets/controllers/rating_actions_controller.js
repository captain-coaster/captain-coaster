import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['deleteButton', 'title'];
    static values = { ratingId: Number, locale: String, mode: String, rateText: String, myRatingText: String };
    static outlets = ['csrf-protection'];

    connect() {
        this.boundShowDelete = this.showDelete.bind(this);
        this.boundHideDelete = this.hideDelete.bind(this);
        document.addEventListener('rating:created', this.boundShowDelete);
        document.addEventListener('rating:deleted', this.boundHideDelete);
        // Always enforce correct visibility on connect
        this.updateDeleteButton();
        this.updateTitle();
    }

    disconnect() {
        document.removeEventListener('rating:created', this.boundShowDelete);
        document.removeEventListener('rating:deleted', this.boundHideDelete);
    }

    async delete(event) {
        event.preventDefault();
        if (!this.ratingIdValue) return;
        if (!confirm('Delete this rating?')) return;

        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            let body = null;
            
            if (this.csrfProtectionOutlet) {
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
                body = `_token=${this.csrfProtectionOutlet.getToken()}`;
            }

            const url = Routing.generate('rating_delete', {
                id: this.ratingIdValue,
                _locale: this.localeValue
            });
            
            const response = await fetch(url.replace(/^http:/, 'https:'), { 
                method: 'DELETE',
                headers,
                body
            });

            if (!response.ok) throw new Error('Delete failed');

            this.handleSuccessfulDeletion();
        } catch (error) {
            console.error('Rating deletion failed:', {
                ratingId: this.ratingIdValue,
                error: error.message,
                timestamp: new Date().toISOString()
            });
            
            // Show user-friendly error message
            const errorMsg = error.message.includes('Network') ? 
                'Network error. Please check your connection.' : 
                'Unable to delete rating. Please try again.';
            
            this.dispatch('error', { detail: { message: errorMsg } });
        }
    }

    showDelete(event) {
        this.ratingIdValue = event.detail.ratingId;
        this.updateDeleteButton();
        this.updateTitle();
    }

    hideDelete() {
        this.ratingIdValue = null;
        this.updateDeleteButton();
        this.updateTitle();
    }

    handleSuccessfulDeletion() {
        if (this.modeValue === 'table') {
            this.removeTableRow();
        } else {
            this.resetRatingStars();
        }
    }

    removeTableRow() {
        const row = this.element.closest('tr');
        if (row) row.remove();
    }

    resetRatingStars() {
        const ratingElement = document.querySelector('[data-controller*="rating"]:not([data-controller*="rating-"])');
        if (ratingElement) {
            const controller = this.application.getControllerForElementAndIdentifier(ratingElement, 'rating');
            if (controller?.resetToZero) controller.resetToZero();
        }
    }

    updateDeleteButton() {
        if (this.hasDeleteButtonTarget) {
            // Only show if we have a valid rating ID
            const shouldShow = this.hasRatingIdValue && this.ratingIdValue > 0;
            this.deleteButtonTarget.style.display = shouldShow ? 'inline-flex' : 'none';
        }
    }

    updateTitle() {
        if (this.hasTitleTarget) {
            this.titleTarget.textContent = this.ratingIdValue ? this.myRatingTextValue : this.rateTextValue;
        }
    }
}