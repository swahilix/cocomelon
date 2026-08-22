# Stock Management Implementation Roadmap

## Objective

Build a complete stock, purchasing, sales, expense and POS management module inside the Prinx administration panel while keeping the existing storefront, customer orders, product catalogue and Google Merchant integration stable.

The stock module must use the existing `products.stock_quantity` field as the live stock balance, while every inventory change is backed by a traceable stock movement.

## Core principles

- One product stock balance shared by storefront, POS and purchases.
- Every stock increase or decrease must create an auditable movement.
- Purchases increase stock only when received/posted.
- POS sales reduce stock immediately when completed.
- Online orders must eventually use the same stock movement ledger.
- Transactions that affect stock or money should use database transactions.
- Historical sales and purchase lines must retain their original price/cost even if the product price changes later.
- Never silently delete financial records; use status, void/reversal or soft-delete patterns where appropriate.
- Mobile-first admin UI with compact cards, tables and Iconsax icons.

---

# Phase 0 — Foundation already created

Status: **Initial implementation exists and must be reviewed before expanding.**

Current foundation:

- Stock Management entry in admin sidebar.
- Stock overview page.
- Purchase recording.
- POS/counter sale recording.
- Expense recording.
- Stock movement ledger.
- Purchase stock-in updates `products.stock_quantity`.
- POS stock-out updates `products.stock_quantity`.
- POS prevents selling above available tracked stock.

Before Phase 1, inspect the existing migrations/controllers/UI and normalize any naming or transaction logic needed for the roadmap below.

---

# Phase 1 — Inventory foundation hardening

## 1.1 Stock movement service

Create one reusable inventory service/action responsible for all stock mutations.

Required operations:

- stock in
- stock out
- adjustment increase
- adjustment decrease
- reversal
- opening balance

Each movement should store:

- product ID
- movement type
- quantity delta
- quantity before
- quantity after
- source type
- source ID
- reference number
- cost where relevant
- notes
- performed by user ID
- timestamp

Rules:

- Lock the product row before changing tracked stock.
- Prevent negative stock unless a future setting explicitly allows it.
- All source transaction + stock movement changes must commit atomically.
- Product stock should never be manually changed in unrelated controllers once this service is active.

## 1.2 Opening stock / stock adjustment

Add admin ability to:

- set opening stock for products without historical movements
- add stock adjustment
- subtract damaged stock
- subtract lost/missing stock
- correct counting errors
- enter adjustment reason and note

Adjustment reasons:

- Opening balance
- Physical count correction
- Damaged
- Lost
- Expired
- Internal use
- Return to supplier
- Other

## 1.3 Inventory list

Create dedicated inventory view showing:

- product image
- SKU
- product name
- category
- current stock
- low-stock threshold
- stock status
- average cost
- current selling price
- stock value
- last movement

Filters:

- search
- category
- brand
- in stock
- out of stock
- low stock
- stock tracking enabled/disabled

Actions:

- view ledger
- adjust stock
- purchase product
- open POS with product

## 1.4 Low stock dashboard

Add:

- products at/below low stock level
- out-of-stock products
- low-stock count metric
- shortcut to create purchase

### Phase 1 acceptance criteria

- No stock-changing flow updates `stock_quantity` without the inventory service.
- Concurrent purchase/POS requests cannot corrupt stock balances.
- Every stock balance can be explained by movement history.
- Admin can safely correct stock without editing the product record directly.

---

# Phase 2 — Supplier management

## 2.1 Suppliers

Create supplier management with:

- supplier/business name
- contact person
- phone
- email
- address
- tax/TIN/reference number
- opening balance
- notes
- active/inactive status

Supplier detail page should show:

- total purchases
- paid amount
- outstanding amount
- latest purchases
- payment history
- products commonly supplied

## 2.2 Purchase integration

Replace free-text supplier entry with supplier selection while retaining an optional walk-in/one-time supplier option if useful.

### Phase 2 acceptance criteria

- Purchases can be traced to suppliers.
- Supplier purchase and payment history is visible.
- Supplier balances can be calculated reliably.

---

# Phase 3 — Complete purchasing workflow

## 3.1 Purchase lifecycle

Support statuses:

- Draft
- Ordered
- Partially received
- Received
- Cancelled

Purchase fields:

- purchase/reference number
- supplier
- supplier invoice number
- order date
- expected date
- received date
- payment status
- payment method
- subtotal
- discount
- tax
- shipping/other cost
- grand total
- amount paid
- balance due
- notes
- created by

## 3.2 Purchase lines

Each purchase line stores:

- product
- quantity ordered
- quantity received
- unit cost
- line discount
- tax if used
- line total

## 3.3 Partial receiving

Allow receiving fewer units than ordered.

Only received units should increase stock.

Each receipt should be auditable and impossible to receive beyond the remaining quantity.

## 3.4 Purchase payments

Create purchase payment records:

- purchase
- supplier
- amount
- payment date
- payment method
- transaction/reference number
- note
- created by

Support:

- unpaid
- partially paid
- paid

## 3.5 Purchase returns

Allow returning goods to supplier.

Return flow:

- select purchase/product
- quantity returned
- reason
- refund/credit amount
- notes

Returning received stock should create a stock-out movement.

### Phase 3 acceptance criteria

- Purchase ordering, receiving, payment and returns are distinct and auditable.
- Partial receipts work correctly.
- Supplier balances match purchase payments.

---

# Phase 4 — POS redesign

## 4.1 Dedicated POS screen

Create a fast full-screen/mobile-responsive POS interface.

Layout:

- product search/grid on left/top
- cart on right/bottom
- product image, name, SKU, stock and selling price
- category filters
- barcode/SKU search
- cart quantity controls
- remove item
- cart totals

## 4.2 POS pricing

Support:

- normal product price
- active sale price
- manual line discount with permission
- overall discount
- optional custom selling price with permission

## 4.3 Customer handling

Support:

- walk-in customer
- existing customer selection
- quick customer creation

Customer fields can later be shared with online customers where appropriate.

## 4.4 Payments

Support payment methods:

- Cash
- Mobile money
- Bank transfer
- Card
- Other

Allow:

- exact payment
- overpayment and change
- split payments in a later sub-phase
- payment reference for non-cash payments

## 4.5 Receipts

Create printable POS receipt containing:

- store details
- receipt number
- cashier
- date/time
- customer
- purchased items
- quantity
- unit price
- discount
- total
- payment method
- amount paid
- change

Provide:

- Print receipt
- Reprint receipt
- browser-friendly thermal receipt layout

## 4.6 Hold / resume sale

Add ability to place an unfinished POS cart on hold and restore it later.

### Phase 4 acceptance criteria

- POS is fast enough for counter use.
- Completed sales immediately reduce stock.
- Receipts can be printed/reprinted.
- POS cannot oversell tracked stock.

---

# Phase 5 — Sales management

## 5.1 Unified sales view

Create a sales management area covering:

- POS sales
- online orders

Do not merge their tables blindly; present a unified reporting layer while preserving each transaction source.

Columns:

- sale/order number
- source (POS / Online)
- customer
- date
- amount
- payment status
- fulfillment/status
- cashier/admin where applicable

## 5.2 POS sale detail

Show:

- receipt details
- customer
- items
- payment
- stock movements
- gross profit
- created by

## 5.3 Sale returns/refunds

Implement POS returns:

- full return
- partial return
- return individual lines
- quantity validation
- reason
- refund method

Returned sale stock should create stock-in movements if the product is restockable.

Support non-restockable/damaged returns without increasing sellable stock.

## 5.4 Void transaction

Never hard-delete completed sales.

Void should:

- require reason
- require adequate permission
- create reversal movements
- preserve original sale
- mark transaction voided

### Phase 5 acceptance criteria

- Sales history cannot be silently altered.
- Returns and voids correctly reverse inventory impact.
- Online and POS sales can be reviewed together without losing source-specific details.

---

# Phase 6 — Online order stock ledger integration

The storefront currently manages stock independently from the new movement ledger. Migrate it carefully.

## 6.1 Order stock reservation strategy

Choose and implement one explicit strategy:

Recommended:

- reserve/deduct stock at successful checkout transaction
- restore stock when an order is cancelled before fulfillment
- do not deduct again when marked delivered

## 6.2 Movement records

Online checkout should create stock movements with source:

- `online_order`

Cancellation should create reversal/restock movements.

## 6.3 Existing order compatibility

Do not retroactively modify historical product stock automatically.

Optionally add a controlled reconciliation command for administrators if historical ledger import is needed.

### Phase 6 acceptance criteria

- POS and storefront share the same inventory mutation mechanism.
- Cancelling an online order restores stock exactly once.
- Stock is never deducted twice during order status changes.

---

# Phase 7 — Costing and profit

## 7.1 Product cost data

Track:

- last purchase cost
- weighted average cost

Recommended initial costing method: **weighted average cost**.

Formula after receipt:

`new_average = ((old_qty × old_average_cost) + (received_qty × received_unit_cost)) / new_qty`

## 7.2 Cost of goods sold (COGS)

When a sale is completed, preserve unit cost on the sale line.

This makes historical profit stable even when costs change later.

## 7.3 Profit metrics

Calculate:

- revenue
- COGS
- gross profit
- operating expenses
- net operating result
- gross margin percentage

Reports by:

- day
- week
- month
- custom range
- product
- category
- cashier

### Phase 7 acceptance criteria

- Historical sale profit does not change when purchase costs change.
- Dashboard profit reflects COGS and expenses rather than revenue alone.

---

# Phase 8 — Expense management expansion

## 8.1 Expense categories

Manage categories such as:

- Rent
- Salaries
- Electricity
- Internet
- Transport
- Marketing
- Repairs
- Office supplies
- Taxes/fees
- Miscellaneous

## 8.2 Expense records

Add:

- category
- title
- amount
- date
- payee
- payment method
- reference
- receipt/attachment
- recurring flag
- note
- created by

## 8.3 Expense reports

Show:

- expenses by date range
- category breakdown
- monthly comparison
- largest expenses

### Phase 8 acceptance criteria

- Expenses can be categorized and audited.
- Expenses feed net-profit/reporting calculations.

---

# Phase 9 — Cash register / shift management

## 9.1 POS register sessions

A cashier opens a register with:

- opening cash
- opening time

At closing:

- expected cash
- counted cash
- difference
- notes
- closing time

## 9.2 Cash movements

Record:

- cash sale
- cash refund
- cash in
- cash out
- expense paid from register

## 9.3 Shift report

Show:

- cashier
- opening balance
- cash sales
- non-cash sales
- cash in/out
- refunds
- expected closing cash
- actual cash
- variance

### Phase 9 acceptance criteria

- Cash at the counter can be reconciled per shift.
- Variances are visible and attributable.

---

# Phase 10 — Reporting and dashboard

Create Stock Management reports for:

## Inventory

- current stock
- inventory valuation
- low stock
- out of stock
- stock movement report
- stock adjustment report
- dead/slow stock
- fast-moving products

## Purchases

- purchases by date
- purchases by supplier
- outstanding supplier balances
- purchase returns

## Sales

- daily sales
- monthly sales
- sales by product
- sales by category
- sales by payment method
- sales by cashier
- returned/voided sales

## Profit

- gross profit
- COGS
- expense totals
- estimated net operating result
- highest-margin products

## Dashboard enhancements

Admin dashboard should eventually show:

- today's POS revenue
- today's online revenue
- total sales today
- gross profit today
- expenses today
- purchases today
- low-stock products
- stock value
- outstanding supplier balance

### Phase 10 acceptance criteria

- Reports reconcile back to source transactions.
- Date filters use consistent timezone boundaries.
- Dashboard metrics link to drill-down reports.

---

# Phase 11 — Users, roles and permissions

Introduce granular permissions rather than relying only on administrator access.

Suggested permissions:

- view inventory
- adjust stock
- manage purchases
- receive purchases
- manage suppliers
- use POS
- discount POS sale
- override price
- void sale
- refund sale
- manage expenses
- view costs
- view profit reports
- manage registers

Suggested roles:

- Administrator
- Manager
- Cashier
- Stock keeper
- Accountant

### Phase 11 acceptance criteria

- Cashiers cannot access sensitive purchase/profit data unless permitted.
- Risky actions have explicit permission checks.

---

# Phase 12 — Audit trail and safeguards

Add audit records for sensitive actions:

- stock adjustment
- purchase receive/cancel
- purchase payment
- POS discount override
- sale void
- refund
- expense edit/delete
- register close

Store:

- user
- action
- subject type/ID
- before values where useful
- after values where useful
- IP/user agent if desired
- timestamp

Add validation/safety rules:

- prevent duplicate transaction submissions
- use idempotency/reference guards on financial posting actions
- lock rows during stock mutation
- no negative quantities
- no negative payment amounts
- no receiving more than ordered
- no refunding more than sold

---

# Phase 13 — UI/UX completion

Keep the module consistent with the current Prinx admin style.

Requirements:

- Iconsax icons only
- compact mobile-first layouts
- no excessive rounded cards
- clear status badges
- sticky POS cart on desktop where appropriate
- mobile POS bottom checkout area
- searchable product selectors
- useful empty states
- loading/disabled states during posting
- 30-second copyable flash alerts using existing shared alert behavior
- print-friendly purchase and POS documents

---

# Phase 14 — Tests and reconciliation tools

## Automated tests

Feature tests:

- purchase receipt increases stock
- partial receipt increases only received quantity
- cancelled draft purchase does not alter stock
- supplier return decreases stock
- POS sale decreases stock
- insufficient stock blocks sale
- POS return restores valid stock
- sale void reverses stock once
- online checkout creates movement
- online cancellation restores movement once
- adjustment creates correct before/after balance
- weighted average cost calculation
- COGS preservation
- purchase payment balance
- expense validation
- permission restrictions

## Reconciliation command/tool

Add an admin diagnostic that compares:

- current `products.stock_quantity`
- calculated balance from stock movements since opening balance

Flag mismatches without silently modifying data.

Provide an explicit administrator reconciliation action only after review.

---

# Recommended implementation order

We will implement in this exact order unless a discovered dependency requires a small adjustment:

1. **Phase 1 — Inventory foundation hardening**
2. **Phase 2 — Suppliers**
3. **Phase 3 — Purchasing workflow**
4. **Phase 4 — POS redesign**
5. **Phase 5 — Sales, returns and voids**
6. **Phase 6 — Online order inventory integration**
7. **Phase 7 — Costing and profit**
8. **Phase 8 — Expenses expansion**
9. **Phase 9 — Cash register shifts**
10. **Phase 10 — Reports/dashboard**
11. **Phase 11 — Roles/permissions**
12. **Phase 12 — Audit/safeguards**
13. **Phase 13 — UI polish**
14. **Phase 14 — Tests/reconciliation**

---

# Definition of complete

The Stock Management module is complete when Prinx can reliably answer all of these questions from the system without manual calculation:

- How many units of each product are available now?
- Why is that the current quantity?
- Which supplier supplied the stock and at what cost?
- What is owed to each supplier?
- What was sold through POS today?
- What was sold online today?
- Which cashier made each counter sale?
- What was returned, voided, damaged or adjusted?
- What is the stock valuation?
- What is the gross profit for a selected period?
- What expenses were incurred?
- What is the resulting operating profit estimate?
- Which products are low/out of stock?
- Can every stock and money-changing action be traced to a source transaction and user?

---

# Progress tracking

Use this file as the implementation checklist. As each phase is completed, update its status here and record important architectural decisions so later work does not accidentally bypass the inventory ledger or financial controls.
