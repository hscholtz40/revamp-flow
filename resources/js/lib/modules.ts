export const modules = [
    { key: 'customers', label: 'Customers' },
    { key: 'contacts', label: 'Contacts' },
    { key: 'products', label: 'Products & Services' },
    { key: 'suppliers', label: 'Suppliers' },
    { key: 'stock-movements', label: 'Stock Movements' },
    { key: 'purchase-orders', label: 'Purchase Orders' },
    { key: 'jobcards', label: 'Jobcards' },
    { key: 'quotes', label: 'Quotes' },
    { key: 'invoices', label: 'Invoices' },
    { key: 'credit-notes', label: 'Credit Notes' },
    { key: 'reports', label: 'Reports' },
    { key: 'timesheet', label: 'Timesheet' },
    { key: 'messages', label: 'Messages' },
    { key: 'dispatch', label: 'Dispatch' },
    { key: 'tasks', label: 'Tasks' },
] as const;

/** Client Zone staff moderation: listed separately on the group permissions screen. */
export const clientZoneAdminModules = [
    { key: 'registered-users', label: 'Registered users (Client Zone)' },
    { key: 'customer-update-requests', label: 'Information update requests' },
] as const;

export const allModulesForGroupPermissions = [...modules, ...clientZoneAdminModules] as const;

export type ModuleKey = (typeof modules)[number]['key'];
export type ClientZoneAdminModuleKey = (typeof clientZoneAdminModules)[number]['key'];
