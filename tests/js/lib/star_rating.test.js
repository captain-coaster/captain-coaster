import { describe, expect, it } from 'vitest';
import { renderStarRating } from '../../../assets/js/utils/star-rating';

const states = (rating) => {
    const holder = document.createElement('div');
    holder.innerHTML = renderStarRating(rating);

    return [...holder.querySelectorAll('svg')].map((star) =>
        star.getAttribute('class').replace('star-icon star-icon--', '')
    );
};

describe('renderStarRating', () => {
    it.each([
        [5, ['full', 'full', 'full', 'full', 'full']],
        [3.5, ['full', 'full', 'full', 'half', 'empty']],
        ['4.0', ['full', 'full', 'full', 'full', 'empty']],
        [0.5, ['half', 'empty', 'empty', 'empty', 'empty']],
    ])('draws %s', (rating, expected) => {
        expect(states(rating)).toEqual(expected);
    });

    it('labels the stars for screen readers', () => {
        expect(renderStarRating(4.5)).toContain(
            'role="img" aria-label="4.5/5"'
        );
    });

    it.each([[null], [undefined], [''], [0], ['abc'], [-1], [5.5]])(
        'draws nothing for %s',
        (rating) => {
            expect(renderStarRating(rating)).toBe('');
        }
    );
});
