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
    email: string;
    phone: string;
    terms?: string;
}

export interface QuickCreateCustomerResponse {
    success?: boolean;
    customer?: CustomerLookupCustomer;
    errors?: Record<string, string | string[]>;
}
