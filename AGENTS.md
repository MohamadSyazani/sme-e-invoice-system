# SME E-Invoice System — AI Developer & Architecture Guidelines (`AGENT.md`)

## 1. Project Context & Deadline
* **System Name:** SME E-Invoice System (AI-Powered POS, Inventory, Loyalty & Mock LHDN Platform)
* **Domain:** Malaysian Hardware Retail SMEs (Strictly physical hardware goods: cement, PVC pipes, plumbing supplies, tools, nails, paint).
* **Course:** BITU 3923 Workshop II (BITS) — Universiti Teknikal Malaysia Melaka (UTeM), Group K07.
* **Target Completion Date:** November 16, 2026.

## 2. Technical Stack
* **Backend:** PHP 8.2+, Laravel (MVC Architecture + Service Layer in `app/Services/`)
* **Frontend:** Laravel Blade, Bootstrap 5 (CDN), Chart.js (CDN for Analytics Graphs), Vanilla JS
* **Database:** Aiven Cloud MySQL (7 Core Tables managed via DBeaver / Eloquent)
* **External Integrations:**
  1. **Google Gemini API (`gemini-2.5-flash` / `gemini-1.5-flash`):**
     * **Text POS Mode:** Parses natural language checkout orders AND "AI Project Estimator" problem statements into structured JSON cart payloads.
     * **Vision Photo Mode:** Reads uploaded photos of hardware items (`products.image_url`) to auto-extract Product Name, Category, Unit Type, and Description.
     * **Restock Advisor Mode:** Analyzes low-stock hardware items and generates actionable restocking advice for the Shop Owner.
  2. **Mock LHDN MyInvois Service (`App\Services\MockLhdnService` — Option A):**
     * Simulates Malaysian LHDN e-invoice statutory clearance (`lhdn_uin`, `lhdn_qr_url`, `lhdn_validated_at`) without requiring commercial X.509 digital certificates.
  3. **Telegram Bot API:**
     * **Inbound (Remote Ordering):** Contractors send material orders via Telegram Bot -> Webhook creates a `DRAFT_REMOTE` transaction on the POS screen.
     * **Outbound (PDF Delivery):** Dispatches finalized PDF e-invoices (`barryvdh/laravel-dompdf`) directly to the buyer's `telegram_chat_id`.

## 3. The 5 Modules & Supervisor-Mandated "Wow" Features

### Module 1: User & Access Management
* **Tables:** `users`, `user_login_logs`
* **Features:**
  * Authentication (Login/Logout) with `ACTIVE`/`INACTIVE` status check.
  * Automatic audit logging into `user_login_logs` (`user_id`, `login_at`, `ip_address`, `user_agent`) on every successful login.
  * Role-Based Access Control (RBAC): `OWNER` has full access; `CASHIER` is restricted to Module 2 (Read-Only retail view), Module 3 (Customer & Loyalty CRUD), and Module 4 (POS Checkout).
  * Owner Staff CRUD & Security Login Audit Report.

### Module 2: Hardware Product & Inventory Management (+ AI Vision Photo Scanner)
* **Table:** `products`
* **Features:**
  * Dual-Pricing CRUD: Stores `supplier_cost_price` (Owner-only) and `retail_selling_price` (`retail_selling_price >= supplier_cost_price`).
  * Physical Hardware Units (`PIECE`, `BAG`, `KG`, `METER`, `BOX`, `TIN`, `ROLL`, `SET`) and LHDN Classification Code (`022`).
  * **WOW FEATURE — AI Photo Inventory Scanner:** Owner uploads a photo of a hardware item. `GeminiVisionService` inspects the image and auto-fills `name`, `category`, `unit_type`, `sku` suggestion, and `description` on the form.
  * Low-Stock Alert Badges (`stock_quantity <= low_stock_threshold`).
  * Soft-Archiving (`status = 'ARCHIVED'`) instead of hard deleting products that have sales history.

### Module 3: Customer, LHDN Tax Profile & Contractor Loyalty Management
* **Table:** `customers`
* **Features:**
  * Mandatory Default Seed (ID = 1): `Walk-In Customer` (`customer_type = 'WALK_IN'`, `lhdn_tin = 'EI00000000010'`). Cannot be deleted.
  * Contractor & Regular Buyer directory storing `lhdn_tin`, `brn_ssm`, and `telegram_chat_id`.
  * **WOW FEATURE — Frequent Customer Loyalty Program:**
    * Customers earn **1 Loyalty Point for every RM 10 spent** (`points_earned = FLOOR(subtotal / 10)`).
    * Accumulated `loyalty_points` can be redeemed at POS checkout (**100 Points = RM 10.00 discount**).

### Module 4: Conversational AI POS, Remote Ordering & LHDN E-Invoice Engine
* **Tables:** `transactions`, `transaction_items`
* **Features:**
  * **Smart Chat Bar (2 Modes):**
    1. *Direct Order Mode:* Cashier types *"10 bags cement and 2 PVC pipes to Ah Seng"* -> Gemini maps items & quantities to the draft cart.
    2. **WOW FEATURE — AI Project Estimator Mode:** Cashier types a customer's problem (e.g., *"Customer wants to fix a leaking PVC pipe under sink"*) -> Gemini recommends a complete hardware bundle from active inventory (PVC Pipe, Solvent Glue, PTFE Teflon Tape, Hacksaw) and populates the draft cart.
  * **WOW FEATURE — Telegram Remote Ordering (Contractor Pre-Orders):**
    * Contractors message the shop's Telegram Bot -> Laravel parses the message via Gemini and saves a `transactions` record with `order_source = 'TELEGRAM_BOT'` and `order_status = 'DRAFT_REMOTE'`.
    * Cashier sees a "Pending Telegram Orders" queue on the POS screen, loads the draft cart with one click, verifies stock, and finalizes the invoice.
  * **LHDN Minimum Value & Receipt Type Rule:**
    * Small walk-in retail purchases (e.g., RM 5 hammer) default to `invoice_type = 'STANDARD_RECEIPT'` and `lhdn_status = 'PENDING_CONSOLIDATION'` (for month-end consolidated submission).
    * Purchases >= RM 100, Contractor B2B purchases, or when the Cashier toggles **"Issue Official LHDN E-Invoice"** set `invoice_type = 'LHDN_EINVOICE'` and trigger `MockLhdnService` validation (`lhdn_status = 'VALIDATED'`, generating `lhdn_uin` and `lhdn_qr_url`).
  * **Historical Margin Lock (`DB::transaction`):** Copies `supplier_cost_price` -> `recorded_cost_price` and `retail_selling_price` -> `recorded_selling_price` in `transaction_items`, deducts `products.stock_quantity`, updates `customers.loyalty_points`, generates the PDF receipt, and dispatches via Telegram (`sendDocument`).

### Module 5: Financial Ledger, Analytics Charts & AI Predictive Restock Advisor
* **Table:** `shop_expenses` (plus read aggregations on `transactions`, `transaction_items`, `products`)
* **Features:**
  * Shop Operational Expenses CRUD (`RENT`, `UTILITIES`, `WAGES`, `TRANSPORT_FUEL`, `MAINTENANCE`, `OTHER`).
  * Real-Time Profit & Loss (P&L) Formula (Strictly on `order_status = 'COMPLETED'` and `lhdn_status != 'CANCELLED'`):
    * `Net Sales Revenue = SUM(grand_total - tax_amount)`
    * `Locked Total COGS = SUM(quantity * recorded_cost_price)`
    * `Gross Profit = Net Sales Revenue - Locked Total COGS`
    * `Net Profit = Gross Profit - SUM(shop_expenses.amount)`
  * **WOW FEATURE — Interactive Chart.js Analytics Dashboard:**
    * Monthly Revenue vs. COGS vs. Expenses Bar/Line Chart.
    * Top-Selling Hardware Categories Doughnut Chart.
  * **WOW FEATURE — Gemini Predictive Restock Advisor:**
    * Analyzes all products where `stock_quantity <= low_stock_threshold` alongside recent sales velocity and outputs an AI-generated executive restocking plan for the Shop Owner.