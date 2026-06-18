<?php

declare(strict_types=1);

abstract class DatabaseModel
{
    protected ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: app_db();
    }

    abstract public function save(): bool;

    abstract public function delete(): bool;
}
