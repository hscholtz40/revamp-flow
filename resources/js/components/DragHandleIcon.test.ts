import { mount } from '@vue/test-utils';
import DragHandleIcon from './DragHandleIcon.vue';

describe('DragHandleIcon', () => {
    it('renders the shared drag handle affordance', () => {
        const wrapper = mount(DragHandleIcon);

        const icon = wrapper.find('[aria-hidden="true"]');
        expect(icon.exists()).toBe(true);
        expect(icon.classes()).toContain('cursor-grab');
    });
});
