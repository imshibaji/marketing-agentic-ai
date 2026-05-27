<?php
namespace MarketingAgent\Database;

use PDO;

class SafePdo extends PDO {
    private string $driver;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
        parent::__construct($dsn, $username, $password, $options);
        $this->driver = strtolower($this->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    private function translate(string $sql): string {
        if ($this->driver === 'sqlite') {
            return $sql;
        }

        // 1. Translate AUTOINCREMENT syntax
        // SQLite: INTEGER PRIMARY KEY AUTOINCREMENT
        // MySQL: INT AUTO_INCREMENT PRIMARY KEY
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT\b/i', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);

        // 2. Translate TEXT UNIQUE and TEXT PRIMARY KEY since MySQL requires length for key columns
        $sql = preg_replace('/\bTEXT\s+PRIMARY\s+KEY\b/i', 'VARCHAR(255) PRIMARY KEY', $sql);
        $sql = preg_replace('/\bTEXT\s+UNIQUE\b/i', 'VARCHAR(255) UNIQUE', $sql);

        // 3. Translate TEXT column default values to VARCHAR(255) (MySQL doesn't allow defaults on TEXT)
        $sql = preg_replace('/\bTEXT(\s+NOT\s+NULL)?\s+DEFAULT/i', 'VARCHAR(255)$1 DEFAULT', $sql);

        // 4. Translate INSERT OR IGNORE to INSERT IGNORE for MySQL
        $sql = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT IGNORE', $sql);

        // 4. Translate INSERT OR REPLACE to REPLACE for MySQL
        $sql = preg_replace('/\bINSERT\s+OR\s+REPLACE\b/i', 'REPLACE', $sql);

        return $sql;
    }

    public function exec(string $statement): int|false {
        return parent::exec($this->translate($statement));
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false {
        return parent::prepare($this->translate($query), $options);
    }

    public function query(string $statement, ?int $mode = null, ...$args): \PDOStatement|false {
        $translated = $this->translate($statement);
        if ($mode === null) {
            return parent::query($translated);
        }
        return parent::query($translated, $mode, ...$args);
    }
}
