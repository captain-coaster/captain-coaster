import { Controller } from '@hotwired/stimulus';

/**
 * Marks, in a list of in-page links, the one whose section is being read (`aria-current="location"`).
 * A section counts once its top has passed the upper fifth of the screen.
 *
 * Usage:
 *   <ul data-controller="scrollspy">
 *     <li><a href="#colors" data-scrollspy-target="link">Colors</a></li>
 *   </ul>
 */
export default class extends Controller {
    static targets = ['link'];

    connect() {
        this.links = new Map();
        this.observer = new IntersectionObserver(
            (entries) => this.read(entries),
            {
                rootMargin: '-20% 0px -75% 0px',
            }
        );
        for (const link of this.linkTargets) {
            const anchor = document.getElementById(link.hash.slice(1));
            if (anchor) {
                // The anchor is often a title: what is read is the section around it.
                const section = anchor.closest('section') ?? anchor;
                this.links.set(section, link);
                this.observer.observe(section);
            }
        }
    }

    disconnect() {
        this.observer.disconnect();
    }

    read(entries) {
        const entry = entries.find((candidate) => candidate.isIntersecting);
        if (!entry) {
            return;
        }
        const current = this.links.get(entry.target);
        for (const link of this.linkTargets) {
            if (link === current) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        }
    }
}
