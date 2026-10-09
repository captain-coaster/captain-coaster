import { describe, expect, it, vi } from 'vitest';
import ImageLike from '../../../assets/controllers/image_like_controller';
import { mount, settle, stubFetch } from '../support/stimulus';

const page = (liked) => `
    <button data-controller="image-like" data-action="image-like#toggle" data-image-like-image-id-value="9"
            data-image-like-locale-value="es" data-image-like-liked-value="${liked}">
        <span data-image-like-target="icon" aria-pressed="${liked}"></span>
        <span data-image-like-target="counter">3</span>
    </button>`;

const pressed = () =>
    document
        .querySelector('[data-image-like-target="icon"]')
        .getAttribute('aria-pressed');
const count = () =>
    document.querySelector('[data-image-like-target="counter"]').textContent;

describe('image-like', () => {
    it('shows the state and count the server answers', async () => {
        const fetch = stubFetch({ body: { liked: true, likeCount: 4 } });
        await mount(page(false), { 'image-like': ImageLike });

        document.querySelector('button').click();
        await settle();

        expect(Routing.generate).toHaveBeenCalledWith('like_image_async', {
            id: 9,
            _locale: 'es',
        });
        expect(fetch).toHaveBeenCalledOnce();
        expect(pressed()).toBe('true');
        expect(count()).toBe('4');
    });

    it('unlikes', async () => {
        stubFetch({ body: { liked: false, likeCount: 2 } });
        await mount(page(true), { 'image-like': ImageLike });

        document.querySelector('button').click();
        await settle();

        expect(pressed()).toBe('false');
        expect(count()).toBe('2');
    });

    it.each([
        ['their own photo (403)', { status: 403 }],
        ['a server error', { status: 500 }],
        ['a network failure', new Error('offline')],
    ])('changes nothing on %s', async (_, response) => {
        stubFetch(response);
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await mount(page(false), { 'image-like': ImageLike });

        document.querySelector('button').click();
        await settle();

        expect(pressed()).toBe('false');
        expect(count()).toBe('3');
    });
});
