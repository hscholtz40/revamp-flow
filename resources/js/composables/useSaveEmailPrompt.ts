import { ref } from 'vue';

export const OPEN_DOCUMENT_EMAIL_MODAL_KEY = 'openDocumentEmailModal';

export function markOpenEmailModalAfterRedirect(): void {
    sessionStorage.setItem(OPEN_DOCUMENT_EMAIL_MODAL_KEY, '1');
}

export function consumeOpenEmailModalFlag(): boolean {
    const shouldOpen = sessionStorage.getItem(OPEN_DOCUMENT_EMAIL_MODAL_KEY) === '1';
    if (shouldOpen) {
        sessionStorage.removeItem(OPEN_DOCUMENT_EMAIL_MODAL_KEY);
    }

    return shouldOpen;
}

export function useSaveEmailPrompt() {
    const showSaveEmailPrompt = ref(false);
    let pendingSave: ((emailNow: boolean) => void) | null = null;

    const promptBeforeSave = (saveFn: (emailNow: boolean) => void) => {
        pendingSave = saveFn;
        showSaveEmailPrompt.value = true;
    };

    const cancelSaveEmailPrompt = () => {
        showSaveEmailPrompt.value = false;
        pendingSave = null;
    };

    const confirmSaveEmailChoice = (emailNow: boolean) => {
        showSaveEmailPrompt.value = false;
        const saveFn = pendingSave;
        pendingSave = null;
        saveFn?.(emailNow);
    };

    return {
        cancelSaveEmailPrompt,
        confirmSaveEmailChoice,
        promptBeforeSave,
        showSaveEmailPrompt,
    };
}
