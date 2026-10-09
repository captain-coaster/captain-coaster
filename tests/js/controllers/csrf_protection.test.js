import { describe, expect, it } from 'vitest';
import CsrfProtection from '../../../assets/controllers/csrf_protection_controller';
import { controllerOf, mount } from '../support/stimulus';

describe('csrf-protection', () => {
    it('adds its token to a urlencoded body and to form data', async () => {
        await mount(
            '<div id="csrf" data-controller="csrf-protection" data-csrf-protection-token-value="tok/en+1"></div>',
            {
                'csrf-protection': CsrfProtection,
            }
        );
        const csrf = controllerOf('#csrf', 'csrf-protection');

        expect(csrf.getToken()).toBe('tok/en+1');
        expect(csrf.addTokenToBody('value=4.5')).toBe(
            'value=4.5&_token=tok%2Fen%2B1'
        );
        expect(csrf.addTokenToFormData(new FormData()).get('_token')).toBe(
            'tok/en+1'
        );
    });
});
