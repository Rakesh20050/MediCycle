# 🏥 MediCycle: Medicine Donation & Expiry Tracking Management System

**A Database Management Systems (DBMS) Project**

> MediCycle is a full-stack web application designed to bridge the gap between medicine donors and verified healthcare organizations. It ensures traceability, reduces wastage, and manages stock distribution using strict relational database principles and First-Expiry-First-Out (FEFO) logic.

## 📌 Project Overview
Unused or near-expiry medicine stock often goes to waste while charitable organizations face shortages. MediCycle solves this by providing a centralized platform where:
*   **Donors** can submit medicine donations.
*   **Admins** verify donations, manage inventory, and distribute stock.
*   **Organizations** can request medicines (only after admin verification).

## 🛠️ Tech Stack
*   **Database:** MySQL / MariaDB (Managed via XAMPP/phpMyAdmin)
*   **Backend:** PHP 8 (using PDO for secure database transactions)
*   **Frontend:** HTML5, CSS3 (Bootstrap 5), Vanilla JavaScript
*   **Client-Side Libraries:** DataTables.js (Interactive tables), SweetAlert2 (Beautiful popups)

## ⚙️ Core DBMS Concepts Implemented
This project goes beyond basic CRUD operations and heavily focuses on database integrity and relational theory:
*   **BCNF Normalization:** The database consists of 10 tables, strictly normalized to eliminate data redundancy.
*   **M:N Relationship Resolution:** Associative entities (`REQUEST_ITEM`, `DISTRIBUTION_ITEM`) are used to resolve Many-to-Many relationships.
*   **ACID Transactions:** The distribution process uses `START TRANSACTION`, `ROLLBACK`, and `COMMIT` to ensure all-or-nothing stock deduction.
*   **Row-Level Locking:** Uses `SELECT ... FOR UPDATE` during distribution to prevent concurrent over-allocation (negative stock) by multiple admins.
*   **Strict Constraints:** Enforces `FOREIGN KEY` (referential integrity), `UNIQUE` (preventing duplicate batches), and `CHECK` constraints (e.g., `expiry_date > manufacturing_date`, `quantity_available >= 0`).
*   **FEFO Allocation:** The system uses a First-Expiry-First-Out approach when selecting batches to distribute.

## 🗄️ Database Schema (10 Tables)
1.  `DONOR`: Stores donor details.
2.  `ADMIN`: Stores admin credentials.
3.  `MEDICINE`: Master list of medicines.
4.  `ORGANIZATION`: Verified NGOs/Hospitals.
5.  `DONATION`: Links donor to admin verification.
6.  `MEDICINE_BATCH`: Batch-wise stock tracking and expiry dates.
7.  `MEDICINE_REQUEST`: Requests submitted by organizations.
8.  `REQUEST_ITEM`: Links requests to specific medicines (M:N resolver).
9.  `DISTRIBUTION`: Records approved distributions.
10. `DISTRIBUTION_ITEM`: Links distributions to specific batches (M:N resolver).

## 🚀 How to Run the Project Locally

**Step 1: Set up the Database**
1. Install and open **XAMPP**. Start the **Apache** and **MySQL** modules.
2. Open `http://localhost/phpmyadmin`.
3. Create a new database named `medicycle_final` (or your preferred name).
4. Click on the database, go to the **Import** tab, and import the `database/schema.sql` file.
5. Import the `database/seed_data.sql` file to populate the database with sample data.

**Step 2: Run the Application**
1. Move the entire project folder into your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\medicycle`).
2. Open your browser and navigate to `http://localhost/medicycle/`.
3. **Default Admin Credentials:**
   *   Email: `admin@medicycle.com`
   *   Password: `password123`

## 📁 Folder Structure
```text
medicycle/
├── database/
│   ├── schema.sql               # DDL: CREATE TABLE statements & constraints
│   ├── seed_data.sql            # DML: Sample INSERT statements
│   ├── reports.sql              # Complex SELECT/JOIN queries
│   └── fefo_logic.sql           # Transactional logic for FEFO
├── config/
│   └── db_connect.php           # Database connection (PDO)
├── includes/
│   ├── header.php               # Bootstrap Navbar & CSS links
│   └── footer.php               # Closing tags & JS links
├── admin/                       # Admin dashboard, verify, distribute, reports
├── organization/                # Org dashboard, request medicine
├── donor/                       # Donor dashboard, donate medicine
├── index.php                    # Landing page / Login
└── README.md