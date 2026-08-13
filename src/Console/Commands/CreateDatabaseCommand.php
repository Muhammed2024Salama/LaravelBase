<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;
use RuntimeException;

class CreateDatabaseCommand extends Command
{
    protected $signature = 'base:create-database
        {--connection= : The connection to use (defaults to the configured default)}';

    protected $description = 'Create the configured database if it does not exist (supports MySQL & PostgreSQL)';

    public function handle(): int
    {
        $connectionOption = $this->option('connection');
        $default = config('database.default');

        $connection = (is_string($connectionOption) && $connectionOption !== '')
            ? $connectionOption
            : (is_string($default) ? $default : 'mysql');

        $raw = config("database.connections.{$connection}");

        if (! is_array($raw)) {
            $this->error("Connection [{$connection}] is not configured.");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $config */
        $config = $raw;

        $driverRaw = $config['driver'] ?? null;
        $driver = is_string($driverRaw) ? $driverRaw : '';

        $databaseRaw = $config['database'] ?? null;
        $database = is_string($databaseRaw) ? $databaseRaw : '';

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->error("Only 'mysql' and 'pgsql' are supported. Current driver: ".($driver ?: 'none'));

            return self::FAILURE;
        }

        if ($database === '') {
            $this->error('No database name is set for this connection.');

            return self::FAILURE;
        }

        try {
            return $driver === 'mysql'
                ? $this->createMysql($config, $database)
                : $this->createPgsql($config, $database);
        } catch (PDOException $e) {
            $this->error('Database operation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createMysql(array $config, string $database): int
    {
        $hostRaw = $config['host'] ?? null;
        $host = is_string($hostRaw) ? $hostRaw : '127.0.0.1';

        $portRaw = $config['port'] ?? null;
        $port = is_int($portRaw) ? $portRaw : (is_numeric($portRaw) ? (int) $portRaw : 3306);

        $charsetRaw = $config['charset'] ?? null;
        $charset = is_string($charsetRaw) ? $charsetRaw : 'utf8mb4';

        $collationRaw = $config['collation'] ?? null;
        $collation = is_string($collationRaw) ? $collationRaw : 'utf8mb4_unicode_ci';

        if (! $this->validateIdentifier($database)) {
            $this->error("Database name '{$database}' contains invalid characters. Only letters, digits, underscores, and hyphens are allowed.");

            return self::FAILURE;
        }

        if (! $this->validateIdentifier($charset)) {
            $this->error("Charset '{$charset}' contains invalid characters.");

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            $this->error("Collation '{$collation}' contains invalid characters.");

            return self::FAILURE;
        }

        if (! $this->hasPdoDriver('pdo_mysql')) {
            return self::FAILURE;
        }

        $pdo = $this->connect("mysql:host={$host};port={$port}", $config);

        $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        if ($statement === false) {
            throw new RuntimeException('Could not prepare the existence check.');
        }
        $statement->execute([$database]);

        if ($statement->fetchColumn() !== false) {
            $this->info("Database '{$database}' already exists. Nothing to do.");

            return self::SUCCESS;
        }

        $safeName = '`'.str_replace('`', '``', $database).'`';
        $pdo->exec("CREATE DATABASE {$safeName} CHARACTER SET {$charset} COLLATE {$collation}");

        $this->info("✔ Created MySQL database '{$database}' (charset {$charset}).");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createPgsql(array $config, string $database): int
    {
        $hostRaw = $config['host'] ?? null;
        $host = is_string($hostRaw) ? $hostRaw : '127.0.0.1';

        $portRaw = $config['port'] ?? null;
        $port = is_int($portRaw) ? $portRaw : (is_numeric($portRaw) ? (int) $portRaw : 5432);

        $encodingRaw = $config['charset'] ?? null;
        $encoding = is_string($encodingRaw) ? $encodingRaw : 'UTF8';

        if (! $this->validateIdentifier($database)) {
            $this->error("Database name '{$database}' contains invalid characters. Only letters, digits, underscores, and hyphens are allowed.");

            return self::FAILURE;
        }

        if (! $this->validateIdentifier($encoding)) {
            $this->error("Encoding '{$encoding}' contains invalid characters.");

            return self::FAILURE;
        }

        if (! $this->hasPdoDriver('pdo_pgsql')) {
            return self::FAILURE;
        }

        $pdo = $this->connect("pgsql:host={$host};port={$port};dbname=postgres", $config);

        $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        if ($statement === false) {
            throw new RuntimeException('Could not prepare the existence check.');
        }
        $statement->execute([$database]);

        if ($statement->fetchColumn() !== false) {
            $this->info("Database '{$database}' already exists. Nothing to do.");

            return self::SUCCESS;
        }

        $safeName = '"'.str_replace('"', '""', $database).'"';
        $pdo->exec("CREATE DATABASE {$safeName} ENCODING '{$encoding}'");

        $this->info("✔ Created PostgreSQL database '{$database}' (encoding {$encoding}).");

        return self::SUCCESS;
    }

    /**
     * Fail with a readable message instead of a fatal "Class PDO not found" /
     * "could not find driver" when the PDO driver is not installed.
     *
     * Checked after identifier validation so a hostile configuration is still
     * rejected on hosts without the extension.
     */
    protected function hasPdoDriver(string $extension): bool
    {
        if (extension_loaded('pdo') && extension_loaded($extension)) {
            return true;
        }

        $this->error("The [{$extension}] PHP extension is required for this connection, but it is not loaded.");

        return false;
    }

    /**
     * Ensure a SQL identifier contains only safe characters.
     * Rejects anything outside letters, digits, underscores, and hyphens.
     */
    protected function validateIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function connect(string $dsn, array $config): PDO
    {
        $usernameRaw = $config['username'] ?? null;
        $passwordRaw = $config['password'] ?? null;

        $username = is_string($usernameRaw) ? $usernameRaw : null;
        $password = is_string($passwordRaw) ? $passwordRaw : null;

        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
