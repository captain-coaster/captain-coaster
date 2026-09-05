import { Controller } from '@hotwired/stimulus';

/**
 * Summary feedback controller for handling thumbs up/down voting on AI summaries
 */
export default class extends Controller {
    static targets = [
        'thumbsUpButton',
        'thumbsDownButton',
        'positiveCount',
        'negativeCount',
        'totalCount',
        'loadingIndicator',
    ];
    static values = {
        summaryId: Number,
        feedbackUrl: String,
        csrfToken: String,
        userVote: String, // 'positive', 'negative', or null
        hasVoted: Boolean,
        successMessage: String,
        errorMessage: String,
        helpfulTitle: String,
        notHelpfulTitle: String,
        votedHelpfulTitle: String,
        votedNotHelpfulTitle: String,
    };

    connect() {
        // Initialize button states based on user's current vote
        this._updateButtonStates();
    }

    /**
     * Handle thumbs up vote
     */
    thumbsUp(event) {
        event.preventDefault();
        this._submitVote(true);
    }

    /**
     * Handle thumbs down vote
     */
    thumbsDown(event) {
        event.preventDefault();
        this._submitVote(false);
    }

    /**
     * Submit a vote to the server
     * @param {boolean} isPositive - true for thumbs up, false for thumbs down
     * @private
     */
    _submitVote(isPositive) {
        // Prevent multiple simultaneous requests
        if (this._isLoading) {
            return;
        }

        // Show loading state
        this._setLoadingState(true);

        // Prepare form data
        const formData = new FormData();
        formData.append('isPositive', isPositive.toString());
        formData.append('_token', this.csrfTokenValue);

        // Submit the vote
        fetch(this.feedbackUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Update vote counts
                    this._updateVoteCounts(data);

                    // Update user's vote state
                    this.userVoteValue = data.hasVoted
                        ? data.userVote
                            ? 'positive'
                            : 'negative'
                        : null;
                    this.hasVotedValue = data.hasVoted;

                    // Update button states
                    this._updateButtonStates();

                    // Show success feedback
                    this._showNotification(this.successMessageValue, 'success');
                } else {
                    // Show error message
                    this._showNotification(
                        data.message || this.errorMessageValue,
                        'danger'
                    );
                }
            })
            .catch((error) => {
                console.error('Error submitting feedback:', error);
                this._showNotification(this.errorMessageValue, 'danger');
            })
            .finally(() => {
                // Hide loading state
                this._setLoadingState(false);
            });
    }

    /**
     * Update vote counts in the UI
     * @param {Object} data - Response data containing vote counts
     * @private
     */
    _updateVoteCounts(data) {
        if (this.hasPositiveCountTarget) {
            this.positiveCountTarget.textContent = data.positiveVotes || 0;
        }

        if (this.hasNegativeCountTarget) {
            this.negativeCountTarget.textContent = data.negativeVotes || 0;
        }

        if (this.hasTotalCountTarget) {
            this.totalCountTarget.textContent = data.totalVotes || 0;
        }
    }

    /**
     * Update button states based on user's current vote. The outline/filled icon
     * swap itself is pure CSS driven by the "voted" class (see summary-feedback.css) -
     * both icon variants are rendered once server-side via ux_icon() in the template,
     * so this only toggles which one is visible and updates the accessible title.
     * @private
     */
    _updateButtonStates() {
        // Reset all button states
        this._resetButtonStates();

        // Apply active state based on user's vote
        if (this.hasVotedValue) {
            if (
                this.userVoteValue === 'positive' &&
                this.hasThumbsUpButtonTarget
            ) {
                this.thumbsUpButtonTarget.classList.add('voted');
                this.thumbsUpButtonTarget.setAttribute(
                    'title',
                    this.votedHelpfulTitleValue
                );
            } else if (
                this.userVoteValue === 'negative' &&
                this.hasThumbsDownButtonTarget
            ) {
                this.thumbsDownButtonTarget.classList.add('voted');
                this.thumbsDownButtonTarget.setAttribute(
                    'title',
                    this.votedNotHelpfulTitleValue
                );
            }
        }
    }

    /**
     * Reset button states to default
     * @private
     */
    _resetButtonStates() {
        if (this.hasThumbsUpButtonTarget) {
            this.thumbsUpButtonTarget.classList.remove('voted');
            this.thumbsUpButtonTarget.setAttribute(
                'title',
                this.helpfulTitleValue
            );
        }

        if (this.hasThumbsDownButtonTarget) {
            this.thumbsDownButtonTarget.classList.remove('voted');
            this.thumbsDownButtonTarget.setAttribute(
                'title',
                this.notHelpfulTitleValue
            );
        }
    }

    /**
     * Set loading state for the feedback buttons
     * @param {boolean} isLoading - Whether to show loading state
     * @private
     */
    _setLoadingState(isLoading) {
        this._isLoading = isLoading;

        // Disable/enable buttons
        if (this.hasThumbsUpButtonTarget) {
            this.thumbsUpButtonTarget.disabled = isLoading;
        }

        if (this.hasThumbsDownButtonTarget) {
            this.thumbsDownButtonTarget.disabled = isLoading;
        }

        // Show/hide loading indicator
        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = isLoading
                ? 'inline-block'
                : 'none';
        }

        // Add loading class to buttons for visual feedback
        const buttons = [
            this.thumbsUpButtonTarget,
            this.thumbsDownButtonTarget,
        ].filter(Boolean);
        buttons.forEach((button) => {
            if (isLoading) {
                button.classList.add('loading');
            } else {
                button.classList.remove('loading');
            }
        });
    }

    /**
     * Show a notification using the notification controller
     * @param {string} message - The message to display
     * @param {string} type - The type of notification (success, info, warning, danger)
     * @private
     */
    _showNotification(message, type = 'info') {
        // Try to get the global toast controller
        const notificationElement = document.getElementById('toasts');
        if (notificationElement) {
            const notificationController =
                this.application.getControllerForElementAndIdentifier(
                    notificationElement,
                    'toast'
                );

            if (notificationController) {
                // Use the appropriate method based on notification type
                switch (type) {
                    case 'success':
                        notificationController.showSuccess(message);
                        break;
                    case 'warning':
                        notificationController.showWarning(message);
                        break;
                    case 'danger':
                        notificationController.showDanger(message);
                        break;
                    default:
                        notificationController.showInfo(message);
                }
                return;
            }
        }

        // Fallback: log to console if notification controller is not available
        console.warn('Notification:', message);
    }
}
