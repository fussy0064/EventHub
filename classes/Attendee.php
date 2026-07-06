<?php

declare(strict_types=1);

require_once __DIR__ . '/User.php';

class Attendee extends User
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->setRole('attendee');
    }

    public function getDashboard(): array
    {
        require_once __DIR__ . '/Booking.php';

        $bookings = Booking::findByUserId($this->db, $this->id);

        return [
            'type' => 'attendee',
            'title' => 'Attendee Dashboard',
            'bookings' => $bookings
        ];
    }

    public function delete(): bool
    {
        if (!$this->db || $this->id === null) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            require_once __DIR__ . '/Booking.php';
            $bookings = Booking::findByUserId($this->db, $this->id);
            
            // Restore event tickets for confirmed bookings
            foreach ($bookings as $bData) {
                if ($bData['status'] === 'confirmed') {
                    $stmt = $this->db->prepare('
                        UPDATE events
                        SET tickets_available = tickets_available + :tickets
                        WHERE id = :event_id
                    ');
                    $stmt->execute([
                        'tickets' => (int) $bData['tickets_booked'],
                        'event_id' => (int) $bData['event_id']
                    ]);
                }
            }

            // Delete bookings
            $stmt = $this->db->prepare('DELETE FROM bookings WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $this->id]);

            $result = parent::delete();

            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            app_set_flash('error', 'Failed to delete attendee: ' . $e->getMessage());
            return false;
        }
    }
}
