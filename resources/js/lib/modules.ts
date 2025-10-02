export const modules = [
    { key: 'customers', label: 'Customers' },
    { key: 'users', label: 'Users' },
    { key: 'groups', label: 'Groups' },
    { key: 'contacts', label: 'Contacts' },
    { key: 'products', label: 'Products & Services' },
    { key: 'jobcards', label: 'Jobcards' },
    { key: 'quotes', label: 'Quotes' },
    { key: 'invoices', label: 'Invoices' },
] as const

export type ModuleKey = typeof modules[number]['key']

