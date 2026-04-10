import { getCsrfToken } from './csrf';

describe('getCsrfToken', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('returns the csrf token from the meta tag', () => {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'test-token');
        document.head.appendChild(meta);

        expect(getCsrfToken()).toBe('test-token');
    });

    it('returns an empty string when the meta tag is missing', () => {
        expect(getCsrfToken()).toBe('');
    });
});
