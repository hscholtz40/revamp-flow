import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useProductSuggestionDropdown() {
    const showProductSuggestions = ref<Record<number, boolean>>({});
    const activeSuggestionIndex = ref<number | null>(null);
    const dropdownStyle = ref<{ top: string; left: string; width: string } | null>(null);
    const descriptionInputRefs = ref<Record<number, HTMLInputElement | null>>({});
    let scrollParent: HTMLElement | null = null;

    const setDescriptionInputRef = (index: number, el: unknown) => {
        descriptionInputRefs.value[index] = (el as HTMLInputElement | null) ?? null;
    };

    const updateDropdownPosition = (index: number) => {
        const input = descriptionInputRefs.value[index];
        if (!input) {
            return;
        }

        const rect = input.getBoundingClientRect();
        dropdownStyle.value = {
            top: `${rect.bottom + 4}px`,
            left: `${rect.left}px`,
            width: `${rect.width}px`,
        };
        activeSuggestionIndex.value = index;
    };

    const handleDescriptionFocus = (index: number) => {
        showProductSuggestions.value[index] = true;
        updateDropdownPosition(index);
    };

    const handleDescriptionInput = (index: number) => {
        showProductSuggestions.value[index] = true;
        updateDropdownPosition(index);
    };

    const handleDescriptionBlur = (index: number) => {
        setTimeout(() => {
            showProductSuggestions.value[index] = false;
            if (activeSuggestionIndex.value === index) {
                activeSuggestionIndex.value = null;
                dropdownStyle.value = null;
            }
        }, 200);
    };

    const closeProductSuggestions = (index: number) => {
        showProductSuggestions.value[index] = false;
        if (activeSuggestionIndex.value === index) {
            activeSuggestionIndex.value = null;
            dropdownStyle.value = null;
        }
    };

    const repositionActiveDropdown = () => {
        if (activeSuggestionIndex.value !== null) {
            updateDropdownPosition(activeSuggestionIndex.value);
        }
    };

    const registerLineItemsScrollParent = (el: HTMLElement | null) => {
        scrollParent?.removeEventListener('scroll', repositionActiveDropdown);
        scrollParent = el;
        scrollParent?.addEventListener('scroll', repositionActiveDropdown, { passive: true });
    };

    onMounted(() => {
        window.addEventListener('scroll', repositionActiveDropdown, true);
        window.addEventListener('resize', repositionActiveDropdown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', repositionActiveDropdown, true);
        window.removeEventListener('resize', repositionActiveDropdown);
        scrollParent?.removeEventListener('scroll', repositionActiveDropdown);
    });

    return {
        activeSuggestionIndex,
        closeProductSuggestions,
        dropdownStyle,
        handleDescriptionBlur,
        handleDescriptionFocus,
        handleDescriptionInput,
        registerLineItemsScrollParent,
        setDescriptionInputRef,
        showProductSuggestions,
    };
}
