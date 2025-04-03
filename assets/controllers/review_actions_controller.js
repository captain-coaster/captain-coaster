import { Controller } from '@hotwired/stimulus';

/**
 * Review actions controller for handling upvotes and reports
 */
export default class extends Controller {
    static targets = ['upvoteButton', 'upvoteCount', 'reportButton', 'reportModal'];
    static values = {
        id: Number,
        upvoted: Boolean
    };

    connect() {
        console.log('Review actions controller connected', this.element);
        console.log('Review ID:', this.idValue);
        console.log('Has upvote button target:', this.hasUpvoteButtonTarget);
        console.log('Has report button target:', this.hasReportButtonTarget);
        console.log('Has report modal target:', this.hasReportModalTarget);

        // Check if the user has already upvoted this review
        if (this.hasUpvoteButtonTarget) {
            this._checkUpvoteStatus();
        }
    }

    /**
     * Toggle upvote for a review
     */
    upvote(event) {
        console.log('Upvote action triggered', event);
        event.preventDefault();

        if (!this.hasIdValue) {
            console.error('Review ID not provided');
            return;
        }

        fetch(`/reviews/${this.idValue}/upvote`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the upvote count
                    if (this.hasUpvoteCountTarget) {
                        this.upvoteCountTarget.textContent = data.upvoteCount;
                    }

                    // Toggle the upvoted state
                    this.upvotedValue = data.action === 'added';
                    this._updateUpvoteButtonState();
                }
            })
            .catch(error => {
                console.error('Error toggling upvote:', error);
            });
    }

    /**
     * Open the report modal
     */
    openReportModal(event) {
        console.log('Open report modal action triggered', event);
        event.preventDefault();

        if (this.hasReportModalTarget) {
            // Show the modal using Bootstrap's modal API
            $(this.reportModalTarget).modal('show');
        }
    }

    /**
     * Submit a report
     */
    submitReport(event) {
        console.log('Submit report action triggered', event);
        event.preventDefault();

        if (!this.hasIdValue) {
            console.error('Review ID not provided');
            return;
        }

        const form = event.currentTarget;
        const formData = new FormData(form);

        fetch(`/reviews/${this.idValue}/report`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the modal
                    if (this.hasReportModalTarget) {
                        $(this.reportModalTarget).modal('hide');
                    }

                    // Disable the report button
                    if (this.hasReportButtonTarget) {
                        this.reportButtonTarget.disabled = true;
                        this.reportButtonTarget.classList.add('disabled');
                    }

                    // Show a success message
                    alert('Thank you for your report. Our team will review it shortly.');
                } else {
                    alert(data.message || 'An error occurred while submitting your report.');
                }
            })
            .catch(error => {
                console.error('Error submitting report:', error);
                alert('An error occurred while submitting your report.');
            });
    }

    /**
     * Check if the user has already upvoted this review
     * @private
     */
    _checkUpvoteStatus() {
        if (!this.hasIdValue) {
            return;
        }

        fetch(`/reviews/${this.idValue}/has-upvoted`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                this.upvotedValue = data.hasUpvoted;
                this._updateUpvoteButtonState();
            })
            .catch(error => {
                console.error('Error checking upvote status:', error);
            });
    }

    /**
     * Update the upvote button state based on the upvoted value
     * @private
     */
    _updateUpvoteButtonState() {
        if (this.hasUpvoteButtonTarget) {
            if (this.upvotedValue) {
                this.upvoteButtonTarget.classList.add('active');
                this.upvoteButtonTarget.setAttribute('title', 'Remove upvote');
            } else {
                this.upvoteButtonTarget.classList.remove('active');
                this.upvoteButtonTarget.setAttribute('title', 'Upvote this review');
            }
        }
    }
}