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
        confirmText: String,
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

        const confirmMsg =
            this.hasConfirmTextValue && this.confirmTextValue
                ? this.confirmTextValue
                : 'Delete this rating?';
        if (!confirm(confirmMsg)) return;

        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            let body = null;

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
            this.removeRatingRow();
        } else {
            this.resetRatingStars();
        }
    }

    removeRatingRow() {
        const row = this.element.closest('[data-rating-row]');

        if (row) {
            // Fade out then remove
            row.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(8px)';
            setTimeout(() => row.remove(), 260);
        }
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
