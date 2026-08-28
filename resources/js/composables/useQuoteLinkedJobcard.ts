import { computed, toValue, type MaybeRefOrGetter } from 'vue';

export type QuoteLinkedJobcard = {
    id: number;
    job_number: string;
    title: string;
};

export type QuoteLinkedJobcardSource = {
    converted_jobcard_id?: number | null;
    converted_jobcard?: QuoteLinkedJobcard | null;
    convertedJobcard?: QuoteLinkedJobcard | null;
};

function asQuoteLinkedJobcardSource(value: unknown): QuoteLinkedJobcardSource {
    if (!value || typeof value !== 'object') {
        return {};
    }

    return value as QuoteLinkedJobcardSource;
}

export function useQuoteLinkedJobcard(
    quote: MaybeRefOrGetter<unknown>,
    linkedJobcard?: MaybeRefOrGetter<QuoteLinkedJobcard | null | undefined>,
) {
    return computed<QuoteLinkedJobcard | null>(() => {
        const explicit = linkedJobcard ? toValue(linkedJobcard) : undefined;
        if (explicit?.id) {
            return explicit;
        }

        const quoteValue = asQuoteLinkedJobcardSource(toValue(quote));
        if (quoteValue.converted_jobcard?.id) {
            return quoteValue.converted_jobcard;
        }
        if (quoteValue.convertedJobcard?.id) {
            return quoteValue.convertedJobcard;
        }
        if (quoteValue.converted_jobcard_id) {
            return {
                id: quoteValue.converted_jobcard_id,
                job_number: `Jobcard #${quoteValue.converted_jobcard_id}`,
                title: '',
            };
        }

        return null;
    });
}
