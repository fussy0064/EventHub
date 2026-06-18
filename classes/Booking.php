<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseModel.php';

class Booking extends DatabaseModel
{
    private ?int $id = null;
    private int $userId = 0;
    private int $eventId = 0;
    private int $ticketsBooked = 0;
    private string $status = 'confirmed';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function getTicketsBooked(): int
    {
        return $this->ticketsBooked;
    }

    public function setTicketsBooked(int $ticketsBooked): void
    {
        $this->ticketsBooked = max(0, $ticketsBooked);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (in_array($status, ['confirmed', 'cancelled'], true)) {
            $this->status = $status;
        }
    }

    public function save(): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            if ($this->id === null) {
                // Check tickets availability
                $stmt = $this->db->prepare('SELECT tickets_available FROM events WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $this->eventId]);
                $eventRow = $stmt->fetch();

                if (!$eventRow) {
                    throw new Exception('Event not found.');
                }

                $available = (int) $eventRow['tickets_available'];
                if ($available < $this->ticketsBooked) {
                    throw new Exception('Not enough tickets available.');
                }

                // Insert booking
                $stmt = $this->db->prepare('
                    INSERT INTO bookings (user_id, event_id, tickets_booked, status)
                    VALUES (:user_id, :event_id, :tickets_booked, :status)
                ');
                $stmt->execute([
                    'user_id' => $this->userId,
                    'event_id' => $this->eventId,
                    'tickets_booked' => $this->ticketsBooked,
                    'status' => $this->status
                ]);
                $this->id = (int) $this->db->lastInsertId();

                // Deduct tickets from event (only if status is confirmed)
                if ($this->status === 'confirmed') {
                    $stmt = $this->db->prepare('
                        UPDATE events
                        SET tickets_available = tickets_available - :booked
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'booked' => $this->ticketsBooked,
                        'id' => $this->eventId
                    ]);
                }
            } else {
                // Update booking status
                $stmt = $this->db->prepare('SELECT status, tickets_booked FROM bookings WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $this->id]);
                $currentBooking = $stmt->fetch();

                if (!$currentBooking) {
                    throw new Exception('Booking not found.');
                }

                $oldStatus = $currentBooking['status'];
                $tickets = (int) $currentBooking['tickets_booked'];

                // Update booking status
                $stmt = $this->db->prepare('UPDATE bookings SET status = :status WHERE id = :id');
                $stmt->execute([
                    'status' => $this->status,
                    'id' => $this->id
                ]);

                // Adjust event inventory if status changed
                if ($oldStatus === 'confirmed' && $this->status === 'cancelled') {
                    $stmt = $this->db->prepare('
                        UPDATE events
                        SET tickets_available = tickets_available + :tickets
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'tickets' => $tickets,
                        'id' => $this->eventId
                    ]);
                } elseif ($oldStatus === 'cancelled' && $this->status === 'confirmed') {
                    $stmt = $this->db->prepare('SELECT tickets_available FROM events WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $this->eventId]);
                    $eventRow = $stmt->fetch();
                    if (!$eventRow) {
                        throw new Exception('Event not found.');
                    }
                    $available = (int) $eventRow['tickets_available'];
                    if ($available < $tickets) {
                        throw new Exception('Not enough tickets available to confirm booking.');
                    }

                    $stmt = $this->db->prepare('
                        UPDATE events
                        SET tickets_available = tickets_available - :tickets
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'tickets' => $tickets,
                        'id' => $this->eventId
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            app_set_flash('error', $e->getMessage());
            return false;
        }
    }

    public function delete(): bool
    {
        if (!$this->db || $this->id === null) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Check if we need to return tickets (only if booking was confirmed)
            $stmt = $this->db->prepare('SELECT status, tickets_booked FROM bookings WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $this->id]);
            $booking = $stmt->fetch();

            if ($booking && $booking['status'] === 'confirmed') {
                $stmt = $this->db->prepare('
                    UPDATE events
                    SET tickets_available = tickets_available + :tickets
                    WHERE id = :id
                ');
                $stmt->execute([
                    'tickets' => (int) $booking['tickets_booked'],
                    'id' => $this->eventId
                ]);
            }

            $stmt = $this->db->prepare('DELETE FROM bookings WHERE id = :id');
            $stmt->execute(['id' => $this->id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            app_set_flash('error', $e->getMessage());
            return false;
        }
    }

    public static function find(PDO $db, int $id): ?Booking
    {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $booking = new Booking($db);
        $booking->id = (int) $row['id'];
        $booking->userId = (int) $row['user_id'];
        $booking->eventId = (int) $row['event_id'];
        $booking->ticketsBooked = (int) $row['tickets_booked'];
        $booking->setStatus($row['status']);
        return $booking;
    }

    public static function findByUserId(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('
            SELECT b.*, e.name AS event_name, e.date_time AS event_date_time, e.location AS event_location, e.price AS event_price
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            WHERE b.user_id = :user_id
            ORDER BY b.created_at DESC
        ');
        $stmt->execute(['user_id' => $userId]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $row['event_name'] = app_decrypt($row['event_name']);
            $row['event_location'] = app_decrypt($row['event_location']);
            $results[] = $row;
        }
        return $results;
    }

    public static function findByOrganizerId(PDO $db, int $organizerId): array
    {
        $stmt = $db->prepare('
            SELECT b.*, e.name AS event_name, e.price AS event_price, u.name AS user_name, u.email AS user_email
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            JOIN users u ON b.user_id = u.id
            WHERE e.organizer_id = :organizer_id
            ORDER BY b.created_at DESC
        ');
        $stmt->execute(['organizer_id' => $organizerId]);
        $results = [];
        while ($row = $stmt->fetch()) {
            $row['event_name'] = app_decrypt($row['event_name']);
            $row['user_name'] = app_decrypt($row['user_name']);
            $results[] = $row;
        }
        return $results;
    }
}
