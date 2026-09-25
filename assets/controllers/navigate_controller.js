import { Controller } from '@hotwired/stimulus';

/** A select whose option values are same-site URLs: choosing one goes there. */
export default class extends Controller {
    go() {
        const url = this.element.value;
        if (url.startsWith('/') && !url.startsWith('//')) {
            window.location.assign(url);
        }
    }
}
