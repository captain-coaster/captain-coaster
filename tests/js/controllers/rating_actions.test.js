import { describe, expect, it, vi } from 'vitest';
import CsrfProtection from '../../../assets/controllers/csrf_protection_controller';
import Rating from '../../../assets/controllers/rating_controller';
import RatingActions from '../../../assets/controllers/rating_actions_controller';
import Toast from '../../../assets/controllers/toast_controller';
import { mount, settle, stubFetch } from '../support/stimulus';

const actions = (ratingId, mode = '') => `
    <div id="actions" data-controller="rating-actions"
         data-rating-actions-rating-id-value="${ratingId}" data-rating-actions-locale-value="de"
         data-rating-actions-mode-value="${mode}"
         data-rating-actions-rate-text-value="Rate it" data-rating-actions-my-rating-text-value="My rating"
         data-rating-actions-csrf-protection-outlet="#csrf">
        <h2 data-rating-actions-target="title"></h2>
        <button data-rating-actions-target="deleteButton" data-action="rating-actions#delete">Delete</button>
    </div>`;

const page = (ratingId = '') => `
    <div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok"></div>
    <div id="toasts" data-controller="toast"></div>
    <div id="stars" data-controller="rating" data-rating-coaster-id-value="42" data-rating-locale-value="de"
         data-rating-current-value-value="${ratingId ? 4 : 0}" data-rating-rating-id-value="${ratingId}"></div>
    ${actions(ratingId)}`;

const controllers = {
    rating: Rating,
    'rating-actions': RatingActions,
    'csrf-protection': CsrfProtection,
    toast: Toast,
};
const button = () => document.querySelector('button');
const title = () => document.querySelector('h2').textContent;

describe('rating-actions', () => {
    it('hides the delete button until the coaster is rated', async () => {
        await mount(page(), controllers);

        expect(button().style.display).toBe('none');
        expect(title()).toBe('Rate it');

        document.dispatchEvent(
            new CustomEvent('rating:created', { detail: { ratingId: 77 } })
        );
        await settle();

        expect(button().style.display).toBe('inline-flex');
        expect(title()).toBe('My rating');
    });

    it('deletes the rating after confirmation, then empties the stars', async () => {
        const fetch = stubFetch({ body: { state: 'success' } });
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true)
        );
        await mount(page(77), controllers);

        button().click();
        await settle();

        expect(Routing.generate).toHaveBeenCalledWith('rating_delete', {
            id: 77,
            _locale: 'de',
        });
        expect(fetch.mock.calls[0][1]).toMatchObject({
            method: 'DELETE',
            body: '_token=tok',
        });
        expect(document.querySelectorAll('.star-empty')).toHaveLength(5);
        expect(button().style.display).toBe('none');
        expect(title()).toBe('Rate it');
    });

    it('deletes nothing when the rider cancels', async () => {
        const fetch = stubFetch();
        vi.stubGlobal(
            'confirm',
            vi.fn(() => false)
        );
        await mount(page(77), controllers);

        button().click();
        await settle();

        expect(fetch).not.toHaveBeenCalled();
        expect(document.querySelectorAll('.star-full')).toHaveLength(4);
    });

    it('keeps the rating and says so when the deletion fails', async () => {
        stubFetch({ status: 403 });
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true)
        );
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(77), controllers);

        button().click();
        await settle();

        expect(document.querySelectorAll('.star-full')).toHaveLength(4);
        expect(button().style.display).toBe('inline-flex');
        expect(
            document.querySelector(
                '.notification--danger .notification__message'
            ).textContent
        ).toBe('rating.delete_error');
    });

    it('removes the row in a ratings table', async () => {
        stubFetch({ body: { state: 'success' } });
        vi.stubGlobal(
            'confirm',
            vi.fn(() => true)
        );
        await mount(
            `<div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok"></div>
             <table><tbody><tr id="kept"><td></td></tr><tr id="row"><td>${actions(77, 'table')}</td></tr></tbody></table>`,
            controllers
        );

        button().click();
        await settle();

        expect(document.getElementById('row')).toBeNull();
        expect(document.getElementById('kept')).not.toBeNull();
    });
});
