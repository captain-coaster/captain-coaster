import { beforeEach, describe, expect, it } from 'vitest';
import Gallery from '../../../assets/controllers/gallery_controller';
import { mount, stubDialog } from '../support/stimulus';

const page = `<div data-controller="gallery">${[1, 2, 3]
    .map(
        (n) =>
            `<a id="p${n}" href="https://pictures.test/${n}.jpg" data-avif="https://pictures.test/${n}.avif" data-gallery-target="link"><img></a>`
    )
    .join('')}</div>`;

const lightbox = () =>
    document.querySelector('dialog.captain-gallery-lightbox');
const shown = () => [
    lightbox().querySelector('img').src,
    lightbox().querySelector('source').srcset,
];
const picture = (n) => [
    `https://pictures.test/${n}.jpg`,
    `https://pictures.test/${n}.avif`,
];
const press = (name) =>
    lightbox().querySelector(`.captain-gallery-${name}`).click();

function swipe(from, to) {
    for (const [type, screenX] of [
        ['touchstart', from],
        ['touchend', to],
    ]) {
        const event = new Event(type, { bubbles: true });
        event.changedTouches = [{ screenX }];
        lightbox().dispatchEvent(event);
    }
}

describe('gallery', () => {
    beforeEach(async () => {
        stubDialog();
        await mount(page, { gallery: Gallery });
    });

    it('opens the clicked photo full screen, avif first, instead of following the link', () => {
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        document.getElementById('p2').dispatchEvent(click);

        expect(click.defaultPrevented).toBe(true);
        expect(lightbox().open).toBe(true);
        expect(shown()).toEqual(picture(2));
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('shows a loader until the photo has loaded', () => {
        document.getElementById('p1').click();
        const image = lightbox().querySelector('img');
        const loader = lightbox().querySelector('.captain-gallery-loader');
        expect([loader.style.display, image.style.display]).toEqual([
            'block',
            'none',
        ]);

        image.onload();

        expect([loader.style.display, image.style.display]).toEqual([
            'none',
            'block',
        ]);
    });

    it('moves through the photos and wraps around at both ends', () => {
        document.getElementById('p1').click();

        press('prev');
        expect(shown()).toEqual(picture(3));
        press('next');
        expect(shown()).toEqual(picture(1));
        press('next');
        expect(shown()).toEqual(picture(2));
    });

    it('moves with the arrow keys and with swipes', () => {
        document.getElementById('p1').click();

        document.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowRight' })
        );
        expect(shown()).toEqual(picture(2));
        document.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'ArrowLeft' })
        );
        expect(shown()).toEqual(picture(1));

        swipe(300, 200);
        expect(shown()).toEqual(picture(2));
        swipe(200, 300);
        expect(shown()).toEqual(picture(1));
        swipe(200, 230);
        expect(shown()).toEqual(picture(1));
    });

    it.each([
        ['its close button', () => press('close')],
        ['a click on the backdrop', () => lightbox().click()],
        [
            'Escape',
            () =>
                lightbox().dispatchEvent(
                    new Event('cancel', { cancelable: true })
                ),
        ],
    ])('closes on %s and frees the page', (_, close) => {
        document.getElementById('p1').click();

        close();

        expect(lightbox()).toBeNull();
        expect(document.body.style.overflow).toBe('');
        expect(() =>
            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'ArrowRight' })
            )
        ).not.toThrow();
    });

    it('stays open on a click on the photo', () => {
        document.getElementById('p1').click();

        lightbox().querySelector('img').click();

        expect(lightbox().open).toBe(true);
    });
});
