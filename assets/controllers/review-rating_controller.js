import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { fieldId: String };

    connect() {
        // Listen for rating changes from the rating controller
        this.element.addEventListener('rating:updated', this.updateField.bind(this));
        this.element.addEventListener('rating:created', this.updateField.bind(this));
    }

    updateField(event) {
        const field = document.getElementById(this.fieldIdValue);
        if (field) {
            const ratingController = this.application.getControllerForElementAndIdentifier(
                this.element,
                'rating'
            );
            if (ratingController) {
                field.value = ratingController.currentValueValue;
            }
        }
    }
}
