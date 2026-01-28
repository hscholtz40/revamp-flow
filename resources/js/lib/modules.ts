export const modules = [
    { key: 'customers', label: 'Customers' },
    { key: 'users', label: 'Users' },
    { key: 'groups', label: 'Groups' },
    { key: 'contacts', label: 'Contacts' },
    { key: 'products', label: 'Products & Services' },
    { key: 'suppliers', label: 'Suppliers' },
    { key: 'stock-movements', label: 'Stock Movements' },
    { key: 'purchase-orders', label: 'Purchase Orders' },
    { key: 'jobcards', label: 'Jobcards' },
    { key: 'quotes', label: 'Quotes' },
    { key: 'invoices', label: 'Invoices' },
    { key: 'reports', label: 'Reports' },
] as const

export type ModuleKey = typeof modules[number]['key']

