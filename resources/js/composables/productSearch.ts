export interface ProductSearchCandidate {
    name?: string | null;
    sku?: string | null;
}

const normalizeProductSearchTerm = (value: string | null | undefined): string => {
    return String(value ?? '').trim().toLocaleLowerCase();
};

export const matchesProductSearch = (
    product: ProductSearchCandidate,
    query: string | null | undefined,
): boolean => {
    const normalizedQuery = normalizeProductSearchTerm(query);

    if (normalizedQuery.length < 2) {
        return false;
    }

    const normalizedName = normalizeProductSearchTerm(product.name);
    const normalizedSku = normalizeProductSearchTerm(product.sku);

    return normalizedName.includes(normalizedQuery) || normalizedSku.includes(normalizedQuery);
};
