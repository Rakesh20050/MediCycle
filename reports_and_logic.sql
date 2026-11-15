-- ============================================================
--  MediCycle — Reports & FEFO Transaction Logic
--  Run after schema.sql + seed_data.sql
-- ============================================================

USE medicycle_final;

-- ============================================================
-- REPORT 1 — Near-expiry stock (expiring within 30 days)
-- Aggregated per medicine; expired/critical stock at a glance.
-- ============================================================
SELECT
    m.medicine_id,
    m.medicine_name,
    m.category,
    COUNT(mb.batch_id)                              AS batches_expiring,
    MIN(mb.expiry_date)                             AS earliest_expiry,
    DATEDIFF(MIN(mb.expiry_date), CURDATE())        AS days_to_earliest,
    SUM(mb.quantity_available)                      AS units_expiring
FROM MEDICINE_BATCH mb
JOIN MEDICINE m
    ON m.medicine_id = mb.medicine_id
JOIN DONATION dn
    ON dn.donation_id = mb.donation_id
WHERE mb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND mb.quantity_available > 0
GROUP BY m.medicine_id, m.medicine_name, m.category
ORDER BY earliest_expiry ASC;

-- ============================================================
-- REPORT 2 — Total available stock by medicine
-- LEFT JOIN keeps medicines with zero stock in the report.
-- ============================================================
SELECT
    m.medicine_id,
    m.medicine_name,
    m.category,
    m.manufacturer,
    COUNT(mb.batch_id)                                        AS active_batches,
    COALESCE(SUM(mb.quantity_received), 0)                    AS total_received,
    COALESCE(SUM(mb.quantity_available), 0)                   AS available_stock,
    COALESCE(SUM(CASE WHEN mb.expiry_date < CURDATE()
                      THEN mb.quantity_available ELSE 0 END), 0) AS expired_units
FROM MEDICINE m
LEFT JOIN MEDICINE_BATCH mb
    ON mb.medicine_id = m.medicine_id
GROUP BY m.medicine_id, m.medicine_name, m.category, m.manufacturer
ORDER BY available_stock DESC, m.medicine_name ASC;

-- ============================================================
-- REPORT 3 — Request history (who asked, what, and outcome)
-- ============================================================
SELECT
    mr.request_id,
    o.organization_name,
    o.organization_type,
    o.verification_status,
    mr.request_date,
    mr.request_status,
    COALESCE(a.admin_name, '—')          AS reviewed_by,
    COUNT(ri.request_item_id)            AS item_count,
    COALESCE(SUM(ri.requested_quantity), 0) AS total_units_requested
FROM MEDICINE_REQUEST mr
JOIN ORGANIZATION o
    ON o.organization_id = mr.organization_id
LEFT JOIN ADMIN a
    ON a.admin_id = mr.reviewed_by
LEFT JOIN REQUEST_ITEM ri
    ON ri.request_id = mr.request_id
GROUP BY
    mr.request_id, o.organization_name, o.organization_type,
    o.verification_status, mr.request_date, mr.request_status, a.admin_name
ORDER BY mr.request_date DESC;

-- ============================================================
-- REPORT 4 — Distribution history (batches used per distribution)
-- ============================================================
SELECT
    d.distribution_id,
    d.request_id,
    o.organization_name,
    d.distribution_date,
    d.distribution_status,
    COALESCE(a.admin_name, '—')                            AS approved_by,
    COUNT(di.distribution_item_id)                         AS batches_used,
    COALESCE(SUM(di.distributed_quantity), 0)              AS total_units_distributed,
    COALESCE(GROUP_CONCAT(DISTINCT m.medicine_name
             ORDER BY m.medicine_name SEPARATOR ', '), '—') AS medicines_supplied
FROM DISTRIBUTION d
JOIN MEDICINE_REQUEST mr
    ON mr.request_id = d.request_id
JOIN ORGANIZATION o
    ON o.organization_id = mr.organization_id
LEFT JOIN ADMIN a
    ON a.admin_id = d.approved_by
LEFT JOIN DISTRIBUTION_ITEM di
    ON di.distribution_id = d.distribution_id
LEFT JOIN MEDICINE_BATCH mb
    ON mb.batch_id = di.batch_id
LEFT JOIN MEDICINE m
    ON m.medicine_id = mb.medicine_id
GROUP BY
    d.distribution_id, d.request_id, o.organization_name,
    d.distribution_date, d.distribution_status, a.admin_name
ORDER BY d.distribution_date DESC;

-- ============================================================
-- FEFO TRANSACTION SIMULATION
-- Scenario: fulfil 100 units of Paracetamol (medicine_id = 1)
--           for approved request #1.
-- FEFO   : the earliest-expiring batch with stock (batch #1,
--          expiring in 10 days) must be consumed first.
-- ============================================================

START TRANSACTION;

-- 1. Lock the FEFO candidates so no concurrent session can
--    modify or consume them until COMMIT / ROLLBACK.
SELECT
    batch_id,
    medicine_id,
    batch_number,
    expiry_date,
    DATEDIFF(expiry_date, CURDATE()) AS days_to_expiry,
    quantity_available
FROM MEDICINE_BATCH
WHERE medicine_id = 1
  AND quantity_available > 0
  AND expiry_date >= CURDATE()
ORDER BY expiry_date ASC, batch_id ASC
LIMIT 100
FOR UPDATE;
-- LIMIT is placed before FOR UPDATE because SQL grammar requires it.
-- It also stops phpMyAdmin from appending its own "LIMIT 0, 25"
-- for pagination, which would land after FOR UPDATE and be invalid.
-- Expected first row: batch_id = 1 (PCM-2401, 10 days to expiry)

-- 2. Create the distribution (header).
INSERT INTO DISTRIBUTION (request_id, approved_by, distribution_date, distribution_status)
VALUES (1, 1, NOW(), 'Completed');

SET @distribution_id = LAST_INSERT_ID();

-- 3. Record the FEFO allocation line (batch #1 — earliest expiry).
INSERT INTO DISTRIBUTION_ITEM (distribution_id, batch_id, distributed_quantity)
VALUES (@distribution_id, 1, 100);

-- 4. Deduct stock. The guard `quantity_available >= 100` makes
--    the statement a no-op if stock is insufficient, in which case
--    rows_updated = 0 and the application must ROLLBACK.
UPDATE MEDICINE_BATCH
SET quantity_available = quantity_available - 100
WHERE batch_id = 1
  AND quantity_available >= 100;

SET @rows_updated = ROW_COUNT();

-- 5. Pre-commit verification (inside the transaction).
SELECT @distribution_id   AS distribution_id,
       @rows_updated      AS rows_updated,
       batch_id,
       batch_number,
       quantity_available
FROM MEDICINE_BATCH
WHERE batch_id = 1;

COMMIT;
-- In PHP:
--   if ($rowsUpdated === 0) { $pdo->rollBack(); }
--   else                    { $pdo->commit();   }

-- 6. Post-commit verification: stock reduced 500 -> 400,
--    distribution + item persisted.
SELECT
    di.distribution_item_id,
    di.distribution_id,
    mb.batch_number,
    mb.expiry_date,
    di.distributed_quantity,
    mb.quantity_available
FROM DISTRIBUTION_ITEM di
JOIN MEDICINE_BATCH mb
    ON mb.batch_id = di.batch_id
WHERE di.distribution_id = @distribution_id;
