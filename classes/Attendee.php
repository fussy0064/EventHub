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
}
