import type { CustomerLookupCustomer, QuickCreateCustomerPayload, QuickCreateCustomerResponse } from '@/types/customers';
import { getCsrfToken } from '@/lib/csrf';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface UseCustomerLookupOptions {
    customers: CustomerLookupCustomer[];
    initialCustomerId?: number | string | null;
    onCustomerSelected: (customer: CustomerLookupCustomer) => void;
    onCustomerCleared?: () => void;
    searchUrl?: string;
    quickCreateUrl?: string;
    searchErrorMessage?: string;
    createErrorMessage?: string;
}

export function useCustomerLookup({
    customers,
    initialCustomerId = null,
    onCustomerSelected,
    onCustomerCleared,
    searchUrl = '/customers/search',
    quickCreateUrl = '/customers/quick-create',
    searchErrorMessage = 'Unable to load matching customers right now.',
    createErrorMessage = 'Unable to create a customer right now.',
}: UseCustomerLookupOptions) {
    const customerSearchQuery = ref('');
    const customerSearchFocused = ref(false);
    const filteredCustomers = ref<CustomerLookupCustomer[]>([]);
    const selectedCustomer = ref<CustomerLookupCustomer | null>(
        customers.find((customer) => customer.id === Number(initialCustomerId)) ?? null,
    );
    const showQuickCreateModal = ref(false);
    const quickCreateForm = useForm<QuickCreateCustomerPayload>({
        name: '',
        email: '',
        phone: '',
        terms: '',
    });

    if (selectedCustomer.value) {
        customerSearchQuery.value = selectedCustomer.value.name;
    }

    watch(customerSearchQuery, (newQuery) => {
        if (!showQuickCreateModal.value) {
            quickCreateForm.name = newQuery;
        }
    });

    const selectCustomer = (customer: CustomerLookupCustomer) => {
        selectedCustomer.value = customer;
        customerSearchQuery.value = customer.name;
        customerSearchFocused.value = false;
        onCustomerSelected(customer);
    };

    const clearCustomer = () => {
        selectedCustomer.value = null;
        customerSearchQuery.value = '';
        customerSearchFocused.value = false;
        filteredCustomers.value = [];
        onCustomerCleared?.();
    };

    const setSelectedCustomer = (customer: CustomerLookupCustomer | null) => {
        if (!customer) {
            clearCustomer();

            return;
        }

        selectedCustomer.value = customer;
        customerSearchQuery.value = customer.name;
    };

    const handleCustomerBlur = () => {
        setTimeout(() => {
            customerSearchFocused.value = false;
        }, 200);
    };

    const handleCustomerSearch = async () => {
        if (!customerSearchQuery.value.trim()) {
            filteredCustomers.value = [];

            return;
        }

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(customerSearchQuery.value)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                filteredCustomers.value = [];
                toast.error(searchErrorMessage);

                return;
            }

            filteredCustomers.value = (await response.json()) as CustomerLookupCustomer[];
        } catch {
            filteredCustomers.value = [];
            toast.error(searchErrorMessage);
        }
    };

    const quickCreateCustomer = async () => {
        quickCreateForm.clearErrors();

        try {
            const response = await fetch(quickCreateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(quickCreateForm.data()),
            });

            const payload = (await response.json()) as QuickCreateCustomerResponse;

            if (!response.ok) {
                if (payload.errors) {
                    quickCreateForm.setError(payload.errors);
                } else {
                    toast.error(createErrorMessage);
                }

                return;
            }

            if (payload.success && payload.customer) {
                selectCustomer(payload.customer);
                showQuickCreateModal.value = false;
                quickCreateForm.reset();
                quickCreateForm.clearErrors();
                quickCreateForm.name = customerSearchQuery.value;
            }
        } catch {
            toast.error(createErrorMessage);
        }
    };

    return {
        clearCustomer,
        customerSearchFocused,
        customerSearchQuery,
        filteredCustomers,
        handleCustomerBlur,
        handleCustomerSearch,
        quickCreateCustomer,
        quickCreateForm,
        selectCustomer,
        selectedCustomer,
        setSelectedCustomer,
        showQuickCreateModal,
    };
}
