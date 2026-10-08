import { Controller } from '@hotwired/stimulus';

// Locks a form while it posts, so a slow upload or sign-in can't be sent
// twice: a second submit is dropped and the submit buttons are disabled,
// with the loading text when one is given. The form theme puts it on every
// POST form. Required fields are native (`required`) and validated
// server-side.
export default class extends Controller {
    static values = { loadingText: String };

    connect() {
        // Back to the page from the back-forward cache: the form is usable again
        this.restore = (event) => {
            if (event.persisted) {
                this.unlock();
            }
        };
        window.addEventListener('pageshow', this.restore);
    }

    disconnect() {
        window.removeEventListener('pageshow', this.restore);
    }

    submit(event) {
        // Cancelled by another handler (a declined confirm()): nothing is sent
        if (event.defaultPrevented) {
            return;
        }
        if (this.labels) {
            event.preventDefault();

            return;
        }

        this.labels = new Map();
        this.element
            .querySelectorAll('button[type=submit]')
            .forEach((button) => {
                this.labels.set(button, button.innerHTML);
                button.disabled = true;
                if (this.loadingTextValue) {
                    button.textContent = this.loadingTextValue;
                }
            });
    }

    unlock() {
        this.labels?.forEach((label, button) => {
            button.disabled = false;
            button.innerHTML = label;
        });
        this.labels = null;
    }
}
