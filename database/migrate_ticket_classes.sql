-- Run this ONCE on your live database, then pull the new code.

CREATE TABLE IF NOT EXISTS event_ticket_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    class_name ENUM('VVIP', 'VIP', 'Regular') NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tickets_available INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_ticket_class_event FOREIGN KEY (event_id) REFERENCES events(id),
    UNIQUE KEY uniq_event_class (event_id, class_name)
);

ALTER TABLE bookings ADD COLUMN ticket_class_id INT NULL AFTER event_id;

-- Turn every existing event's current price/tickets into a "Regular" class
INSERT INTO event_ticket_classes (event_id, class_name, price, tickets_available)
SELECT id, 'Regular', price, tickets_available FROM events;

-- Point existing bookings at that new Regular class
UPDATE bookings b
JOIN event_ticket_classes tc ON tc.event_id = b.event_id AND tc.class_name = 'Regular'
SET b.ticket_class_id = tc.id
WHERE b.ticket_class_id IS NULL;

ALTER TABLE bookings ADD CONSTRAINT fk_bookings_ticket_class FOREIGN KEY (ticket_class_id) REFERENCES event_ticket_classes(id);
