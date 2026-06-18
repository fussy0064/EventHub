<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseModel.php';

class User extends DatabaseModel
{
    protected ?int $id = null;
    protected string $name = '';
    protected string $email = '';
    protected string $passwordHash = '';
    protected string $role = 'attendee';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
    }

    public function setPassword(string $password): void
    {
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $allowedRoles = ['attendee', 'organizer'];
        if (in_array($role, $allowedRoles, true)) {
            $this->role = $role;
        }
    }

    public function save(): bool
    {
        if (!$this->db) {
            return false;
        }

        $encryptedName = app_encrypt($this->name);

        if ($this->id === null) {
            $stmt = $this->db->prepare('
                INSERT INTO users (name, email, password_hash, role)
                VALUES (:name, :email, :password_hash, :role)
            ');
            $result = $stmt->execute([
                'name' => $encryptedName,
                'email' => $this->email,
                'password_hash' => $this->passwordHash,
                'role' => $this->role
            ]);
            if ($result) {
                $this->id = (int) $this->db->lastInsertId();
                return true;
            }
            return false;
        } else {
            $stmt = $this->db->prepare('
                UPDATE users
                SET name = :name, email = :email, password_hash = :password_hash, role = :role
                WHERE id = :id
            ');
            return $stmt->execute([
                'id' => $this->id,
                'name' => $encryptedName,
                'email' => $this->email,
                'password_hash' => $this->passwordHash,
                'role' => $this->role
            ]);
        }
    }

    public function delete(): bool
    {
        if (!$this->db || $this->id === null) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $this->id]);
    }

    public static function findById(PDO $db, int $id): ?User
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return self::fromRow($db, $row);
    }

    public static function findByEmail(PDO $db, string $email): ?User
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return self::fromRow($db, $row);
    }

    public static function fromRow(PDO $db, array $row): User
    {
        require_once __DIR__ . '/Organizer.php';
        require_once __DIR__ . '/Attendee.php';

        $role = $row['role'] ?? 'attendee';
        $user = ($role === 'organizer') ? new Organizer($db) : new Attendee($db);
        $user->id = (int) $row['id'];
        $user->setEmail($row['email']);
        $user->passwordHash = $row['password_hash'];
        $user->setName(app_decrypt($row['name']));
        return $user;
    }
}
