<?php

declare(strict_types=1);

require_once __DIR__ . '/User.php';

class Admin extends User
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->setRole('admin');
    }

    public function getDashboard(): array
    {
        $stmt = $this->db->query('SELECT * FROM users ORDER BY id DESC');
        $users = [];
        while ($row = $stmt->fetch()) {
            $users[] = User::fromRow($this->db, $row);
        }

        // Count pending organizers
        $pendingStmt = $this->db->query('SELECT COUNT(*) as cnt FROM users WHERE role = "organizer" AND is_approved = 0');
        $pendingCount = (int) ($pendingStmt->fetch()['cnt'] ?? 0);

        // All events on the platform, with who created each one
        require_once __DIR__ . '/Event.php';
        $eventStmt = $this->db->query('
            SELECT e.*, u.name AS organizer_name, u.email AS organizer_email
            FROM events e
            JOIN users u ON e.organizer_id = u.id
            ORDER BY e.date_time DESC
        ');
        $events = [];
        while ($row = $eventStmt->fetch()) {
            $events[] = [
                'object' => Event::fromRow($this->db, $row),
                'organizer_name' => app_decrypt($row['organizer_name']),
                'organizer_email' => $row['organizer_email'],
            ];
        }

        return [
            'type' => 'admin',
            'title' => 'Admin Dashboard',
            'users' => $users,
            'pending_organizers' => $pendingCount,
            'events' => $events
        ];
    }
}
