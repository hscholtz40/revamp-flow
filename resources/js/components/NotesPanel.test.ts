import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useDateTimeFormat', () => ({
    useDateTimeFormat: () => ({
        formatDateTime: (value: string) => `formatted:${value}`,
    }),
}));

vi.mock('@/composables/useSafeExternalUrl', () => ({
    getSafeExternalUrl: (value: string | null | undefined) => value ?? null,
}));

import NotesPanel from './NotesPanel.vue';

describe('NotesPanel', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="notes-token">';
        vi.stubGlobal('fetch', vi.fn());
    });

    it('loads and renders notes for a locked record context', async () => {
        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: 1,
                        subject: 'Loaded note',
                        description: 'Coverage note body',
                        created_at: '2024-05-01T10:00:00Z',
                        user: { id: 1, name: 'Alex' },
                    },
                ],
                current_page: 1,
                last_page: 1,
                total: 1,
            }),
        } as Response);

        const wrapper = mount(NotesPanel, {
            props: {
                module: 'customers',
                recordId: 5,
            },
        });

        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            '/notes/record?module=customers&record_id=5&page=1&per_page=10',
            expect.any(Object),
        );
        expect(wrapper.text()).toContain('Loaded note');
        expect(wrapper.text()).toContain('formatted:2024-05-01T10:00:00Z');
    });

    it('shows a validation message when trying to submit without a subject', async () => {
        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                data: [],
                current_page: 1,
                last_page: 1,
                total: 0,
            }),
        } as Response);

        const wrapper = mount(NotesPanel, {
            props: {
                module: 'customers',
                recordId: 5,
            },
        });

        await flushPromises();
        await wrapper.get('button').trigger('click');
        await wrapper.findAll('button').at(-1)!.trigger('click');

        expect(wrapper.text()).toContain('Subject and related record are required.');
    });
});
