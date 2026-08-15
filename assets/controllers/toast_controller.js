import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];

    show(message, type = 'info', timeout = 4000) {
        const accentColors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            danger: 'bg-red-500',
            warning: 'bg-amber-500',
            info: 'bg-cc-blue-500',
        };
        const icons = {
            success: '✓',
            error: '✕',
            danger: '✕',
            warning: '⚠',
            info: 'ℹ',
        };

        const accent = accentColors[type] ?? accentColors.info;
        const icon = icons[type] ?? icons.info;

        const toast = document.createElement('div');
        toast.className =
            'flex bg-surface-raised rounded-xl shadow-lg border border-line overflow-hidden min-w-64 max-w-xs transition-all duration-300 translate-y-2 opacity-0';

        const accentEl = document.createElement('div');
        accentEl.className = `w-1 shrink-0 ${accent}`;

        const body = document.createElement('div');
        body.className = 'flex items-center gap-3 px-4 py-3 flex-1';

        const iconEl = document.createElement('span');
        iconEl.className = 'text-base shrink-0';
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.textContent = icon;

        const msgEl = document.createElement('p');
        msgEl.className = 'text-sm text-content flex-1';
        msgEl.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'shrink-0 text-content-muted hover:text-content transition-colors';
        closeBtn.setAttribute('aria-label', 'Dismiss');
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', () => this.dismiss(toast));

        body.append(iconEl, msgEl, closeBtn);
        toast.append(accentEl, body);

        const container = this.hasContainerTarget ? this.containerTarget : document.body;
        container.appendChild(toast);

        // Double rAF ensures the initial opacity-0/translate classes are painted before removing them
        requestAnimationFrame(() =>
            requestAnimationFrame(() =>
                toast.classList.remove('translate-y-2', 'opacity-0')
            )
        );

        if (timeout > 0) {
            setTimeout(() => this.dismiss(toast), timeout);
        }
    }

    dismiss(toast) {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }

    showSuccess(message) { this.show(message, 'success'); }
    showInfo(message)    { this.show(message, 'info'); }
    showWarning(message) { this.show(message, 'warning'); }
    showDanger(message)  { this.show(message, 'danger'); }
    showError(message)   { this.show(message, 'error'); }
}
