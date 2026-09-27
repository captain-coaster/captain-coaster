import { Controller } from '@hotwired/stimulus';

/**
 * Crosshair tooltip of Ranking:TrendChart: follows the pointer (or arrow keys
 * once the plot has focus) and shows the month and value of the nearest point.
 */
export default class extends Controller {
    static targets = ['plot', 'cursor', 'dot', 'tip'];
    static values = { labels: Array, values: Array, max: Number };

    connect() {
        this.format = new Intl.NumberFormat(document.documentElement.lang);
        this.index = this.valuesValue.length - 1;
    }

    point(event) {
        const box = this.plotTarget.getBoundingClientRect();
        const ratio = Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1);
        this.show(Math.round(ratio * (this.valuesValue.length - 1)));
    }

    key(event) {
        const step = { ArrowLeft: -1, ArrowRight: 1, Home: -Infinity, End: Infinity }[event.key];
        if (step === undefined) return;
        event.preventDefault();
        const last = this.valuesValue.length - 1;
        this.show(Math.min(Math.max(this.index + step, 0), last));
    }

    show(index) {
        this.index = index;
        const last = this.valuesValue.length - 1;
        const x = last > 0 ? (index / last) * 100 : 0;
        const value = Number(this.valuesValue[index]);
        const top = this.maxValue ? 100 - (value / this.maxValue) * 100 : 0;

        this.cursorTarget.style.left = `${x}%`;
        this.dotTarget.style.top = `${top}%`;
        this.tipTarget.textContent = `${this.labelsValue[index]} · ${this.format.format(value)}`;
        this.tipTarget.style.left = `${x}%`;
        this.tipTarget.style.translate = `${x > 70 ? -100 : x < 30 ? 0 : -50}% 0`;
        this.cursorTarget.classList.remove('hidden');
        this.tipTarget.classList.remove('hidden');
    }

    hide() {
        this.cursorTarget.classList.add('hidden');
        this.tipTarget.classList.add('hidden');
    }
}
