import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static values = { coasterId: Number, ridden: Boolean, riddenCoasterId: Number };
    connect() {
        document.dispatchEvent(new CustomEvent(this.riddenValue ? 'ride:marked' : 'ride:removed', {
            detail: { coasterId: this.coasterIdValue, riddenCoasterId: this.riddenCoasterIdValue },
        }));
    }
}
