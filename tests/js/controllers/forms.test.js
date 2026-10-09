import { describe, expect, it, vi } from 'vitest';
import Autosave from '../../../assets/controllers/autosave_controller';
import FilePreview from '../../../assets/controllers/file_preview_controller';
import FormSubmit from '../../../assets/controllers/form_submit_controller';
import TagChoice from '../../../assets/controllers/tag_choice_controller';
import { mount, settle, stubFetch } from '../support/stimulus';

describe('autosave', () => {
    const page = `
        <form action="/en/settings/email" data-controller="autosave" data-action="change->autosave#save"
              data-autosave-saved-text-value="Saved" data-autosave-error-text-value="Not saved">
            <input type="checkbox" name="emailNotification" checked>
            <input type="hidden" name="_token" value="tok">
            <p role="status" data-autosave-target="status"></p>
        </form>`;
    const box = () => document.querySelector('[type=checkbox]');
    const status = () => document.querySelector('[role=status]');

    function toggle() {
        box().checked = !box().checked;
        box().dispatchEvent(new Event('change', { bubbles: true }));
    }

    it('posts the form as soon as a switch changes and says it is saved', async () => {
        const fetch = stubFetch({ status: 204 });
        await mount(page, { autosave: Autosave });

        toggle();
        await settle();

        const [url, request] = fetch.mock.calls[0];
        expect(new URL(url).pathname).toBe('/en/settings/email');
        expect(request.method).toBe('POST');
        expect(request.body.has('emailNotification')).toBe(false);
        expect(request.body.get('_token')).toBe('tok');
        expect(status().textContent).toBe('Saved');
        expect(status().hasAttribute('data-failed')).toBe(false);
    });

    it.each([
        ['the server refuses', { status: 500 }],
        ['the network is down', new Error('offline')],
    ])('puts the switch back and says so when %s', async (_, response) => {
        stubFetch(response);
        await mount(page, { autosave: Autosave });

        toggle();
        await settle();

        expect(box().checked).toBe(true);
        expect(status().textContent).toBe('Not saved');
        expect(status().hasAttribute('data-failed')).toBe(true);
    });
});

describe('form-submit', () => {
    it('disables the submit button while the form posts', async () => {
        await mount(
            `<form data-controller="form-submit" data-action="submit->form-submit#submit" data-form-submit-loading-text-value="Sending…">
                <button type="button">Other</button><button type="submit">Send</button>
            </form>`,
            { 'form-submit': FormSubmit }
        );
        const form = document.querySelector('form');
        form.addEventListener('submit', (event) => event.preventDefault());

        form.dispatchEvent(
            new Event('submit', { bubbles: true, cancelable: true })
        );

        const [other, submit] = document.querySelectorAll('button');
        expect(submit.disabled).toBe(true);
        expect(submit.textContent).toBe('Sending…');
        expect(other.disabled).toBe(false);
    });
});

describe('tag-choice', () => {
    const chip = (id, checked = false, extra = false) =>
        `<label ${extra ? 'data-tag-choice-target="extra"' : ''}><input type="checkbox" id="t${id}" ${checked ? 'checked' : ''}></label>`;
    const page = (chips) => `
        <fieldset data-controller="tag-choice" data-tag-choice-max-value="2" data-action="change->tag-choice#update">
            ${chips}<button type="button" data-action="tag-choice#expand">Show all</button>
        </fieldset>`;
    const disabled = () =>
        [...document.querySelectorAll('input')]
            .filter((box) => box.disabled)
            .map((box) => box.id);

    function check(id, checked) {
        const box = document.getElementById(id);
        box.checked = checked;
        box.dispatchEvent(new Event('change', { bubbles: true }));
    }

    it('disables the other chips once the maximum is checked, and frees them again', async () => {
        await mount(page(chip(1, true) + chip(2) + chip(3)), {
            'tag-choice': TagChoice,
        });
        expect(disabled()).toEqual([]);

        check('t2', true);
        expect(disabled()).toEqual(['t3']);

        check('t1', false);
        expect(disabled()).toEqual([]);
    });

    it('starts full when the saved tags already reach the maximum', async () => {
        await mount(page(chip(1, true) + chip(2, true) + chip(3)), {
            'tag-choice': TagChoice,
        });

        expect(disabled()).toEqual(['t3']);
    });

    it('collapses the extra chips until "Show all", then focuses the first one revealed', async () => {
        await mount(
            page(chip(1) + chip(2, true, true) + chip(3, false, true)),
            { 'tag-choice': TagChoice }
        );
        const fieldset = document.querySelector('fieldset');
        expect(fieldset.hasAttribute('data-collapsed')).toBe(true);

        document.querySelector('button').click();
        await settle();

        expect(fieldset.hasAttribute('data-collapsed')).toBe(false);
        expect(document.querySelector('button')).toBeNull();
        expect(document.activeElement.id).toBe('t3');
    });

    it('shows every chip when there is nothing to collapse', async () => {
        await mount(page(chip(1) + chip(2)), { 'tag-choice': TagChoice });

        expect(
            document.querySelector('fieldset').hasAttribute('data-collapsed')
        ).toBe(false);
    });
});

describe('file-preview', () => {
    const page = `
        <div data-controller="file-preview">
            <input type="file" data-file-preview-target="input" data-action="file-preview#show">
            <span data-file-preview-target="name"></span>
            <img data-file-preview-target="image" src="data:," hidden>
        </div>`;
    const image = () => document.querySelector('img');

    function choose(...files) {
        const input = document.querySelector('input');
        Object.defineProperty(input, 'files', {
            value: files,
            configurable: true,
        });
        // A browser fires input, then change
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function objectUrls() {
        let n = 0;
        URL.createObjectURL = vi.fn(
            () => `blob:https://captaincoaster.test/${++n}`
        );
        URL.revokeObjectURL = vi.fn();
    }

    it('shows the name and a preview of the chosen photo', async () => {
        objectUrls();
        await mount(page, { 'file-preview': FilePreview });

        choose(new File(['x'], 'taron.jpg', { type: 'image/jpeg' }));

        expect(document.querySelector('span').textContent).toBe('taron.jpg');
        expect(image().hidden).toBe(false);
        expect(image().src).toBe('blob:https://captaincoaster.test/1');
    });

    it('frees the previous preview when another photo is chosen', async () => {
        objectUrls();
        await mount(page, { 'file-preview': FilePreview });

        choose(new File(['x'], 'one.jpg', { type: 'image/jpeg' }));
        choose(new File(['x'], 'two.jpg', { type: 'image/jpeg' }));

        expect(URL.revokeObjectURL).toHaveBeenCalledWith(
            'blob:https://captaincoaster.test/1'
        );
        expect(image().src).toBe('blob:https://captaincoaster.test/2');
    });

    it.each([
        [
            'a file that is not an image',
            [new File(['x'], 'notes.pdf', { type: 'application/pdf' })],
            'notes.pdf',
        ],
        ['no file (choice cancelled)', [], ''],
    ])('shows no preview for %s', async (_, files, name) => {
        objectUrls();
        await mount(page, { 'file-preview': FilePreview });
        choose(new File(['x'], 'one.jpg', { type: 'image/jpeg' }));

        choose(...files);

        expect(document.querySelector('span').textContent).toBe(name);
        expect(image().hidden).toBe(true);
        expect(image().src).toBe('data:,');
    });
});
