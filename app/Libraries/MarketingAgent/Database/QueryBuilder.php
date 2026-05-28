<?php
namespace MarketingAgent\Database;

use PDO;
use PDOStatement;

/**
 * QueryBuilder — lightweight, driver-agnostic SQL query builder.
 *
 * Each instance targets a single table and chains conditions.
 * A new builder is returned from OrmModel::query($table) or
 * created standalone: new QueryBuilder($pdo, 'users')
 *
 * Supported drivers: sqlite | mysql | pgsql | sqlsrv | oracle
 */
class QueryBuilder {
    protected PDO    $pdo;
    protected Schema $schema;
    protected string $table;
    protected string $driver;

    // ─── Builder state ─────────────────────────────────────────────
    private array  $selects    = ['*'];
    private array  $joins      = [];
    private array  $wheres     = [];
    private array  $bindings   = [];
    private array  $orderBys   = [];
    private ?int   $limitVal   = null;
    private ?int   $offsetVal  = null;

    public function __construct(PDO $pdo, string $table) {
        $this->pdo    = $pdo;
        $this->schema = new Schema($pdo);
        $this->driver = $this->schema->getDriver();
        $this->table  = $table;
    }

    // ─── Fluent column/table quoting ───────────────────────────────
    public function q(string $col): string { return $this->schema->quoteColumn($col); }
    public function qt(string $tbl): string { return $this->schema->quoteTable($tbl); }

    // ─── SELECT builder ────────────────────────────────────────────
    public function select(string ...$cols): static {
        $this->selects = $cols ?: ['*'];
        return $this;
    }

    public function join(string $type, string $table, string $on): static {
        $this->joins[] = strtoupper($type) . " JOIN {$this->qt($table)} ON {$on}";
        return $this;
    }

    public function where(string $col, string $op, mixed $value): static {
        $placeholder = $this->nextPlaceholder();
        $this->wheres[]   = $this->q($col) . " {$op} {$placeholder}";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereRaw(string $rawSql, array $bindings = []): static {
        $this->wheres[]  = $rawSql;
        $this->bindings  = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function orWhere(string $col, string $op, mixed $value): static {
        $placeholder = $this->nextPlaceholder();
        $last = array_pop($this->wheres);
        $this->wheres[]   = "({$last} OR " . $this->q($col) . " {$op} {$placeholder})";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNull(string $col): static {
        $this->wheres[] = $this->q($col) . " IS NULL";
        return $this;
    }

    public function whereNotNull(string $col): static {
        $this->wheres[] = $this->q($col) . " IS NOT NULL";
        return $this;
    }

    public function orderBy(string $col, string $dir = 'ASC'): static {
        $this->orderBys[] = $this->q($col) . ' ' . strtoupper($dir);
        return $this;
    }

    public function limit(int $n): static  { $this->limitVal  = $n; return $this; }
    public function offset(int $n): static { $this->offsetVal = $n; return $this; }

    // ─── Terminal fetch methods ─────────────────────────────────────
    public function get(): array {
        $stmt = $this->execute($this->buildSelect());
        return $stmt->fetchAll();
    }

    public function first(): ?array {
        $this->limit(1);
        $stmt = $this->execute($this->buildSelect());
        $row  = $stmt->fetch();
        return $row ?: null;
    }

    public function count(): int {
        $saved = $this->selects;
        $this->selects = ['COUNT(*) AS _cnt'];
        $stmt  = $this->execute($this->buildSelect());
        $this->selects = $saved;
        return (int)($stmt->fetch()['_cnt'] ?? 0);
    }

    // ─── CRUD ──────────────────────────────────────────────────────
    /**
     * Insert a row and return the new primary-key value.
     */
    public function insert(array $data): int|string {
        $cols = array_keys($data);
        $qCols = implode(', ', array_map([$this, 'q'], $cols));
        $phs   = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->qt($this->table)} ({$qCols}) VALUES ({$phs})";
        $this->execute($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * Update matching rows. Returns number of affected rows.
     */
    public function update(array $data): int {
        $sets = implode(', ', array_map(fn($c) => $this->q($c) . ' = ?', array_keys($data)));
        $vals = array_merge(array_values($data), $this->bindings);
        $sql  = "UPDATE {$this->qt($this->table)} SET {$sets}" . $this->buildWhere();
        return (int)$this->execute($sql, $vals)->rowCount();
    }

    /**
     * Delete matching rows. Returns number of affected rows.
     */
    public function delete(): int {
        $sql = "DELETE FROM {$this->qt($this->table)}" . $this->buildWhere();
        return (int)$this->execute($sql, $this->bindings)->rowCount();
    }

    /**
     * Upsert — delegates to Schema::upsert().
     */
    public function upsert(array $data, array $uniqueCols): void {
        $this->schema->upsert($this->table, $data, $uniqueCols);
    }

    /**
     * Insert if the row does not exist yet (checked via uniqueCols).
     */
    public function insertIgnore(array $data, array $uniqueCols): void {
        // Check existence first (works on all drivers)
        $check = new static($this->pdo, $this->table);
        foreach ($uniqueCols as $col) {
            if (array_key_exists($col, $data)) {
                $check->where($col, '=', $data[$col]);
            }
        }
        if ($check->count() === 0) {
            $this->insert($data);
        }
    }

    // ─── Raw execute (for callers that need full control) ──────────
    public function raw(string $sql, array $bindings = []): PDOStatement {
        return $this->execute($sql, $bindings);
    }

    // ─── Internal helpers ──────────────────────────────────────────
    private function buildSelect(): string {
        $cols = implode(', ', $this->selects);
        $sql  = "SELECT {$cols} FROM {$this->qt($this->table)}";
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->buildWhere();
        if ($this->orderBys) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }
        $sql .= $this->buildLimit();
        return $sql;
    }

    private function buildWhere(): string {
        if (!$this->wheres) return '';
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    private function buildLimit(): string {
        if ($this->limitVal === null) return '';
        if ($this->driver === 'sqlsrv') {
            // SQL Server uses FETCH NEXT / OFFSET
            $offset = $this->offsetVal ?? 0;
            return " ORDER BY (SELECT NULL) OFFSET {$offset} ROWS FETCH NEXT {$this->limitVal} ROWS ONLY";
        }
        if ($this->driver === 'oracle') {
            // Oracle 12c+ FETCH syntax
            $offset = $this->offsetVal ?? 0;
            return " OFFSET {$offset} ROWS FETCH NEXT {$this->limitVal} ROWS ONLY";
        }
        $sql = " LIMIT {$this->limitVal}";
        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }
        return $sql;
    }

    /**
     * For Oracle/SQL Server named placeholders; everyone else uses ?.
     * We keep it simple: use positional ? for all drivers supported by PDO.
     */
    private function nextPlaceholder(): string {
        return '?';
    }

    private function execute(string $sql, array $bindings = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings ?: ($this->bindings ?: []));
        return $stmt;
    }

    // ─── Reset for re-use ──────────────────────────────────────────
    public function reset(): static {
        $this->selects   = ['*'];
        $this->joins     = [];
        $this->wheres    = [];
        $this->bindings  = [];
        $this->orderBys  = [];
        $this->limitVal  = null;
        $this->offsetVal = null;
        return $this;
    }

    // ─── Accessors ─────────────────────────────────────────────────
    public function getSchema(): Schema { return $this->schema; }
    public function getPdo(): PDO       { return $this->pdo; }
    public function getDriver(): string { return $this->driver; }
}
