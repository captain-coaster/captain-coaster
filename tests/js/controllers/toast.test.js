import { describe, expect, it, vi } from 'vitest';
import Clipboard from '../../../assets/controllers/clipboard_controller';
import Toast from '../../../assets/controllers/toast_controller';
import {
    controllerOf,
    jsIcons,
    mount,
    patch,
    settle,
} from '../support/stimulus';

const toasts = `${jsIcons('success', 'info', 'warning', 'error', 'close')}<div id="toasts" data-controller="toast"></div>`;
const shown = () => [...document.querySelectorAll('.notification')];

describe('toast', () => {
    it.each([
        ['showSuccess', 'success', 'success'],
        ['showInfo', 'info', 'info'],
        ['showWarning', 'warning', 'warning'],
        ['showDanger', 'danger', 'error'],
    ])(
        '%s shows its message with its own style and icon',
        async (method, type, icon) => {
            await mount(toasts, { toast: Toast });

            controllerOf('#toasts', 'toast')[method]('Saved');

            const [toast] = shown();
            expect(toast.classList.contains(`notification--${type}`)).toBe(
                true
            );
            expect(
                toast.querySelector('.notification__message').textContent
            ).toBe('Saved');
            expect(
                toast.querySelector('.notification__icon svg').dataset.name
            ).toBe(icon);
        }
    );

    it('goes away by itself', async () => {
        await mount(toasts, { toast: Toast });
        vi.useFakeTimers();

        controllerOf('#toasts', 'toast').show('Saved', 'info', 3000);
        vi.advanceTimersByTime(2999);
        expect(shown()).toHaveLength(1);

        vi.advanceTimersByTime(301);
        expect(shown()).toHaveLength(0);
    });

    it('closes on its button', async () => {
        await mount(toasts, { toast: Toast });
        vi.useFakeTimers();
        controllerOf('#toasts', 'toast').show('Saved');

        document.querySelector('.notification__close').click();
        vi.advanceTimersByTime(300);

        expect(shown()).toHaveLength(0);
    });
});

describe('clipboard', () => {
    const page = (attributes = '') =>
        `${toasts}<button data-controller="clipboard" data-action="clipboard#copy" data-clipboard-content-value="https://captaincoaster.test/en/tops/12" ${attributes}>Copy</button>`;

    function clipboard(writeText) {
        patch(navigator, 'clipboard', { writeText });
    }

    it('copies its content and confirms', async () => {
        const writeText = vi.fn().mockResolvedValue();
        clipboard(writeText);
        await mount(page(), { clipboard: Clipboard, toast: Toast });

        document.querySelector('button').click();
        await settle();

        expect(writeText).toHaveBeenCalledWith(
            'https://captaincoaster.test/en/tops/12'
        );
        expect(
            document.querySelector(
                '.notification--success .notification__message'
            ).textContent
        ).toBe('clipboard.copied');
    });

    it('uses the page own messages when given', async () => {
        clipboard(vi.fn().mockResolvedValue());
        await mount(
            page('data-clipboard-success-message-value="Link copied"'),
            { clipboard: Clipboard, toast: Toast }
        );

        document.querySelector('button').click();
        await settle();

        expect(
            document.querySelector('.notification__message').textContent
        ).toBe('Link copied');
    });

    it('says so when the browser refuses', async () => {
        clipboard(vi.fn().mockRejectedValue(new Error('denied')));
        await mount(page(), { clipboard: Clipboard, toast: Toast });

        document.querySelector('button').click();
        await settle();

        expect(
            document.querySelector(
                '.notification--danger .notification__message'
            ).textContent
        ).toBe('clipboard.copy_failed');
    });
});
