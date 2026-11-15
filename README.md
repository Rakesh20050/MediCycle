# 🏥 MediCycle: Medicine Donation & Expiry Tracking Management System

**A Database Management Systems (DBMS) Full-Stack Project**

> MediCycle is a web application designed to bridge the gap between medicine donors and verified healthcare organizations. It ensures traceability, reduces wastage, and manages stock distribution using strict relational database principles and First-Expiry-First-Out (FEFO) logic.

## 📌 Project Overview
Unused or near-expiry medicine stock often goes to waste while charitable organizations face shortages. MediCycle solves this by providing a centralized platform where:
*   **Donors** can submit medicine donations.
*   **Admins** verify donations, manage inventory, and distribute stock.
*   **Organizations** can request medicines (only after admin verification).

## 🛠️ Tech Stack
*   **Database:** MySQL / MariaDB (Managed via XAMPP/phpMyAdmin)
*   **Backend:** PHP 8 (using PDO for secure database connections and transactions)
*   **Frontend:** HTML5, CSS3 (Bootstrap 5), Vanilla JavaScript
*   **Libraries:** DataTables.js (Interactive tables), SweetAlert2 (Popups)

## ⚙️ Core DBMS Concepts Implemented
*   **BCNF Normalization:** 10 tables strictly normalized to eliminate data redundancy.
*   **M:N Relationship Resolution:** Associative entities (`REQUEST_ITEM`, `DISTRIBUTION_ITEM`).
*   **ACID Transactions:** Distribution uses `START TRANSACTION`, `ROLLBACK`, and `COMMIT` for all-or-nothing stock deduction.
*   **Row-Level Locking:** Uses `SELECT ... FOR UPDATE` during distribution to prevent negative stock.
*   **Strict Constraints:** `FOREIGN KEY`, `UNIQUE`, and `CHECK` constraints (e.g., `expiry_date > manufacturing_date`, `quantity_available >= 0`).
*   **FEFO Allocation:** First-Expiry-First-Out logic when selecting batches for distribution.

## 📁 Project Directory Structure
```text
medicycle/
│
├── admin/                     # ADMIN ROLE MODULE
│   ├── dashboard.php          # Global stats & overview
│   ├── verify_org.php         # Approve/Reject new organizations
│   ├── review_donations.php   # View pending donor submissions
│   ├── create_batch.php       # Approve donation & set available stock
│   ├── review_requests.php    # View org medicine requests
│   ├── distribute.php         # FEFO transaction execution logic
│   ├── batches.php            # Full inventory view
│   ├── near_expiry.php        # 30-day expiry warning report
│   ├── distributions.php      # History of all distributed stock
│   ├── medicines.php          # Master list of medicines
│   ├── donations.php          # Full donation history
│   ├── reports.php            # Complex JOIN data tables
│   └── profile.php            # Admin details
│
├── organization/              # ORGANIZATION ROLE MODULE
│   ├── dashboard.php          # Org stats & verification status alert
│   ├── request.php            # Dynamic form with stock validation
│   ├── my_requests.php        # History of org's specific requests
│   ├── my_distributions.php   # Record of medicines physically received
│   ├── history.php            # Combined history view
│   ├── profile.php            # Org details
│   ├── register.php           # Org signup (Defaults to 'Pending')
│   └── change_password.php    # Org password update
│
├── donor/                     # DONOR ROLE MODULE
│   ├── dashboard.php          # Donor's personal donation history
│   ├── donate.php             # Form to submit new medicines
│   └── register.php           # Donor signup
│
├── includes/                  # SHARED UI & SECURITY
│   ├── header.php             # Navbar, Sidebar, CSS links
│   ├── footer.php             # Closing tags, JS scripts
│   └── auth.php               # Session validation (blocks unauthorized access)
│
├── config/                    # DATABASE CONFIGURATION
│   └── db_connect.php         # PDO Connection string
│
├── assets/                    
│   └── css/
│       └── style.css          # Custom Teal theme styling
│
├── index.php                  # Main Landing Page / Universal Login
├── register.php               # Registration router/toggle
├── change_password.php        # Global fallback for password change
├── logout.php                 # Destroys session and redirects
│
├── schema.sql                 # DDL: 10 Tables, FKs, CHECK constraints
├── seed_data.sql              # DML: Sample data for testing
├── reports_and_logic.sql      # Raw SQL for reports & FEFO logic
└── README.md                  # Project documentation