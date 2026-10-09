import { beforeEach, describe, expect, it, vi } from 'vitest';
import Modal from '../../../assets/controllers/modal_controller';
import { controllerOf, mount, stubDialog } from '../support/stimulus';

const page = `
    <div id="wrapper" data-controller="modal">
        <button id="open" data-action="modal#handleShow">Report</button>
        <dialog data-modal-target="modal">
            <div class="modal-dialog"><p id="content">Why?</p><button id="cancel" data-dismiss="modal">Cancel</button></div>
        </dialog>
    </div>`;

const dialog = () => document.querySelector('dialog');
const events = [];

describe('modal', () => {
    beforeEach(async () => {
        stubDialog();
        await mount(page, { modal: Modal });
        events.length = 0;
        for (const name of [
            'modal:show',
            'modal:shown',
            'modal:hide',
            'modal:hidden',
        ]) {
            document
                .getElementById('wrapper')
                .addEventListener(name, (event) => events.push(event.type));
        }
    });

    it('opens the dialog and locks the page behind it', () => {
        document.getElementById('open').click();

        expect(dialog().open).toBe(true);
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(events).toEqual(['modal:show', 'modal:shown']);
    });

    it.each([
        ['its dismiss button', () => document.getElementById('cancel').click()],
        ['a click on the backdrop', () => dialog().click()],
        [
            'Escape',
            () =>
                dialog().dispatchEvent(
                    new Event('cancel', { cancelable: true })
                ),
        ],
    ])('closes on %s', (_, dismiss) => {
        document.getElementById('open').click();

        dismiss();

        expect(dialog().open).toBe(false);
        expect(document.body.classList.contains('modal-open')).toBe(false);
        expect(events.slice(2)).toEqual(['modal:hide', 'modal:hidden']);
    });

    it('stays open on a click inside its content', () => {
        document.getElementById('open').click();

        document.getElementById('content').click();

        expect(dialog().open).toBe(true);
    });

    it('lets a listener keep it closed or open', () => {
        const veto = (event) => event.preventDefault();
        document
            .getElementById('wrapper')
            .addEventListener('modal:show', veto, { once: true });
        document.getElementById('open').click();
        expect(dialog().open).toBe(false);

        document.getElementById('open').click();
        document
            .getElementById('wrapper')
            .addEventListener('modal:hide', veto, { once: true });
        document.getElementById('cancel').click();
        expect(dialog().open).toBe(true);
    });

    it('toggles', () => {
        const modal = controllerOf('#wrapper', 'modal');

        modal.toggle();
        expect(dialog().open).toBe(true);
        modal.toggle();
        expect(dialog().open).toBe(false);
    });

    it('does not reopen a dialog already open', () => {
        const showModal = vi.spyOn(HTMLDialogElement.prototype, 'showModal');
        const modal = controllerOf('#wrapper', 'modal');

        modal.show();
        modal.show();

        expect(showModal).toHaveBeenCalledOnce();
    });
});
