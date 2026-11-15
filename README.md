# 🏥 MediCycle: Medicine Donation & Expiry Tracking System

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Status](https://img.shields.io/badge/Status-Completed-success?style=for-the-badge)

> **MediCycle** is a secure, database-driven platform designed to bridge the gap between medicine donors and verified healthcare organizations. By enforcing strict relational database principles, we ensure traceability, prevent wastage, and manage stock distribution using First-Expiry-First-Out (FEFO) logic.

---

## 🚨 Security & Anti-Fraud Restrictions (Core Focus)
A critical flaw in medical donation platforms is the risk of bad actors registering fake organizations to hoard and illegally sell free medicines. **MediCycle prevents this through strict database-level and application-level checks:**

1. **Mandatory Admin Verification:** When an Organization registers, their database `verification_status` defaults to `'Pending'`. They are completely blocked from requesting medicines until a real Admin manually reviews and approves them.
2. **Access-Restricted Interfaces:** Pending organizations do not get "404 Page Not Found" errors. Instead, the system intercepts them and displays a strict **"Access Restricted"** UI card, preventing them from accessing the `my_requests.php` or `request.php` pages.
3. **No Direct Distribution:** Organizations cannot distribute medicines to themselves. They can only *request* stock. The **Admin** is the sole orchestrator who uses FEFO logic to distribute the stock.
4. **Strict Route Guarding:** The `includes/auth.php` file acts as a bouncer. If an Organization tries to manually type the URL for an Admin dashboard, the session check immediately redirects them away.
5. **Data Isolation:** Organization A cannot see what Organization B is requesting. SQL `WHERE` clauses strictly bind queries to the logged-in `organization_id`.

---

## ⚙️ Advanced DBMS Concepts Implemented
This is not just a CRUD app; it is a showcase of deep relational database architecture:

* 🔒 **ACID Transactions:** The FEFO distribution process uses `START TRANSACTION`, `ROLLBACK`, and `COMMIT`. If a batch is empty, the entire distribution fails gracefully without leaving orphaned data.
* 🛑 **Concurrency Control:** Uses `SELECT ... FOR UPDATE` to lock database rows during distribution. This prevents two admins from distributing the same last batch of medicine at the exact same millisecond.
* 🧩 **M:N Relationship Resolution:** Complex Many-to-Many relationships are handled via associative entities (`REQUEST_ITEM` links Requests to Medicines; `DISTRIBUTION_ITEM` links Distributions to Batches).
* 📏 **Strict Data Integrity:** Enforced via `FOREIGN KEY` constraints and `CHECK` constraints (e.g., `expiry_date > manufacturing_date`, `quantity_available >= 0`).
* ⏳ **FEFO Allocation:** The SQL `ORDER BY expiry_date ASC` ensures that medicines expiring soonest are distributed first.

---

## 🛠️ Tech Stack
*   **Backend:** PHP 8 (using **PDO** with Prepared Statements to prevent SQL Injection)
*   **Database:** MySQL / MariaDB
*   **Frontend:** HTML5, CSS3 (Bootstrap 5 with custom Teal Theme)
*   **Client-Side:** Vanilla JavaScript (DataTables.js for interactive search/sorting, SweetAlert2 for beautiful confirmation popups)

---

## 📁 Project Directory Structure
```text
medicycle/
│
├── admin/                     # 🔑 ADMIN MODULE
│   ├── dashboard.php          # Global stats & overview
│   ├── verify_org.php         # Anti-Fraud: Approve/Reject new organizations
│   ├── review_donations.php   # View pending donor submissions
│   ├── create_batch.php       # Approve donation & set available stock
│   ├── review_requests.php    # View org medicine requests
│   ├── distribute.php         # FEFO transaction execution (ACID Logic)
│   ├── batches.php            # Full inventory view
│   ├── near_expiry.php        # 30-day expiry warning report
│   ├── distributions.php      # History of all distributed stock
│   └── reports.php            # Complex JOIN data tables
│
├── organization/              # 🏢 ORGANIZATION MODULE (Receivers)
│   ├── dashboard.php          # Org stats & verification status alert
│   ├── request.php            # Dynamic form with strict stock validation
│   ├── my_requests.php        # History of org's specific requests
│   ├── my_distributions.php   # Record of medicines physically received
│   └── profile.php            # Org details
│
├── donor/                     # 👤 DONOR MODULE (Suppliers)
│   ├── dashboard.php          # Donor's personal donation history
│   └── donate.php             # Form to submit new medicines
│
├── includes/                  # 🛡️ SHARED UI & SECURITY
│   ├── header.php             # Navbar, Sidebar, CSS links
│   ├── footer.php             # Closing tags, JS scripts
│   └── auth.php               # Session validation (Route Guard)
│
├── config/                    # ⚙️ DATABASE CONFIGURATION
│   └── db_connect.php         # PDO Connection string
│
├── assets/css/style.css       # Custom Teal theme styling
├── index.php                  # Main Landing Page / Universal Login
├── logout.php                 # Destroys session and redirects
│
├── schema.sql                 # DDL: 10 Tables, FKs, CHECK constraints
├── seed_data.sql              # DML: Sample data for testing
└── reports_and_logic.sql      # Raw SQL for reports & FEFO logic