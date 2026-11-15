-- ============================================================
--  MediCycle — Sample Seed Data
--  Run AFTER schema.sql
--  Admin login: admin@medicycle.com / password123
--  Dates are relative to CURDATE() so the report stays valid.
-- ============================================================

USE medicycle_final;

-- Clear existing rows child-first (DELETE works with RESTRICT keys;
-- MariaDB refuses TRUNCATE on a table referenced by a FOREIGN KEY)
DELETE FROM DISTRIBUTION_ITEM;
DELETE FROM DISTRIBUTION;
DELETE FROM REQUEST_ITEM;
DELETE FROM MEDICINE_REQUEST;
DELETE FROM MEDICINE_BATCH;
DELETE FROM DONATION;
DELETE FROM ORGANIZATION;
DELETE FROM MEDICINE;
DELETE FROM ADMIN;
DELETE FROM DONOR;

-- ============================================================
-- ADMIN (bcrypt hash of 'password123')
-- ============================================================
INSERT INTO ADMIN (admin_id, admin_name, email, password_hash) VALUES
(1, 'System Admin', 'admin@medicycle.com', '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.');

-- ============================================================
-- DONORS (bcrypt hash of 'password123'; donor portal login)
-- ============================================================
INSERT INTO DONOR (donor_id, donor_name, email, password_hash, phone, address) VALUES
(1, 'Rahul Mehta', 'rahul.mehta@example.com', '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.', '+91 98765 43210', '14 Green Park, New Delhi 110016'),
(2, 'Ananya Rao', 'ananya.rao@example.com', '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.', '+91 91234 56789', '27 Lake Vista, Bengaluru 560034');

-- ============================================================
-- ORGANIZATIONS (2 Verified, 1 Pending)
-- Demo login password for every account: password123
-- ============================================================
INSERT INTO ORGANIZATION (organization_id, organization_name, organization_type, address, contact, contact_person, email, password_hash, verification_status) VALUES
(1, 'City Care Foundation',    'NGO',             '8 Ring Road, Pune 411001',         '+91 98200 11122', 'Meera Joshi',     'citycare@medicycle.com', '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.', 'Verified'),
(2, 'Hope Medical Trust',      'Hospital',        '55 Station Road, Mumbai 400001',   '+91 98330 44556', 'Dr. Arjun Nair',  'hope@medicycle.com',     '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.', 'Verified'),
(3, 'Sunrise Relief Society',  'Charitable Trust','12 Hill View, Jaipur 302001',      '+91 94140 77889', 'Kunal Shah',      'sunrise@medicycle.com',  '$2y$10$ZaeSVBelvzbXG/aSHdL8AOpur8QNYILDWU51undh8MqvZ/XwgleN.', 'Pending');

-- ============================================================
-- MEDICINES
-- ============================================================
INSERT INTO MEDICINE (medicine_id, medicine_name, category, manufacturer, description) VALUES
(1, 'Paracetamol 500mg', 'Analgesic / Antipyretic', 'Cipla Ltd.',       'Relief from mild to moderate pain and fever.'),
(2, 'Amoxicillin 250mg', 'Antibiotic',              'Sun Pharma',      'Broad-spectrum penicillin antibiotic for bacterial infections.'),
(3, 'Cetirizine 10mg',   'Antihistamine',           'Dr. Reddy''s Labs','Antiallergic tablet for sneezing, runny nose and itching.'),
(4, 'Metformin 500mg',   'Antidiabetic',            'USV Pvt. Ltd.',   'First-line oral medication for type 2 diabetes.');

-- ============================================================
-- DONATIONS
--   #1 Accepted (stock enters inventory as batches below)
--   #2 Pending  (awaiting admin review — WF-1 starting point)
-- ============================================================
INSERT INTO DONATION (donation_id, donor_id, verified_by, donation_date, donation_status) VALUES
(1, 1, 1,    NOW() - INTERVAL 20 DAY, 'Accepted'),
(2, 2, NULL, NOW() - INTERVAL 2 HOUR, 'Pending');

-- ============================================================
-- MEDICINE BATCHES (all from accepted donation #1)
--   Batch 1 expires in 10 days  -> drives FEFO + expiry alerts
-- ============================================================
INSERT INTO MEDICINE_BATCH
(batch_id, donation_id, medicine_id, batch_number, manufacturing_date, expiry_date, quantity_received, quantity_available) VALUES
(1, 1, 1, 'PCM-2401', CURDATE() - INTERVAL 350 DAY, CURDATE() + INTERVAL 10 DAY, 500, 500),
(2, 1, 2, 'AMX-2402', CURDATE() - INTERVAL 180 DAY, CURDATE() + INTERVAL 400 DAY, 300, 300),
(3, 1, 3, 'CTZ-2403', CURDATE() - INTERVAL 90 DAY,  CURDATE() + INTERVAL 250 DAY, 200, 200);

-- ============================================================
-- MEDICINE REQUEST (Approved, from verified org #1)
-- ============================================================
INSERT INTO MEDICINE_REQUEST (request_id, organization_id, reviewed_by, request_date, request_status) VALUES
(1, 1, 1, NOW() - INTERVAL 5 DAY, 'Approved');

INSERT INTO REQUEST_ITEM (request_item_id, request_id, medicine_id, requested_quantity) VALUES
(1, 1, 1, 100),
(2, 1, 3, 50);
