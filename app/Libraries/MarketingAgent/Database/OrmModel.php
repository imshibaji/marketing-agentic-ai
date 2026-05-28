<?php
namespace MarketingAgent\Database;

use PDO;

/**
 * OrmModel — Active Record base class.
 *
 * Concrete model classes extend this and set:
 *   protected static string $tableName  — e.g. 'users'
 *   protected static string $primaryKey — default 'id'
 *
 * The class provides:
 *  - Static factory:  MyModel::table($pdo)  → QueryBuilder
 *  - Static helpers:  find, findBy, all, create, updateWhere, deleteWhere
 *  - Instance CRUD:   save(), delete()
 *
 * All query-building delegates to QueryBuilder; all schema helpers
 * delegate to Schema, both of which are driver-agnostic.
 */
abstract class OrmModel {
    protected static string $tableName  = '';
    protected static string $primaryKey = 'id';

    // Runtime PDO is injected once per request, shared across all instances.
    private static ?PDO $staticPdo = null;

    // Instance attributes
    protected array $attributes = [];
    protected bool  $exists     = false;   // true after fetch / save

    // ─── PDO injection (called by DatabaseService after connect) ───
    public static function setPdo(PDO $pdo): void {
        static::$staticPdo = $pdo;
    }

    public static function getPdo(): PDO {
        if (static::$staticPdo === null) {
            throw new \RuntimeException('OrmModel: PDO has not been set. Call OrmModel::setPdo($pdo) first.');
        }
        return static::$staticPdo;
    }

    // ─── QueryBuilder factory ──────────────────────────────────────
    /**
     * Returns a fresh QueryBuilder for this model's table.
     * Usage: MyModel::table()->where('id','=',1)->first()
     */
    public static function table(?PDO $pdo = null): QueryBuilder {
        return new QueryBuilder($pdo ?? static::getPdo(), static::$tableName);
    }

    /**
     * Returns the Schema helper for this model's connection.
     */
    public static function schema(?PDO $pdo = null): Schema {
        return new Schema($pdo ?? static::getPdo());
    }

    // ─── Static CRUD helpers ───────────────────────────────────────
    /** Fetch all rows as plain associative arrays. */
    public static function all(): array {
        return static::table()->get();
    }

    /** Find one row by primary key. Returns null if not found. */
    public static function find(int|string $id): ?array {
        return static::table()->where(static::$primaryKey, '=', $id)->first();
    }

    /** Find one row matching a given column=value. */
    public static function findBy(string $col, mixed $val): ?array {
        return static::table()->where($col, '=', $val)->first();
    }

    /** Count rows in the table. */
    public static function count(): int {
        return static::table()->count();
    }

    /**
     * Insert a new row and return the new primary-key value.
     */
    public static function create(array $data): int|string {
        return static::table()->insert($data);
    }

    /**
     * Update rows where $col = $val with the given $data.
     */
    public static function updateWhere(string $col, mixed $val, array $data): int {
        return static::table()->where($col, '=', $val)->update($data);
    }

    /**
     * Delete rows where $col = $val.
     */
    public static function deleteWhere(string $col, mixed $val): int {
        return static::table()->where($col, '=', $val)->delete();
    }

    /**
     * Insert or update based on unique keys.
     */
    public static function upsert(array $data, array $uniqueKeys): void {
        static::table()->upsert($data, $uniqueKeys);
    }

    /**
     * Insert only if row does not already exist (checked by uniqueKeys).
     */
    public static function insertIgnore(array $data, array $uniqueKeys): void {
        static::table()->insertIgnore($data, $uniqueKeys);
    }

    // ─── Instance helpers ──────────────────────────────────────────
    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
        $this->exists     = !empty($attributes[static::$primaryKey]);
    }

    public function __get(string $name): mixed {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool {
        return isset($this->attributes[$name]);
    }

    public function toArray(): array {
        return $this->attributes;
    }

    /**
     * Persist: INSERT if new, UPDATE if already persisted.
     */
    public function save(): bool {
        if ($this->exists) {
            $pk = static::$primaryKey;
            static::table()->where($pk, '=', $this->attributes[$pk])
                ->update($this->attributes);
        } else {
            $newId = static::table()->insert($this->attributes);
            $this->attributes[static::$primaryKey] = $newId;
            $this->exists = true;
        }
        return true;
    }

    /**
     * Delete this model instance from the database.
     */
    public function delete(): bool {
        if (!$this->exists) return false;
        $pk = static::$primaryKey;
        static::table()->where($pk, '=', $this->attributes[$pk])->delete();
        $this->exists = false;
        return true;
    }
}
