CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARBINARY(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('organizer', 'attendee', 'admin') NOT NULL DEFAULT 'attendee',
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NOT NULL,
    name VARBINARY(255) NOT NULL,
    description VARBINARY(2000) NOT NULL,
    date_time DATETIME NOT NULL,
    location VARBINARY(255) NOT NULL,
    tickets_available INT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS event_ticket_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    class_name ENUM('VVIP', 'VIP', 'Regular') NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tickets_available INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_ticket_class_event FOREIGN KEY (event_id) REFERENCES events(id),
    UNIQUE KEY uniq_event_class (event_id, class_name)
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    ticket_class_id INT NULL,
    tickets_booked INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_bookings_event FOREIGN KEY (event_id) REFERENCES events(id),
    CONSTRAINT fk_bookings_ticket_class FOREIGN KEY (ticket_class_id) REFERENCES event_ticket_classes(id)
);
