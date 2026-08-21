# Prinx Electronics — Completion Implementation Plan

## Baseline audit

This roadmap is based on the June 23, 2026 Laravel/Inertia/Vue source baseline.

### Already implemented
- [x] Laravel 13 + Vue 3 + Inertia storefront
- [x] SSR build and SEO head support
- [x] Product catalogue, categories and brands
- [x] Product search and category browsing
- [x] Product detail pages
- [x] Session cart
- [x] Guest checkout
- [x] Customer registration/login
- [x] Admin dashboard
- [x] Admin product CRUD + Trash/restore
- [x] Admin category/brand management + Trash/restore
- [x] Admin order management
- [x] Service catalogue + inquiries
- [x] Homepage carousel management
- [x] Product reels
- [x] Google Merchant feed/sync tools
- [x] Sitemap
- [x] WebP/image SEO pipeline

## Phase 1 — Commerce data hardening
- [x] Add structured product specifications
- [x] Add low-stock threshold per product
- [x] Add inventory movement ledger
- [x] Record checkout stock deductions in inventory ledger
- [x] Record manual admin stock adjustments
- [ ] Product variants (only when required by real catalogue)

## Phase 2 — Orders and customer experience
- [x] Add order status history
- [x] Record admin order/payment status changes
- [x] Add guest order tracking using order number + phone
- [x] Add signed-in My Orders page
- [x] Add signed-in order detail page
- [x] Expose tracking and account links in storefront navigation
- [ ] Saved delivery addresses
- [ ] Wishlist
- [ ] Reviews

## Phase 3 — Admin operations
- [x] Dashboard low-stock metric
- [x] Dashboard out-of-stock metric
- [x] Product list low-stock indicator
- [ ] Dedicated inventory movement screen
- [ ] Bulk stock update/import
- [ ] Configurable delivery zones/rates
- [ ] Store settings UI

## Phase 4 — Checkout and payments
- [x] Cash on delivery
- [x] Mobile money arrangement
- [x] Bank transfer arrangement
- [x] Transaction-safe stock validation/deduction
- [ ] Payment gateway integration when provider is selected
- [ ] Configurable delivery method selection
- [ ] Coupons/promotions

## Phase 5 — Content and trust
- [x] About
- [x] Contact
- [x] Shipping
- [x] Returns
- [x] Privacy
- [x] Terms
- [ ] FAQ
- [ ] Warranty policy page if separate from Returns/Terms

## Phase 6 — SEO and performance hardening
- [x] Product JSON-LD
- [x] Breadcrumb JSON-LD
- [x] Image metadata
- [x] Sitemap
- [x] Merchant feed
- [x] WebP optimization
- [ ] Production Core Web Vitals audit
- [ ] CDN/cache policy review
- [ ] Search-engine console verification after domain deployment

## Phase 7 — Security, testing and deployment
- [ ] Feature tests for checkout stock locking
- [ ] Feature tests for order tracking authorization
- [ ] Feature tests for admin inventory/order updates
- [ ] Rate-limit sensitive public endpoints
- [ ] Production queue worker/supervisor configuration
- [ ] GitHub Actions deployment
- [ ] Automated database + storage backups
- [ ] Final mobile/browser regression test

## Definition of done
Prinx is launch-ready when a customer can discover a product, review specifications, add it to cart, check out, receive an order number, track the order, and when staff can process the same order and inventory entirely from the admin system without direct database changes.

## Implementation log
### 2026-08-21
- Added structured product specifications and low-stock thresholds.
- Added inventory movement records for checkout deductions and manual admin stock changes.
- Added order status/payment history.
- Added guest order tracking.
- Added authenticated My Orders and order detail pages.
- Added low/out-of-stock dashboard visibility.
