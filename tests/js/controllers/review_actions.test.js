import { Controller } from '@hotwired/stimulus';
import { describe, expect, it, vi } from 'vitest';
import CsrfProtection from '../../../assets/controllers/csrf_protection_controller';
import ReviewActions from '../../../assets/controllers/review_actions_controller';
import Toast from '../../../assets/controllers/toast_controller';
import { mount, settle, stubFetch, stubViewport } from '../support/stimulus';

const modal = { show: vi.fn(), hide: vi.fn() };
class Modal extends Controller {
    show = modal.show;
    hide = modal.hide;
}

const LONG = 'x'.repeat(200);

const page = ({ upvoted = false, text = 'Great ride' } = {}) => `
    <div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok"></div>
    <div id="toasts" data-controller="toast"></div>
    <ul><li id="item"><h3>Taron</h3>
        <article data-controller="review-actions" data-review-actions-id-value="5"
                 data-review-actions-upvoted-value="${upvoted}"
                 data-review-actions-upvote-url-value="/en/reviews/5/upvote"
                 data-review-actions-report-url-value="/en/reviews/5/report"
                 data-review-actions-delete-url-value="/en/ratings/5"
                 data-review-actions-modal-outlet="#report"
                 data-review-actions-csrf-protection-outlet="#csrf">
            <div data-review-actions-target="reviewContent">
                <span class="review-short">${text.slice(0, 20)} <a class="expand-review" data-action="review-actions#toggleReview">more</a></span>
                <span class="review-full" style="display: none">${text} <a class="collapse-review" data-action="review-actions#toggleReview">less</a></span>
            </div>
            <button id="upvote" data-review-actions-target="upvoteButton" data-action="review-actions#upvote">
                <span data-review-actions-target="upvoteCount">2</span>
            </button>
            <button id="open-report" data-review-actions-target="reportButton" data-action="review-actions#openReportModal">Report</button>
            <button id="delete" data-action="review-actions#deleteReview">Delete</button>
            <div id="report" data-controller="modal">
                <form data-action="review-actions#submitReport"><input name="reason" value="spam"><button type="submit">Report</button></form>
            </div>
        </article>
    </li></ul>`;

const controllers = {
    'review-actions': ReviewActions,
    'csrf-protection': CsrfProtection,
    toast: Toast,
    modal: Modal,
};
const toast = (type) =>
    document.querySelector(`.notification--${type} .notification__message`)
        ?.textContent;

async function start(options) {
    stubViewport(options);
    await mount(page(options), controllers);
    modal.show.mockClear();
    modal.hide.mockClear();
}

describe('review-actions', () => {
    describe('upvote', () => {
        it('shows the count and state the server answers', async () => {
            const fetch = stubFetch({
                body: { success: true, action: 'added', upvoteCount: 3 },
            });
            await start();

            document.getElementById('upvote').click();
            await settle();

            expect(fetch.mock.calls[0]).toEqual([
                '/en/reviews/5/upvote',
                expect.objectContaining({ method: 'POST' }),
            ]);
            expect(
                document.getElementById('upvote').classList.contains('active')
            ).toBe(true);
            expect(document.getElementById('upvote').title).toBe(
                'review.remove_upvote'
            );
            expect(
                document.querySelector(
                    '[data-review-actions-target="upvoteCount"]'
                ).textContent
            ).toBe('3');
        });

        it('removes an upvote', async () => {
            stubFetch({
                body: { success: true, action: 'removed', upvoteCount: 1 },
            });
            await start({ upvoted: true });
            expect(
                document.getElementById('upvote').classList.contains('active')
            ).toBe(true);

            document.getElementById('upvote').click();
            await settle();

            expect(
                document.getElementById('upvote').classList.contains('active')
            ).toBe(false);
            expect(
                document.querySelector(
                    '[data-review-actions-target="upvoteCount"]'
                ).textContent
            ).toBe('1');
        });

        it('changes nothing when the server refuses (own review)', async () => {
            stubFetch({ body: { success: false, error: 'own review' } });
            vi.spyOn(console, 'warn').mockImplementation(() => {});
            await start();

            document.getElementById('upvote').click();
            await settle();

            expect(
                document.getElementById('upvote').classList.contains('active')
            ).toBe(false);
            expect(
                document.querySelector(
                    '[data-review-actions-target="upvoteCount"]'
                ).textContent
            ).toBe('2');
        });
    });

    describe('delete', () => {
        it('removes the whole list item after confirmation', async () => {
            const fetch = stubFetch({ body: { state: 'success' } });
            vi.stubGlobal(
                'confirm',
                vi.fn(() => true)
            );
            await start();

            document.getElementById('delete').click();
            await settle();

            expect(fetch.mock.calls[0]).toEqual([
                '/en/ratings/5',
                expect.objectContaining({
                    method: 'DELETE',
                    body: '_token=tok',
                }),
            ]);
            expect(document.getElementById('item')).toBeNull();
            expect(toast('success')).toBe('review.delete_success');
        });

        it('deletes nothing when the rider cancels', async () => {
            const fetch = stubFetch();
            vi.stubGlobal(
                'confirm',
                vi.fn(() => false)
            );
            await start();

            document.getElementById('delete').click();
            await settle();

            expect(fetch).not.toHaveBeenCalled();
            expect(document.getElementById('item')).not.toBeNull();
        });

        it.each([
            ['the server says no', { body: { state: 'error' } }],
            [
                'the answer is not JSON (403 page)',
                { status: 403, body: '<html>' },
            ],
        ])('keeps the review and says so when %s', async (_, response) => {
            stubFetch(response);
            vi.stubGlobal(
                'confirm',
                vi.fn(() => true)
            );
            vi.spyOn(console, 'error').mockImplementation(() => {});
            await start();

            document.getElementById('delete').click();
            await settle();

            expect(document.getElementById('item')).not.toBeNull();
            expect(toast('danger')).toBe('review.delete_error');
        });
    });

    describe('report', () => {
        const submit = () =>
            document
                .querySelector('form')
                .dispatchEvent(
                    new Event('submit', { bubbles: true, cancelable: true })
                );
        const submitButton = () => document.querySelector('form button');

        it('opens the report dialog', async () => {
            await start();

            document.getElementById('open-report').click();

            expect(modal.show).toHaveBeenCalledOnce();
        });

        it('sends the report, closes the dialog and disables further reports', async () => {
            const fetch = stubFetch({ body: { success: true } });
            await start();

            submit();
            await settle();

            const [url, request] = fetch.mock.calls[0];
            expect(url).toBe('/en/reviews/5/report');
            expect(request.body.get('reason')).toBe('spam');
            expect(modal.hide).toHaveBeenCalledOnce();
            expect(document.getElementById('open-report').disabled).toBe(true);
            expect(toast('success')).toBe('review.report_success');
        });

        it('sends a report once when submitted twice', async () => {
            const fetch = stubFetch({ body: { success: true } });
            await start();

            submit();
            submit();
            await settle();

            expect(fetch).toHaveBeenCalledOnce();
        });

        it('shows the server message and lets the rider try again when refused', async () => {
            stubFetch({
                body: { success: false, message: 'Already reported' },
            });
            await start();

            submit();
            await settle();

            expect(toast('danger')).toBe('Already reported');
            expect(submitButton().disabled).toBe(false);
            expect(submitButton().textContent).toBe('review.submit_report');
            expect(modal.hide).not.toHaveBeenCalled();
        });

        it('lets the rider try again after a network failure', async () => {
            stubFetch(new Error('offline'));
            vi.spyOn(console, 'error').mockImplementation(() => {});
            await start();

            submit();
            await settle();

            expect(toast('danger')).toBe('review.report_error');
            expect(submitButton().disabled).toBe(false);
        });
    });

    describe('long reviews', () => {
        const short = () => document.querySelector('.review-short');
        const full = () => document.querySelector('.review-full');

        it('cuts a long review to the phone length', async () => {
            await start({ text: LONG });

            expect(short().childNodes[0].textContent).toBe(
                `${'x'.repeat(150)}... `
            );
            expect(full().style.display).toBe('none');
        });

        it('shows a review that fits in full, without expand links', async () => {
            await start({ text: LONG, tablet: true });

            expect(short().style.display).toBe('none');
            expect(full().style.display).toBe('block');
            expect(document.querySelector('.expand-review').style.display).toBe(
                'none'
            );
            expect(
                document.querySelector('.collapse-review').style.display
            ).toBe('none');
        });

        it('expands and collapses', async () => {
            await start({ text: LONG });

            document.querySelector('.expand-review').click();
            expect([short().style.display, full().style.display]).toEqual([
                'none',
                'block',
            ]);

            document.querySelector('.collapse-review').click();
            expect([short().style.display, full().style.display]).toEqual([
                'block',
                'none',
            ]);
        });
    });
});
