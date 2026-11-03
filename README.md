# SME E-Invoice System (Group K07)

A lightweight, AI-powered Point-of-Sale (POS), Hardware Inventory, Financial Ledger, and LHDN-compliant E-Invoicing web application engineered specifically for Malaysian hardware retail SMEs.

**Course:** BITU 3923 Workshop II (BITS) — Universiti Teknikal Malaysia Melaka (UTeM)  
**Target Completion:** November 16, 2026  

---

## 🚀 The "Wow" Features (System Highlights)
1. **AI Photo Inventory Scanner:** Upload a photo of a hardware item, and Google Gemini Vision automatically extracts the product name, category, and unit type to populate the inventory form.
2. **AI Smart Chat & Project Estimator:** Cashiers type natural orders (e.g., *"10 bags cement to Ah Seng"*) or project problems (e.g., *"Customer needs to fix a leaking PVC pipe"*), and Gemini instantly builds a recommended shopping cart.
3. **Telegram Remote Ordering:** Contractors can message the shop's Telegram Bot to pre-order materials. The system intercepts the message and queues a draft cart on the POS screen.
4. **Contractor Loyalty Rewards:** Customers earn 1 point for every RM 10 spent, which can be redeemed for RM 10 discounts at checkout to drive B2B retention.
5. **Interactive Chart.js P&L & AI Restock Advisor:** Real-time animated financial graphs showing Net Profit (Revenue - Locked COGS - Expenses), plus a Gemini-powered advisory tool that reads low-stock levels and recommends weekly purchasing strategies.
6. **Mock LHDN E-Invoicing (Option A):** A toggle at checkout allows cashiers to bypass standard retail receipts and generate LHDN-validated PDFs (complete with UINs and QR codes) dispatched instantly via Telegram.

---

## 👥 Team & Module Allocation (UTeM Rubric)

| Module | Module Name & Core Scope | Primary Actors | Assigned Member |
| :--- | :--- | :--- | :--- |
| **Module 1** | **User & Access Management** (Login, RBAC Middleware, Staff CRUD, Login Security Audit Report) | Owner & Cashier (Login); Owner Only (Staff CRUD & Logs) | **Mohamad Syazani Bin Sarman** (`B032410621`) |
| **Module 2** | **Hardware Product & Inventory Management** (Gemini Vision Photo Scanner, Dual-Pricing, Low-Stock Alerts, Soft-Archive) | Owner (Full CRUD); Cashier (Read-Only) | **Nur Farzanah Najwa Binti Sopian** (`B032510633`) |
| **Module 3** | **Customer & Loyalty Profile Management** (Walk-In Default, Contractor TIN/BRN Directory, Loyalty Points Engine) | Cashier & Owner | **Nurul Nabila Huda Binti Mohd Kholaluddin** (`B032510774`) |
| **Module 4** | **Conversational POS & E-Invoice Engine** (Gemini Smart Chat, Telegram Remote Order Queue, Mock LHDN Toggle, PDF Telegram Dispatch) | Cashier & Owner | **Muhammad Adib Bin Mohd Nazli** (`B032520034`) |
| **Module 5** | **Financial Ledger & Analytics Module** (Overhead Expenses CRUD, Locked Historical COGS, Chart.js P&L, AI Restock Advisor) | Owner Only | **Muhammad Adib Bin Mohd Nazli** (`B032520034`) |

---

## 🛠️ Technology Stack
* **Backend:** PHP 8.2+, Laravel 11 (MVC Architecture)
* **Frontend:** Laravel Blade, Bootstrap 5 (CDN), Chart.js (CDN), Vanilla JavaScript
* **Database:** Cloud MySQL (Aiven) — Managed via DBeaver & Laravel Migrations
* **PDF Engine:** DomPDF (`barryvdh/laravel-dompdf`)
* **APIs:** Google Gemini API (`gemini-1.5-flash`), Telegram Bot API (`sendDocument`, Webhooks)

---

## 🚀 Team Onboarding: How to Clone & Run Locally

> **⚠️ IMPORTANT FOR GROUPMATES:** Do **NOT** run `composer create-project`. The base project is already initialized in this repository. Follow the steps below:

### Step 1: Clone the Repository
Open Windows PowerShell and run:
```powershell
git clone <YOUR_GITHUB_REPOSITORY_URL>
cd sme-e-invoice-system