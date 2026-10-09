import { describe, expect, it, vi } from 'vitest';
import SummaryFeedback from '../../../assets/controllers/summary_feedback_controller';
import Toast from '../../../assets/controllers/toast_controller';
import { mount, settle, stubFetch } from '../support/stimulus';

const page = ({ vote = '' } = {}) => `
    <div id="toasts" data-controller="toast"></div>
    <div data-controller="summary-feedback" data-summary-feedback-feedback-url-value="/en/summary/3/feedback"
         data-summary-feedback-csrf-token-value="tok" data-summary-feedback-user-vote-value="${vote}"
         data-summary-feedback-has-voted-value="${vote !== ''}"
         data-summary-feedback-success-message-value="Thanks" data-summary-feedback-error-message-value="Not sent"
         data-summary-feedback-helpful-title-value="Helpful" data-summary-feedback-not-helpful-title-value="Not helpful"
         data-summary-feedback-voted-helpful-title-value="You found it helpful"
         data-summary-feedback-voted-not-helpful-title-value="You found it unhelpful">
        <button id="up" data-summary-feedback-target="thumbsUpButton" data-action="summary-feedback#thumbsUp"></button>
        <button id="down" data-summary-feedback-target="thumbsDownButton" data-action="summary-feedback#thumbsDown"></button>
        <span id="positive" data-summary-feedback-target="positiveCount">4</span>
        <span id="negative" data-summary-feedback-target="negativeCount">1</span>
    </div>`;

const controllers = { 'summary-feedback': SummaryFeedback, toast: Toast };
const up = () => document.getElementById('up');
const down = () => document.getElementById('down');
const toast = (type) =>
    document.querySelector(`.notification--${type} .notification__message`)
        ?.textContent;

describe('summary-feedback', () => {
    it('shows the vote the rider already cast', async () => {
        await mount(page({ vote: 'negative' }), controllers);

        expect(down().classList.contains('voted')).toBe(true);
        expect(down().title).toBe('You found it unhelpful');
        expect(up().classList.contains('voted')).toBe(false);
        expect(up().title).toBe('Helpful');
    });

    it('sends a thumbs up and shows the new counts', async () => {
        const fetch = stubFetch({
            body: {
                success: true,
                hasVoted: true,
                userVote: true,
                positiveVotes: 5,
                negativeVotes: 1,
            },
        });
        await mount(page(), controllers);

        up().click();
        await settle();

        const [url, request] = fetch.mock.calls[0];
        expect(url).toBe('/en/summary/3/feedback');
        expect(Object.fromEntries(request.body)).toEqual({
            isPositive: 'true',
            _token: 'tok',
        });
        expect(up().classList.contains('voted')).toBe(true);
        expect(document.getElementById('positive').textContent).toBe('5');
        expect(toast('success')).toBe('Thanks');
    });

    it('switches from thumbs up to thumbs down', async () => {
        const fetch = stubFetch({
            body: {
                success: true,
                hasVoted: true,
                userVote: false,
                positiveVotes: 3,
                negativeVotes: 2,
            },
        });
        await mount(page({ vote: 'positive' }), controllers);

        down().click();
        await settle();

        expect(fetch.mock.calls[0][1].body.get('isPositive')).toBe('false');
        expect(up().classList.contains('voted')).toBe(false);
        expect(down().classList.contains('voted')).toBe(true);
        expect([
            document.getElementById('positive').textContent,
            document.getElementById('negative').textContent,
        ]).toEqual(['3', '2']);
    });

    it('clears the vote when the same thumb is pressed again', async () => {
        stubFetch({
            body: {
                success: true,
                hasVoted: false,
                userVote: null,
                positiveVotes: 3,
                negativeVotes: 1,
            },
        });
        await mount(page({ vote: 'positive' }), controllers);

        up().click();
        await settle();

        expect(up().classList.contains('voted')).toBe(false);
        expect(down().classList.contains('voted')).toBe(false);
        expect(up().title).toBe('Helpful');
    });

    it('sends one vote while a request is pending, then frees the buttons', async () => {
        const fetch = stubFetch({
            body: {
                success: true,
                hasVoted: true,
                userVote: true,
                positiveVotes: 5,
            },
        });
        await mount(page(), controllers);

        up().click();
        expect(up().disabled && down().disabled).toBe(true);
        down().click();
        await settle();

        expect(fetch).toHaveBeenCalledOnce();
        expect(up().disabled || down().disabled).toBe(false);
    });

    it.each([
        [
            'the server message when refused',
            { body: { success: false, message: 'Sign in first' } },
            'Sign in first',
        ],
        [
            'its own message on a network failure',
            new Error('offline'),
            'Not sent',
        ],
    ])('keeps the counts and shows %s', async (_, response, message) => {
        stubFetch(response);
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(), controllers);

        up().click();
        await settle();

        expect(toast('danger')).toBe(message);
        expect(up().classList.contains('voted')).toBe(false);
        expect(document.getElementById('positive').textContent).toBe('4');
        expect(up().disabled).toBe(false);
    });
});
