import { computed, type ComputedRef, type Ref } from 'vue';

export type QuoteLinkedJobcard = {
    id: number;
    job_number: string;
    title: string;
};

type QuoteWithLinkedJobcard = {
    converted_jobcard_id?: number | null;
    converted_jobcard?: QuoteLinkedJobcard | null;
    convertedJobcard?: QuoteLinkedJobcard | null;
};

export function useQuoteLinkedJobcard(
    quote: Ref<QuoteWithLinkedJobcard> | ComputedRef<QuoteWithLinkedJobcard>,
    linkedJobcard?: Ref<QuoteLinkedJobcard | null | undefined> | ComputedRef<QuoteLinkedJobcard | null | undefined>,
) {
    return computed<QuoteLinkedJobcard | null>(() => {
        const explicit = linkedJobcard?.value;
        if (explicit?.id) {
            return explicit;
        }

        const quoteValue = quote.value;
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
