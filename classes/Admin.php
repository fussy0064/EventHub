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

        return [
            'type' => 'admin',
            'title' => 'Admin Dashboard',
            'users' => $users,
            'pending_organizers' => $pendingCount
        ];
    }
}
