-- ============================================================
--  MediCycle — Database Schema (MySQL 8 / MariaDB 10.4+)
--  DBMS Mini-Project
--  Engine: InnoDB (required for FOREIGN KEY + transactions)
-- ============================================================

CREATE DATABASE IF NOT EXISTS medicycle_final
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE medicycle_final;

-- Drop in reverse dependency order (safe re-run)
DROP TABLE IF EXISTS DISTRIBUTION_ITEM;
DROP TABLE IF EXISTS DISTRIBUTION;
DROP TABLE IF EXISTS REQUEST_ITEM;
DROP TABLE IF EXISTS MEDICINE_REQUEST;
DROP TABLE IF EXISTS MEDICINE_BATCH;
DROP TABLE IF EXISTS DONATION;
DROP TABLE IF EXISTS ORGANIZATION;
DROP TABLE IF EXISTS MEDICINE;
DROP TABLE IF EXISTS ADMIN;
DROP TABLE IF EXISTS DONOR;

-- ============================================================
-- 1. DONOR
-- ============================================================
CREATE TABLE DONOR (
    donor_id      INT AUTO_INCREMENT PRIMARY KEY,
    donor_name    VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    -- NULL = guest donation recorded before the donor created a portal account.
    password_hash VARCHAR(255) NULL,
    phone         VARCHAR(20)  NOT NULL,
    address       VARCHAR(255) NOT NULL,
    CONSTRAINT uq_donor_email UNIQUE (email)
) ENGINE=InnoDB;

-- ============================================================
-- 2. ADMIN
-- ============================================================
CREATE TABLE ADMIN (
    admin_id      INT AUTO_INCREMENT PRIMARY KEY,
    admin_name    VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    CONSTRAINT uq_admin_email UNIQUE (email)
) ENGINE=InnoDB;

-- ============================================================
-- 3. MEDICINE
-- ============================================================
CREATE TABLE MEDICINE (
    medicine_id   INT AUTO_INCREMENT PRIMARY KEY,
    medicine_name VARCHAR(150) NOT NULL,
    category      VARCHAR(100) NOT NULL,
    manufacturer  VARCHAR(150) NOT NULL,
    description   TEXT NULL,
    CONSTRAINT uq_medicine_name_manufacturer UNIQUE (medicine_name, manufacturer)
) ENGINE=InnoDB;

-- ============================================================
-- 4. ORGANIZATION
-- ============================================================
CREATE TABLE ORGANIZATION (
    organization_id     INT AUTO_INCREMENT PRIMARY KEY,
    organization_name   VARCHAR(150) NOT NULL,
    organization_type   VARCHAR(100) NOT NULL,
    address             VARCHAR(255) NOT NULL,
    contact             VARCHAR(50)  NOT NULL,
    contact_person      VARCHAR(100) NOT NULL,
    email               VARCHAR(150) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    verification_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
    CONSTRAINT uq_org_email UNIQUE (email),
    INDEX idx_org_verification (verification_status)
) ENGINE=InnoDB;

-- ============================================================
-- 5. DONATION
--    donor_id    -> DONOR      (RESTRICT)
--    verified_by -> ADMIN      (RESTRICT, NULL until reviewed)
-- ============================================================
CREATE TABLE DONATION (
    donation_id     INT AUTO_INCREMENT PRIMARY KEY,
    donor_id        INT NOT NULL,
    verified_by     INT NULL,
    donation_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    donation_status ENUM('Pending','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
    INDEX idx_donation_donor (donor_id),
    INDEX idx_donation_admin (verified_by),
    INDEX idx_donation_status (donation_status),
    CONSTRAINT fk_donation_donor
        FOREIGN KEY (donor_id) REFERENCES DONOR (donor_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_donation_admin
        FOREIGN KEY (verified_by) REFERENCES ADMIN (admin_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. MEDICINE_BATCH
--    donation_id -> DONATION  (RESTRICT)
--    medicine_id -> MEDICINE  (RESTRICT)
-- ============================================================
CREATE TABLE MEDICINE_BATCH (
    batch_id           INT AUTO_INCREMENT PRIMARY KEY,
    donation_id        INT NOT NULL,
    medicine_id        INT NOT NULL,
    batch_number       VARCHAR(50) NOT NULL,
    manufacturing_date DATE NOT NULL,
    expiry_date        DATE NOT NULL,
    quantity_received  INT NOT NULL,
    quantity_available INT NOT NULL,
    CONSTRAINT uq_batch_medicine_number UNIQUE (medicine_id, batch_number),
    INDEX idx_batch_donation (donation_id),
    INDEX idx_batch_medicine (medicine_id),
    INDEX idx_batch_expiry (expiry_date),
    CONSTRAINT fk_batch_donation
        FOREIGN KEY (donation_id) REFERENCES DONATION (donation_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_batch_medicine
        FOREIGN KEY (medicine_id) REFERENCES MEDICINE (medicine_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_batch_expiry_after_manufacture CHECK (expiry_date > manufacturing_date),
    CONSTRAINT chk_batch_received_positive CHECK (quantity_received > 0),
    CONSTRAINT chk_batch_available_nonneg CHECK (quantity_available >= 0),
    CONSTRAINT chk_batch_available_lte_received CHECK (quantity_available <= quantity_received)
) ENGINE=InnoDB;

-- ============================================================
-- 7. MEDICINE_REQUEST
--    organization_id -> ORGANIZATION (RESTRICT)
--    reviewed_by     -> ADMIN        (RESTRICT, NULL until reviewed)
-- ============================================================
CREATE TABLE MEDICINE_REQUEST (
    request_id     INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    reviewed_by    INT NULL,
    request_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    INDEX idx_request_organization (organization_id),
    INDEX idx_request_admin (reviewed_by),
    INDEX idx_request_status (request_status),
    CONSTRAINT fk_request_organization
        FOREIGN KEY (organization_id) REFERENCES ORGANIZATION (organization_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_request_admin
        FOREIGN KEY (reviewed_by) REFERENCES ADMIN (admin_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. REQUEST_ITEM  (resolves M:N MEDICINE_REQUEST <-> MEDICINE)
--    request_id  -> MEDICINE_REQUEST (CASCADE)
--    medicine_id -> MEDICINE         (RESTRICT)
-- ============================================================
CREATE TABLE REQUEST_ITEM (
    request_item_id    INT AUTO_INCREMENT PRIMARY KEY,
    request_id         INT NOT NULL,
    medicine_id        INT NOT NULL,
    requested_quantity INT NOT NULL,
    CONSTRAINT uq_request_item UNIQUE (request_id, medicine_id),
    INDEX idx_request_item_request (request_id),
    INDEX idx_request_item_medicine (medicine_id),
    CONSTRAINT fk_request_item_request
        FOREIGN KEY (request_id) REFERENCES MEDICINE_REQUEST (request_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_request_item_medicine
        FOREIGN KEY (medicine_id) REFERENCES MEDICINE (medicine_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_request_item_qty_positive CHECK (requested_quantity > 0)
) ENGINE=InnoDB;

-- ============================================================
-- 9. DISTRIBUTION
--    request_id   -> MEDICINE_REQUEST (RESTRICT)
--    approved_by  -> ADMIN            (RESTRICT, NULL until approved)
-- ============================================================
CREATE TABLE DISTRIBUTION (
    distribution_id     INT AUTO_INCREMENT PRIMARY KEY,
    request_id          INT NOT NULL,
    approved_by         INT NULL,
    distribution_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    distribution_status ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    INDEX idx_distribution_request (request_id),
    INDEX idx_distribution_admin (approved_by),
    INDEX idx_distribution_status (distribution_status),
    CONSTRAINT fk_distribution_request
        FOREIGN KEY (request_id) REFERENCES MEDICINE_REQUEST (request_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_distribution_admin
        FOREIGN KEY (approved_by) REFERENCES ADMIN (admin_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. DISTRIBUTION_ITEM  (resolves M:N DISTRIBUTION <-> MEDICINE_BATCH)
--     distribution_id -> DISTRIBUTION   (CASCADE)
--     batch_id        -> MEDICINE_BATCH (RESTRICT)
-- ============================================================
CREATE TABLE DISTRIBUTION_ITEM (
    distribution_item_id   INT AUTO_INCREMENT PRIMARY KEY,
    distribution_id        INT NOT NULL,
    batch_id               INT NOT NULL,
    distributed_quantity   INT NOT NULL,
    CONSTRAINT uq_distribution_item UNIQUE (distribution_id, batch_id),
    INDEX idx_distribution_item_dist (distribution_id),
    INDEX idx_distribution_item_batch (batch_id),
    CONSTRAINT fk_distribution_item_distribution
        FOREIGN KEY (distribution_id) REFERENCES DISTRIBUTION (distribution_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_distribution_item_batch
        FOREIGN KEY (batch_id) REFERENCES MEDICINE_BATCH (batch_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_distribution_item_qty_positive CHECK (distributed_quantity > 0)
) ENGINE=InnoDB;
