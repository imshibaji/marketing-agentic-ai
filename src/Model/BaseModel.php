<?php
namespace MarketingAgent\Model;

use PDO;
use MarketingAgent\Database\OrmModel;
use MarketingAgent\Database\Schema;
use MarketingAgent\Database\QueryBuilder;

/**
 * BaseModel — bridge between legacy Model classes and the new ORM engine.
 *
 * All model classes that previously held a raw `PDO $pdo` property
 * continue to work via the `$this->pdo` accessor; they also gain
 * access to `$this->schema` (Schema builder) and `$this->qb()`
 * (a fresh QueryBuilder for their own table).
 */
abstract class BaseModel extends OrmModel {
    /** Raw PDO connection — kept for backward compatibility with existing query code. */
    protected PDO $pdo;

    /** Schema builder pre-wired to the connection. */
    protected Schema $schema;

    public function __construct(PDO $pdo) {
        $this->pdo    = $pdo;
        $this->schema = new Schema($pdo);
        // Register PDO on the static ORM layer so static helpers work too.
        static::setPdo($pdo);
    }

    /**
     * Return a fresh QueryBuilder scoped to this model's table.
     * Concrete models set static::$tableName.
     */
    protected function qb(): QueryBuilder {
        return new QueryBuilder($this->pdo, static::$tableName);
    }

    /**
     * Convenience: quote a column/table name for the current driver.
     */
    protected function q(string $col): string {
        return $this->schema->quoteColumn($col);
    }
}
