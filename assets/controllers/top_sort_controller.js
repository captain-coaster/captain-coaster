import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static values = { topId: Number };

    connect() {
        this.sortable = new Sortable(this.element, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: () => this.#saveOrder(),
        });
    }

    disconnect() {
        this.sortable?.destroy();
    }

    async moveToTop(event) {
        const coasterId = parseInt(event.currentTarget.dataset.coasterId, 10);
        const component = await this.#liveComponent();
        component.action('moveToTop', { coasterId });
    }

    async moveToBottom(event) {
        const coasterId = parseInt(event.currentTarget.dataset.coasterId, 10);
        const component = await this.#liveComponent();
        component.action('moveToBottom', { coasterId });
    }

    async promptMoveToPosition(event) {
        const coasterId = parseInt(event.currentTarget.dataset.coasterId, 10);
        const count = this.element.querySelectorAll('li[data-coaster-id]').length;
        const input = window.prompt(`Move to position (1–${count})?`);
        if (!input) return;
        const position = parseInt(input, 10);
        if (isNaN(position) || position < 1 || position > count) return;
        const component = await this.#liveComponent();
        component.action('moveToPosition', { coasterId, position });
    }

    async #saveOrder() {
        const items = [...this.element.querySelectorAll('li[data-coaster-id]')];
        const positions = {};
        items.forEach((el, i) => {
            positions[el.dataset.coasterId] = i + 1;
        });
        const component = await this.#liveComponent();
        component.action('reorder', { positions: JSON.stringify(positions) });
    }

    async #liveComponent() {
        const liveEl = this.element.closest('[data-controller~="live"]');
        return getComponent(liveEl);
    }
}
