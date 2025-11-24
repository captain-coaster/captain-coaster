import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        $(this.element).tooltip({
            placement: 'bottom',
            animation: true
        });
    }
}
