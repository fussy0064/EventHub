<?php

declare(strict_types=1);

require_once __DIR__ . '/User.php';

class Organizer extends User
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->setRole('organizer');
    }

    public function getDashboard(): array
    {
        require_once __DIR__ . '/Event.php';
        require_once __DIR__ . '/Booking.php';

        $stmt = $this->db->prepare('SELECT * FROM events WHERE organizer_id = :organizer_id ORDER BY date_time ASC');
        $stmt->execute(['organizer_id' => $this->id]);

        $events = [];
        $totalEvents = 0;
        $totalTicketsSold = 0;
        $totalRevenue = 0.0;

        while ($row = $stmt->fetch()) {
            $event = Event::fromRow($this->db, $row);

            $bookingStmt = $this->db->prepare('
                SELECT SUM(tickets_booked) AS tickets_sold 
                FROM bookings 
                WHERE event_id = :event_id AND status = "confirmed"
            ');
            $bookingStmt->execute(['event_id' => $event->getId()]);
            $bookingRow = $bookingStmt->fetch();
            $ticketsSold = (int) ($bookingRow['tickets_sold'] ?? 0);

            $events[] = [
                'object' => $event,
                'tickets_sold' => $ticketsSold
            ];

            $totalEvents++;
            $totalTicketsSold += $ticketsSold;
            $totalRevenue += ($ticketsSold * $event->getPrice());
        }

        $bookings = Booking::findByOrganizerId($this->db, $this->id);

        return [
            'type' => 'organizer',
            'title' => 'Organizer Dashboard',
            'stats' => [
                'total_events' => $totalEvents,
                'tickets_sold' => $totalTicketsSold,
                'revenue' => $totalRevenue,
            ],
            'events' => $events,
            'bookings' => $bookings
        ];
    }
}
