import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { token: String };

    getToken() {
        return this.tokenValue;
    }

    addTokenToFormData(formData) {
        formData.append('_token', this.tokenValue);
        return formData;
    }

    addTokenToBody(body) {
        const params = new URLSearchParams(body);
        params.append('_token', this.tokenValue);
        return params.toString();
    }
}
