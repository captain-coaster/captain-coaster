import BaseController from './base_controller.js';
import { show, hide } from '../js/utils/dom.js';

export default class extends BaseController {
    static targets = ['deleteButton', 'title'];
    static values = {
        ratingId: Number,
        locale: String,
        mode: String,
        rateText: String,
        myRatingText: String,
    };

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

            // Use base controller's CSRF token method
            const token = this.getCsrfToken();
            if (token) {
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
                body = `_token=${token}`;
            }

            const url = Routing.generate('rating_delete', {
                id: this.ratingIdValue,
                _locale: this.localeValue,
            });

            const response = await fetch(url.replace(/^http:/, 'https:'), {
                method: 'DELETE',
                headers,
                body,
            });

            if (!response.ok) throw new Error('Delete failed');

            this.handleSuccessfulDeletion();
        } catch (error) {
            console.error('Rating deletion failed:', {
                ratingId: this.ratingIdValue,
                error: error.message,
                timestamp: new Date().toISOString(),
            });

            // Show user-friendly error message using base controller
            const errorMsg = error.message.includes('Network')
                ? 'Network error. Please check your connection.'
                : 'Unable to delete rating. Please try again.';

            this.showError(errorMsg);
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
        const ratingElement = document.querySelector(
            '[data-controller*="rating"]:not([data-controller*="rating-"])'
        );
        if (ratingElement) {
            const controller =
                this.application.getControllerForElementAndIdentifier(
                    ratingElement,
                    'rating'
                );
            if (controller?.resetToZero) controller.resetToZero();
        }
    }

    updateDeleteButton() {
        if (this.hasDeleteButtonTarget) {
            // Only show if we have a valid rating ID
            const shouldShow = this.hasRatingIdValue && this.ratingIdValue > 0;
            if (shouldShow) {
                show(this.deleteButtonTarget);
            } else {
                hide(this.deleteButtonTarget);
            }
        }
    }

    updateTitle() {
        if (this.hasTitleTarget) {
            this.titleTarget.textContent = this.ratingIdValue
                ? this.myRatingTextValue
                : this.rateTextValue;
        }
    }
}
