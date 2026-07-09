<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseModel.php';

class TicketClass extends DatabaseModel
{
    public const CLASSES = ['VVIP', 'VIP', 'Regular'];

    private ?int $id = null;
    private int $eventId = 0;
    private string $className = 'Regular';
    private float $price = 0.0;
    private int $ticketsAvailable = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setClassName(string $className): void
    {
        if (in_array($className, self::CLASSES, true)) {
            $this->className = $className;
        }
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = max(0.0, $price);
    }

    public function getTicketsAvailable(): int
    {
        return $this->ticketsAvailable;
    }

    public function setTicketsAvailable(int $ticketsAvailable): void
    {
        $this->ticketsAvailable = max(0, $ticketsAvailable);
    }

    public function save(): bool
    {
        if (!$this->db) {
            return false;
        }

        if ($this->id === null) {
            $stmt = $this->db->prepare('
                INSERT INTO event_ticket_classes (event_id, class_name, price, tickets_available)
                VALUES (:event_id, :class_name, :price, :tickets_available)
            ');
            $result = $stmt->execute([
                'event_id' => $this->eventId,
                'class_name' => $this->className,
                'price' => $this->price,
                'tickets_available' => $this->ticketsAvailable
            ]);
            if ($result) {
                $this->id = (int) $this->db->lastInsertId();
                return true;
            }
            return false;
        }

        $stmt = $this->db->prepare('
            UPDATE event_ticket_classes
            SET class_name = :class_name, price = :price, tickets_available = :tickets_available
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $this->id,
            'class_name' => $this->className,
            'price' => $this->price,
            'tickets_available' => $this->ticketsAvailable
        ]);
    }

    public function delete(): bool
    {
        if (!$this->db || $this->id === null) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM event_ticket_classes WHERE id = :id');
        return $stmt->execute(['id' => $this->id]);
    }

    public static function find(PDO $db, int $id): ?TicketClass
    {
        $stmt = $db->prepare('SELECT * FROM event_ticket_classes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::fromRow($db, $row) : null;
    }

    public static function findByEventId(PDO $db, int $eventId): array
    {
        $stmt = $db->prepare('SELECT * FROM event_ticket_classes WHERE event_id = :event_id ORDER BY FIELD(class_name, "VVIP", "VIP", "Regular")');
        $stmt->execute(['event_id' => $eventId]);
        $classes = [];
        while ($row = $stmt->fetch()) {
            $classes[] = self::fromRow($db, $row);
        }
        return $classes;
    }

    public static function fromRow(PDO $db, array $row): TicketClass
    {
        $tc = new TicketClass($db);
        $tc->id = (int) $row['id'];
        $tc->eventId = (int) $row['event_id'];
        $tc->setClassName($row['class_name']);
        $tc->setPrice((float) $row['price']);
        $tc->setTicketsAvailable((int) $row['tickets_available']);
        return $tc;
    }

    /**
     * Keep events.tickets_available and events.price (min price) in sync
     * so old code that reads those two columns still shows sensible numbers.
     */
    public static function syncEventTotals(PDO $db, int $eventId): void
    {
        $stmt = $db->prepare('
            SELECT COALESCE(SUM(tickets_available), 0) AS total_tickets, COALESCE(MIN(price), 0) AS min_price
            FROM event_ticket_classes WHERE event_id = :event_id
        ');
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch();

        $stmt = $db->prepare('UPDATE events SET tickets_available = :tickets, price = :price WHERE id = :id');
        $stmt->execute([
            'tickets' => (int) ($row['total_tickets'] ?? 0),
            'price' => (float) ($row['min_price'] ?? 0),
            'id' => $eventId
        ]);
    }
}
