<?php

declare(strict_types=1);

final class Database
{
    private string $host;
    private string $name;
    private string $user;
    private string $password;
    private int $port;
    private array $options;

    public function __construct()
    {
        // AWS RDS environment variables take priority, then .env fallbacks
        $this->host = getenv('RDS_HOSTNAME') ?: (getenv('DB_HOST') ?: '127.0.0.1');
        $this->name = getenv('RDS_DB_NAME') ?: (getenv('DB_NAME') ?: 'eventhub');
        $this->user = getenv('RDS_USERNAME') ?: (getenv('DB_USER') ?: 'root');
        $this->password = getenv('RDS_PASSWORD') ?: (getenv('DB_PASSWORD') ?: '');
        $this->port = (int) (getenv('RDS_PORT') ?: (getenv('DB_PORT') ?: 3306));

        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        // Use SSL for AWS RDS connections in production
        if (getenv('RDS_HOSTNAME') && file_exists('/etc/pki/tls/certs/ca-bundle.crt')) {
            $this->options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/pki/tls/certs/ca-bundle.crt';
            $this->options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    public function connection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->name
        );

        try {
            return new PDO($dsn, $this->user, $this->password, $this->options);
        } catch (\PDOException $e) {
            // In production, don't expose database credentials in error messages
            if (getenv('APP_ENV') === 'production') {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \PDOException('Database connection failed. Please try again later.');
            }
            throw $e;
        }
    }
}
