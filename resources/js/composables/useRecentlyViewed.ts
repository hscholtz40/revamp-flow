import { ref, computed } from 'vue';
import type { LucideIcon } from 'lucide-vue-next';
import {
    LayoutGrid,
    Users,
    UserCheck,
    Package,
    Warehouse,
    ArrowUpDown,
    ShoppingCart,
    ClipboardList,
    FileText,
    Receipt,
    Clock,
    Settings,
} from 'lucide-vue-next';

export interface RecentlyViewedItem {
    url: string;
    title: string;
    icon?: LucideIcon;
    timestamp: number;
}

const STORAGE_KEY = 'recently_viewed_items';
const MAX_ITEMS = 10;

const recentlyViewed = ref<RecentlyViewedItem[]>([]);

// Route patterns to friendly names and icons
const routePatterns: Array<{
    pattern: RegExp;
    title: (url: string) => string;
    icon: LucideIcon;
}> = [
    {
        pattern: /^\/dashboard/,
        title: () => 'Dashboard',
        icon: LayoutGrid,
    },
    {
        pattern: /^\/customers\/\d+$/,
        title: (url) => {
            const match = url.match(/\/customers\/(\d+)/);
            return match ? `Customer #${match[1]}` : 'Customer';
        },
        icon: Users,
    },
    {
        pattern: /^\/customers/,
        title: () => 'Customers',
        icon: Users,
    },
    {
        pattern: /^\/contacts\/\d+$/,
        title: (url) => {
            const match = url.match(/\/contacts\/(\d+)/);
            return match ? `Contact #${match[1]}` : 'Contact';
        },
        icon: UserCheck,
    },
    {
        pattern: /^\/contacts/,
        title: () => 'Contacts',
        icon: UserCheck,
    },
    {
        pattern: /^\/products\/\d+$/,
        title: (url) => {
            const match = url.match(/\/products\/(\d+)/);
            return match ? `Product #${match[1]}` : 'Product';
        },
        icon: Package,
    },
    {
        pattern: /^\/products/,
        title: () => 'Products & Services',
        icon: Package,
    },
    {
        pattern: /^\/suppliers\/\d+$/,
        title: (url) => {
            const match = url.match(/\/suppliers\/(\d+)/);
            return match ? `Supplier #${match[1]}` : 'Supplier';
        },
        icon: Warehouse,
    },
    {
        pattern: /^\/suppliers/,
        title: () => 'Suppliers',
        icon: Warehouse,
    },
    {
        pattern: /^\/stock-movements\/\d+$/,
        title: (url) => {
            const match = url.match(/\/stock-movements\/(\d+)/);
            return match ? `Stock Movement #${match[1]}` : 'Stock Movement';
        },
        icon: ArrowUpDown,
    },
    {
        pattern: /^\/stock-movements/,
        title: () => 'Stock Movements',
        icon: ArrowUpDown,
    },
    {
        pattern: /^\/purchase-orders\/\d+$/,
        title: (url) => {
            const match = url.match(/\/purchase-orders\/(\d+)/);
            return match ? `Purchase Order #${match[1]}` : 'Purchase Order';
        },
        icon: ShoppingCart,
    },
    {
        pattern: /^\/purchase-orders/,
        title: () => 'Purchase Orders',
        icon: ShoppingCart,
    },
    {
        pattern: /^\/jobcards\/\d+$/,
        title: (url) => {
            const match = url.match(/\/jobcards\/(\d+)/);
            return match ? `Jobcard #${match[1]}` : 'Jobcard';
        },
        icon: ClipboardList,
    },
    {
        pattern: /^\/jobcards/,
        title: () => 'Jobcards',
        icon: ClipboardList,
    },
    {
        pattern: /^\/quotes\/\d+$/,
        title: (url) => {
            const match = url.match(/\/quotes\/(\d+)/);
            return match ? `Quote #${match[1]}` : 'Quote';
        },
        icon: FileText,
    },
    {
        pattern: /^\/quotes/,
        title: () => 'Quotes',
        icon: FileText,
    },
    {
        pattern: /^\/invoices\/\d+$/,
        title: (url) => {
            const match = url.match(/\/invoices\/(\d+)/);
            return match ? `Invoice #${match[1]}` : 'Invoice';
        },
        icon: Receipt,
    },
    {
        pattern: /^\/invoices/,
        title: () => 'Invoices',
        icon: Receipt,
    },
    {
        pattern: /^\/time-entries/,
        title: () => 'Timesheet',
        icon: Clock,
    },
    {
        pattern: /^\/administration/,
        title: () => 'Administration',
        icon: Settings,
    },
];

function getRouteInfo(url: string): { title: string; icon?: LucideIcon } {
    // Remove query parameters and hash
    const cleanUrl = url.split('?')[0].split('#')[0];
    
    for (const route of routePatterns) {
        if (route.pattern.test(cleanUrl)) {
            return {
                title: route.title(cleanUrl),
                icon: route.icon,
            };
        }
    }
    
    // Fallback: use URL path
    const pathParts = cleanUrl.split('/').filter(Boolean);
    const lastPart = pathParts[pathParts.length - 1] || 'Home';
    return {
        title: lastPart.charAt(0).toUpperCase() + lastPart.slice(1).replace(/-/g, ' '),
    };
}

function loadFromStorage(): RecentlyViewedItem[] {
    if (typeof window === 'undefined') {
        return [];
    }
    
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (error) {
        console.error('Failed to load recently viewed items:', error);
    }
    
    return [];
}

function saveToStorage(items: RecentlyViewedItem[]) {
    if (typeof window === 'undefined') {
        return;
    }
    
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch (error) {
        console.error('Failed to save recently viewed items:', error);
    }
}

function shouldTrackUrl(url: string): boolean {
    // Don't track certain routes
    const excludedPatterns = [
        /^\/login/,
        /^\/logout/,
        /^\/password/,
        /^\/two-factor/,
        /^\/verification/,
        /^\/install/,
    ];
    
    return !excludedPatterns.some(pattern => pattern.test(url));
}

export function useRecentlyViewed() {
    // Initialize from storage
    if (recentlyViewed.value.length === 0) {
        recentlyViewed.value = loadFromStorage();
    }
    
    function addItem(url: string, customTitle?: string) {
        if (!shouldTrackUrl(url)) {
            return;
        }
        
        // Remove query parameters for tracking (but keep them in the stored URL)
        const cleanUrl = url.split('?')[0].split('#')[0];
        
        // Remove existing item with same URL
        recentlyViewed.value = recentlyViewed.value.filter(
            item => item.url.split('?')[0].split('#')[0] !== cleanUrl
        );
        
        // Get route info
        const routeInfo = getRouteInfo(cleanUrl);
        
        // Add new item at the beginning
        const newItem: RecentlyViewedItem = {
            url: url, // Keep full URL with query params
            title: customTitle || routeInfo.title,
            icon: routeInfo.icon,
            timestamp: Date.now(),
        };
        
        recentlyViewed.value.unshift(newItem);
        
        // Keep only the most recent items
        if (recentlyViewed.value.length > MAX_ITEMS) {
            recentlyViewed.value = recentlyViewed.value.slice(0, MAX_ITEMS);
        }
        
        // Save to storage
        saveToStorage(recentlyViewed.value);
    }
    
    function removeItem(url: string) {
        recentlyViewed.value = recentlyViewed.value.filter(
            item => item.url !== url
        );
        saveToStorage(recentlyViewed.value);
    }
    
    function clearAll() {
        recentlyViewed.value = [];
        saveToStorage([]);
    }
    
    const items = computed(() => recentlyViewed.value);
    
    return {
        items,
        addItem,
        removeItem,
        clearAll,
    };
}

