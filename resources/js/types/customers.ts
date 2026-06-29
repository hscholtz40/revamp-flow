export interface CustomerLookupCustomer {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    account_code?: string | null;
    terms?: string | null;
}

export interface QuickCreateCustomerPayload {
    name: string;
    registration_number: string;
    email: string;
    company_cell: string;
    company_tel: string;
    address: string;
    city: string;
    country: string;
    contact_first_name: string;
    contact_last_name: string;
    contact_cell: string;
    contact_email: string;
    terms: string;
    vat_number: string;
}

export function createQuickCreateCustomerDefaults(name = ''): QuickCreateCustomerPayload {
    return {
        name,
        registration_number: '',
        email: '',
        company_cell: '',
        company_tel: '',
        address: '',
        city: '',
        country: '',
        contact_first_name: '',
        contact_last_name: '',
        contact_cell: '',
        contact_email: '',
        terms: 'COD',
        vat_number: '',
    };
}

export interface QuickCreateCustomerResponse {
    success?: boolean;
    customer?: CustomerLookupCustomer;
    errors?: Record<string, string | string[]>;
}
