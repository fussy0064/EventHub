<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseModel.php';

class Event extends DatabaseModel
{
    private ?int $id = null;
    private int $organizerId = 0;
    private string $name = '';
    private string $description = '';
    private string $dateTime = '';
    private string $location = '';
    private int $ticketsAvailable = 0;
    private float $price = 0.0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizerId(): int
    {
        return $this->organizerId;
    }

    public function setOrganizerId(int $organizerId): void
    {
        $this->organizerId = $organizerId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = trim($description);
    }

    public function getDateTime(): string
    {
        return $this->dateTime;
    }

    public function setDateTime(string $dateTime): void
    {
        $this->dateTime = trim($dateTime);
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $this->location = trim($location);
    }

    public function getTicketsAvailable(): int
    {
        return $this->ticketsAvailable;
    }

    public function setTicketsAvailable(int $ticketsAvailable): void
    {
        $this->ticketsAvailable = max(0, $ticketsAvailable);
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = max(0.0, $price);
    }

    public function save(): bool
    {
        if (!$this->db) {
            return false;
        }

        $encryptedName = app_encrypt($this->name);
        $encryptedDescription = app_encrypt($this->description);
        $encryptedLocation = app_encrypt($this->location);

        if ($this->id === null) {
            $stmt = $this->db->prepare('
                INSERT INTO events (organizer_id, name, description, date_time, location, tickets_available, price)
                VALUES (:organizer_id, :name, :description, :date_time, :location, :tickets_available, :price)
            ');
            $result = $stmt->execute([
                'organizer_id' => $this->organizerId,
                'name' => $encryptedName,
                'description' => $encryptedDescription,
                'date_time' => $this->dateTime,
                'location' => $encryptedLocation,
                'tickets_available' => $this->ticketsAvailable,
                'price' => $this->price
            ]);
            if ($result) {
                $this->id = (int) $this->db->lastInsertId();
                return true;
            }
            return false;
        } else {
            $stmt = $this->db->prepare('
                UPDATE events
                SET organizer_id = :organizer_id, name = :name, description = :description,
                    date_time = :date_time, location = :location, tickets_available = :tickets_available,
                    price = :price
                WHERE id = :id
            ');
            return $stmt->execute([
                'id' => $this->id,
                'organizer_id' => $this->organizerId,
                'name' => $encryptedName,
                'description' => $encryptedDescription,
                'date_time' => $this->dateTime,
                'location' => $encryptedLocation,
                'tickets_available' => $this->ticketsAvailable,
                'price' => $this->price
            ]);
        }
    }

    public function delete(): bool
    {
        if (!$this->db || $this->id === null) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM events WHERE id = :id');
        return $stmt->execute(['id' => $this->id]);
    }

    public static function find(PDO $db, int $id): ?Event
    {
        $stmt = $db->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return self::fromRow($db, $row);
    }

    public static function fromRow(PDO $db, array $row): Event
    {
        $event = new Event($db);
        $event->id = (int) $row['id'];
        $event->organizerId = (int) $row['organizer_id'];
        $event->setName(app_decrypt($row['name']));
        $event->setDescription(app_decrypt($row['description']));
        $event->setDateTime($row['date_time']);
        $event->setLocation(app_decrypt($row['location']));
        $event->setTicketsAvailable((int) $row['tickets_available']);
        $event->setPrice((float) $row['price']);
        return $event;
    }

    public static function all(PDO $db): array
    {
        $stmt = $db->query('SELECT * FROM events ORDER BY date_time ASC');
        $events = [];
        while ($row = $stmt->fetch()) {
            $events[] = self::fromRow($db, $row);
        }
        return $events;
    }

    public static function search(PDO $db, string $query): array
    {
        $events = self::all($db);
        if ($query === '') {
            return $events;
        }
        $query = strtolower($query);
        return array_filter($events, function (Event $event) use ($query) {
            return str_contains(strtolower($event->getName()), $query) ||
                   str_contains(strtolower($event->getDescription()), $query) ||
                   str_contains(strtolower($event->getLocation()), $query) ||
                   str_contains(strtolower($event->getDateTime()), $query);
        });
    }
}
