// Pages grouped behind the admin "Tools" and "Settings" hubs.
// The hub pages render these as cards, and the sidebar uses them to
// highlight Tools / Settings while one of these pages is open.

export const adminTools = [
    { label: 'GTIN scanner', href: '/admin/gtin', icon: 'qr-code', permissions: ['manage_storefront'], description: 'Scan barcodes to look up and attach GTINs to products.' },
    { label: 'Reviews', href: '/admin/reviews', icon: 'star-1', permissions: ['manage_storefront'], description: 'Moderate and respond to customer product reviews.' },
    { label: 'Software', href: '/admin/software', icon: 'code-1', permissions: ['manage_storefront'], description: 'Manage the software solutions listed on the store.' },
    { label: 'Services', href: '/admin/services', icon: 'setting-3', permissions: ['manage_services'], description: 'Publish repair and installation services.' },
    { label: 'Homepage carousel', href: '/admin/carousel', icon: 'gallery-add', permissions: ['manage_storefront'], description: 'Edit the slides and banners on the storefront home page.' },
    { label: 'Product reels', href: '/admin/reels', icon: 'video-play', permissions: ['manage_storefront'], description: 'Upload short product videos for the storefront.' },
    { label: 'Buying guides', href: '/admin/guides', icon: 'document-text-2', permissions: ['manage_storefront'], description: 'Write guides that help customers choose products.' },
];

export const adminSettings = [
    { label: 'Business settings', href: '/admin/business-settings', icon: 'shop', permissions: ['manage_storefront'], description: 'Business details, receipts, SMS provider, payments and fulfilment.' },
    { label: 'AI & product lookup', href: '/admin/business-settings/ai', icon: 'magic-star', permissions: ['manage_storefront'], description: 'OpenAI key for photo recognition, barcode search and market prices; barcode database.' },
    { label: 'Map settings', href: '/admin/business-settings/maps', icon: 'location', permissions: ['manage_storefront'], description: 'Google Maps key and store location.' },
    { label: 'Mail settings', href: '/admin/mail-settings', icon: 'sms', permissions: ['mail.manage_mailboxes', 'mail.manage_settings'], description: 'Business mailboxes, IMAP/SMTP connections and staff access.' },
    { label: 'Reset data', href: '/admin/system/reset', icon: 'trash', permissions: ['manage_staff'], description: 'Administrators only: back up, then clear sales, orders, stock, purchases, expenses, installments and payments.' },
];

export const canAccess = (granted, permissions = []) => !permissions.length || permissions.some((permission) => granted.includes(permission));

// True when the current URL is one of the hub's pages (or a sub-page of one).
// Map settings lives under /admin/business-settings, so exact matches win there.
export const urlInHub = (url, items) => {
    const path = String(url || '').split('?')[0];
    return items.some((item) => path === item.href || path.startsWith(`${item.href}/`));
};
