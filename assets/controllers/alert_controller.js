import { Controller } from '@hotwired/stimulus';

/**
 * Replaces bootstrap/js/alert for the 3 real dismiss buttons in the app
 * (flashes.html.twig, Default/index.html.twig, User/list.html.twig).
 * Bootstrap's own version also fades the alert out first if it has a
 * `.fade` class -- none of ours do, so the real behavior has always been
 * an instant removal, not an animated one.
 */
export default class extends Controller {
    close() {
        this.element.remove();
    }
}
