-- Exclusive Resort: GCash only, pay in full OR 20% down payment + balance.
-- Run once on the live database (phpMyAdmin > SQL).
-- NOTE: the site also adds the new columns automatically the first time any page
-- loads. If you get "Duplicate column name", the columns already exist — run only
-- the UPDATE statements below.

ALTER TABLE `reservations`
  MODIFY `payment_status` ENUM('Unpaid','Pending Verification','Partially Paid','Paid') DEFAULT 'Unpaid',
  ADD COLUMN `payment_option` ENUM('Full','Downpayment') NOT NULL DEFAULT 'Full',
  ADD COLUMN `balance_due_date` DATE NULL;

ALTER TABLE `gcash_payments`
  ADD COLUMN `payment_type` ENUM('Full','Downpayment','Balance') NOT NULL DEFAULT 'Full';

-- Existing Exclusive bookings were charged a 20% down payment already:
UPDATE `gcash_payments` g
JOIN `reservations` r ON r.id = g.reservation_id
SET g.payment_type = 'Downpayment'
WHERE r.booking_type = 'Exclusive' AND g.amount < r.total_price;

UPDATE `reservations` r
SET r.payment_option = 'Downpayment', r.balance_due_date = r.check_in
WHERE r.booking_type = 'Exclusive'
  AND EXISTS (SELECT 1 FROM `gcash_payments` g WHERE g.reservation_id = r.id AND g.payment_type = 'Downpayment');

-- Recompute payment_status from verified payments (duplicate PayMongo rows ignored).
UPDATE `reservations` r
JOIN (
    SELECT reservation_id, SUM(amt) AS paid
    FROM (
        SELECT reservation_id, reference_number, MAX(amount) AS amt
        FROM `gcash_payments`
        WHERE status = 'Verified'
        GROUP BY reservation_id, reference_number
    ) d
    GROUP BY reservation_id
) p ON p.reservation_id = r.id
SET r.payment_status = CASE WHEN p.paid >= r.total_price THEN 'Paid' ELSE 'Partially Paid' END
WHERE r.status <> 'Cancelled';
