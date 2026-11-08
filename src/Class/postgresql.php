<?php

namespace Puneetxp\CompilePhp\Class;

class postgresql {

    private static function uniqueClauses($table, string $prefix = ''): array {
        if (!isset($table['unique']) || !is_array($table['unique'])) {
            return [];
        }

        $clauses = [];
        foreach ($table['unique'] as $columns) {
            $columnList = is_array($columns) ? $columns : [$columns];
            if (count($columnList) === 0) {
                continue;
            }
            $columnNames = array_map(fn($col) => trim((string) $col), $columnList);
            $indexName = $table['name'] . '_' . implode('_', $columnNames) . '_unique';
            $clauses[] = $prefix . 'CONSTRAINT ' . self::q($indexName) . ' UNIQUE (' .
                implode(',', array_map(fn($col) => self::q($col), $columnNames)) . ')';
        }

        return $clauses;
    }

    public static function addattribute($tables) {
        return array_map(fn($item) => array_replace(
                        $item,
                        ["data" => array_map(fn($data) =>
                                    array_replace(
                                            $data,
                                            ["sql_attribute" => ((isset($data['default']) || isset($data['sql_attribute'])) ? ((isset($data['default']) ?
                                                (strtoupper($data['default']) === "NULL" ? "" : " NOT NULL ") . " DEFAULT " . $data["default"] :
                                                '')
                                                . " " . (isset($data["sql_attribute"]) ? $data["sql_attribute"] : '')) : " NOT NULL ")]
                                    ), $item['data'])]
                ), $tables);
    }

    /** Quote one identifier for PostgreSQL. */
    public static function q(string $name): string {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    /**
     * Convert a model's MySQL column type to PostgreSQL.
     *
     * Returns [type, extra]: `extra` holds column modifiers that were written
     * into the type in the model (e.g. "varchar(255) UNIQUE"), to append after
     * the type. Unsigned types widen where PostgreSQL's signed type is too
     * small (int unsigned -> BIGINT). Unknown types are kept as written, with
     * a warning, so a new type shows up instead of being silently dropped.
     */
    public static function convertType(string $mysqlType): array {
        $t = strtolower(trim(preg_replace('/\s+/', ' ', $mysqlType)));
        $extra = [];
        if (preg_match('/\bunique\b/', $t)) {
            $extra[] = 'UNIQUE';
            $t = trim(preg_replace('/\bunique\b/', '', $t));
        }
        $unsigned = (bool) preg_match('/\bunsigned\b/', $t);
        $t = trim(preg_replace('/\b(unsigned|zerofill)\b/', '', $t));
        $t = trim(preg_replace('/\s+/', ' ', $t));

        if (!preg_match('/^([a-z ]+?)\s*(?:\(([^)]*)\))?\s*(\[\])?$/', $t, $m)) {
            fwrite(STDERR, "postgresql: unrecognised type '$mysqlType', kept as written\n");
            return [strtoupper($t), implode(' ', $extra)];
        }
        $base = trim($m[1]);
        $args = isset($m[2]) && $m[2] !== '' ? preg_replace('/\s+/', '', $m[2]) : null;
        $array = !empty($m[3]) ? '[]' : '';

        $type = match ($base) {
            'tinyint', 'smallint', 'year' => $unsigned && $base === 'smallint' ? 'INTEGER' : 'SMALLINT',
            'mediumint' => 'INTEGER',
            'int', 'integer' => $unsigned ? 'BIGINT' : 'INTEGER',
            'bigint' => 'BIGINT',
            'serial' => 'BIGSERIAL',
            // MySQL FLOAT(M,D)/DOUBLE(M,D) are approximate types; the (M,D)
            // only rounds on display, so keep the storage class.
            'float' => 'REAL',
            'double', 'double precision', 'real' => 'DOUBLE PRECISION',
            'decimal', 'numeric', 'dec', 'fixed' => 'NUMERIC' . ($args !== null ? "($args)" : ''),
            'bool', 'boolean' => 'BOOLEAN',
            'bit' => $args === null || $args === '1' ? 'BOOLEAN' : "BIT($args)",
            'char' => 'CHAR' . ($args !== null ? "($args)" : ''),
            'varchar', 'character varying' => 'VARCHAR' . ($args !== null ? "($args)" : ''),
            'tinytext', 'text', 'mediumtext', 'longtext' => 'TEXT',
            'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob' => 'BYTEA',
            'date' => 'DATE',
            'time' => 'TIME',
            'datetime', 'timestamp' => 'TIMESTAMP' . ($args !== null ? "($args)" : ''),
            'json', 'jsonb' => 'JSONB',
            'uuid' => 'UUID',
            'vector' => 'vector' . ($args !== null ? "($args)" : ''),
            'enum', 'set' => 'TEXT',
            default => null,
        };
        if ($type === null) {
            fwrite(STDERR, "postgresql: unrecognised type '$mysqlType', kept as written\n");
            $type = strtoupper($base) . ($args !== null ? "($args)" : '');
        }
        if ($base === 'enum' && $args !== null) {
            // enum('a','b') -> TEXT plus a CHECK added by the caller.
            $extra[] = '__ENUM__(' . $m[2] . ')';
        }
        return [$type . $array, implode(' ', $extra)];
    }

    /** Backward-compatible wrapper: the PostgreSQL type only. */
    public static function convertDataType($mysqlType) {
        return self::convertType((string) $mysqlType)[0];
    }

    /**
     * Convert MySQL column attributes (NOT NULL, DEFAULT ..., PRIMARY KEY)
     * to PostgreSQL. MySQL-only keywords are removed; double-quoted string
     * defaults become single-quoted. ON UPDATE CURRENT_TIMESTAMP is removed
     * here and done by a trigger instead (see onUpdateColumns()).
     */
    public static function convertAttributes($sqlAttribute, $isId = false) {
        $attr = ' ' . trim((string) $sqlAttribute) . ' ';

        if ($isId && stripos($attr, 'PRIMARY KEY') !== false) {
            return 'PRIMARY KEY';
        }
        $attr = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(\(\d*\))?/i', '', $attr);
        $attr = preg_replace('/\b(UNSIGNED|ZEROFILL|AUTO_INCREMENT)\b/i', '', $attr);
        $attr = preg_replace("/\bCOMMENT\s+('([^'\\\\]|''|\\\\.)*'|\"([^\"\\\\]|\"\"|\\\\.)*\")/i", '', $attr);
        $attr = preg_replace('/\b(CHARACTER\s+SET|CHARSET|COLLATE)\s+\w+/i', '', $attr);
        // DEFAULT "text" -> DEFAULT 'text'
        $attr = preg_replace_callback('/\bDEFAULT\s+"((?:[^"\\\\]|\\\\.)*)"/i',
            fn($m) => "DEFAULT '" . str_replace("'", "''", stripslashes($m[1])) . "'", $attr);
        $attr = preg_replace('/\bCURRENT_TIMESTAMP\(\)/i', 'CURRENT_TIMESTAMP', $attr);

        return trim(preg_replace('/\s+/', ' ', $attr));
    }

    /** One column definition: "name" TYPE attributes. */
    public static function column(array $item): string {
        $isId = $item['name'] === 'id';
        if ($isId && stripos($item['sql_attribute'] ?? '', 'AUTO_INCREMENT') !== false) {
            return self::q('id') . ' BIGSERIAL PRIMARY KEY';
        }
        [$type, $extra] = self::convertType($item['mysql_data']);
        $check = '';
        if (preg_match('/__ENUM__\((.*)\)/', $extra, $m)) {
            $check = ' CHECK (' . self::q($item['name']) . ' IN (' . $m[1] . '))';
            $extra = trim(str_replace($m[0], '', $extra));
        }
        $attr = self::convertAttributes($item['sql_attribute'] ?? '', $isId);
        return implode(' ', array_filter([self::q($item['name']), $type, $extra, $attr], fn($p) => $p !== '')) . $check;
    }

    /** Columns that MySQL bumps with ON UPDATE CURRENT_TIMESTAMP. */
    public static function onUpdateColumns(array $table): array {
        $cols = [];
        foreach ($table['data'] as $item) {
            if (preg_match('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP/i', $item['sql_attribute'] ?? '')) {
                $cols[] = $item['name'];
            }
        }
        return array_values(array_unique($cols));
    }

    /**
     * Shared trigger function for ON UPDATE CURRENT_TIMESTAMP. Like MySQL, it
     * sets the column to now() when the row changed and the UPDATE did not set
     * the column itself.
     */
    public static function onUpdateFunction(): string {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION the_on_update_timestamp() RETURNS trigger AS $$
DECLARE col text;
BEGIN
  FOREACH col IN ARRAY TG_ARGV LOOP
    IF to_jsonb(NEW) - col IS DISTINCT FROM to_jsonb(OLD) - col
       AND to_jsonb(NEW) -> col IS NOT DISTINCT FROM to_jsonb(OLD) -> col THEN
      NEW := jsonb_populate_record(NEW, jsonb_build_object(col, CURRENT_TIMESTAMP));
    END IF;
  END LOOP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL;
    }

    public static function onUpdateTrigger(array $table): string {
        $cols = self::onUpdateColumns($table);
        if (!$cols) {
            return '';
        }
        $args = implode(', ', array_map(fn($c) => "'" . str_replace("'", "''", $c) . "'", $cols));
        $name = self::q($table['name'] . '_on_update');
        return 'DROP TRIGGER IF EXISTS ' . $name . ' ON ' . self::q($table['table']) . ";\n" .
            'CREATE TRIGGER ' . $name . ' BEFORE UPDATE ON ' . self::q($table['table']) .
            ' FOR EACH ROW EXECUTE FUNCTION the_on_update_timestamp(' . $args . ');';
    }

    public static function tablealter($table) {
        $columns = array_map(fn($item) => 'ADD COLUMN IF NOT EXISTS ' . self::column($item), $table['data']);
        $uniqueConstraints = self::uniqueClauses($table, 'ADD ');

        return 'ALTER TABLE ' . self::q($table['table']) . ' ' . implode(", ", array_merge($columns, $uniqueConstraints)) . ';';
    }

    public static function table($table) {
        $columns = [];
        $seen = [];
        foreach ($table['data'] as $item) {
            if (in_array($item['name'], $seen, true)) {
                continue;
            }
            $seen[] = $item['name'];
            $columns[] = self::column($item);
        }
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::q($table['table']) . ' (' .
            implode(", ", array_merge($columns, self::uniqueClauses($table))) . ');';
        $trigger = self::onUpdateTrigger($table);
        return $trigger === '' ? $sql : $sql . "\n" . $trigger;
    }

    /** Foreign-key columns of a table: [[column, table, key], ...]. */
    private static function foreignKeys($table): array {
        $fks = [];
        foreach ($table['data'] as $items) {
            foreach ($items['relations'] ?? [] as $value) {
                $fks[] = [$value['name'], $value['table'], $value['key']];
            }
        }
        return $fks;
    }

    /**
     * Foreign keys, each with an index on its column: MySQL creates that index
     * itself, PostgreSQL does not.
     */
    public static function migrate_table($table) {
        $sql = [];
        foreach (self::foreignKeys($table) as [$col, $refTable, $refKey]) {
            $base = $table['name'] . '_' . $col;
            $sql[] = 'CREATE INDEX IF NOT EXISTS ' . self::q($base . '_index') . ' ON ' . self::q($table['table']) . ' (' . self::q($col) . ');';
            $sql[] = 'ALTER TABLE ' . self::q($table['table']) . ' ADD CONSTRAINT ' . self::q($base . '_foreign') .
                ' FOREIGN KEY (' . self::q($col) . ') REFERENCES ' . self::q($refTable) . ' (' . self::q($refKey) . ');';
        }
        return implode("\n", $sql);
    }

    /**
     * Write the PostgreSQL schema to database/Postgresql/: Structure/<Name>.sql,
     * Relations/<Name>_relation.sql, Insert/<key>_insert.sql and Migration.sql
     * (everything, in load order). With $primary, also write
     * database/{structure,relation,insert,Migration}.sql, as the MySQL
     * generator does when MySQL is the app's database.
     */
    public static function alltable($tables, $insert = [], bool $primary = true) {
        echo "building PostgreSQL sql";
        $dir = $_ENV['dir'] . '/database/Postgresql';
        $structure = [self::onUpdateFunction()];
        $relations = [];
        foreach ($tables as $table) {
            $tableSql = self::table($table);
            $relationSql = self::migrate_table($table);
            index::createfile($dir . '/Structure/' . ucfirst($table['name']) . '.sql', $tableSql);
            index::createfile($dir . '/Relations/' . ucfirst($table['name']) . '_relation.sql', $relationSql);
            $structure[] = $tableSql;
            if ($relationSql !== '') {
                $relations[] = $relationSql;
            }
        }
        foreach ($insert as $key => $item) {
            index::createfile($dir . '/Insert/' . ucfirst($key) . '_insert.sql', $item);
        }

        $structureSql = implode("\n\n", $structure) . "\n";
        $relationSql = implode("\n\n", $relations) . "\n";
        $insertSql = implode("\n\n", $insert) . (count($insert) ? "\n" : '');
        $migration = $structureSql . "\n-- Foreign keys\n\n" . $relationSql .
            (count($insert) ? "\n-- Inserts\n\n" . $insertSql : '');

        index::createfile($dir . '/Migration.sql', $migration);
        if ($primary) {
            index::createfile($_ENV['dir'] . '/database/structure.sql', $structureSql);
            index::createfile($_ENV['dir'] . '/database/relation.sql', $relationSql);
            index::createfile($_ENV['dir'] . '/database/insert.sql', $insertSql);
            index::createfile($_ENV['dir'] . '/database/Migration.sql', $migration);
        }
        echo "     Done\n";
    }

    public $dir = [
        "structure" => [], "relations" => [], "insert" => []
    ];
    public $json_set = [];
    public $conn;

    public function __construct() {
        $this->json_set = json_decode(file_get_contents($_ENV["dir"] . '/config.json'), TRUE);
        if (isset($this->json_set['postgresql']) && $this->json_set['postgresql'] === true) {
            $env = $this->json_set['env'];
            try {
                $this->conn = new \PDO("pgsql:host=" . $env['dbhost'] . ";dbname=" . $env['dbname'], $env['dbuser'], $env['dbpwd']);
                $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } catch (\PDOException $e) {
                echo "PostgreSQL Connection failed: " . $e->getMessage() . "\n";
            }
        }
    }

    public function migrate() {
        echo "PostgreSQL migration via SQL files\n";
        echo "Run: psql -U " . $this->json_set["env"]["dbuser"] . " -d " . $this->json_set["env"]["dbname"] . " -f database/Migration.sql\n";
        echo "     Done\n";
    }

    public function sync($tables) {
        if (!$this->conn) {
            echo "Skipping PostgreSQL sync: No connection.\n";
            return;
        }

        echo "Checking for missing columns and relations (PostgreSQL)...\n";
        foreach ($tables as $tableDef) {
            $tableName = $tableDef['table'];
            
            // Sync columns
            $stmt = $this->conn->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?");
            $stmt->execute([$tableName]);
            $existingColumns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tableDef['data'] as $colDef) {
                $colName = $colDef['name'];
                if (!in_array($colName, $existingColumns)) {
                    echo "Column `$colName` missing in table `$tableName`. Adding...\n";
                    $alterSql = 'ALTER TABLE ' . self::q($tableName) . ' ADD COLUMN ' . self::column($colDef);
                    try {
                        $this->conn->exec($alterSql);
                        echo "Successfully added column `$colName` to `$tableName`.\n";
                    } catch (\PDOException $e) {
                        echo "Failed to add column `$colName` to `$tableName`: " . $e->getMessage() . "\n";
                    }
                }
            }

            // Sync relations
            $fkStmt = $this->conn->prepare("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema = 'public' AND table_name = ? AND constraint_type = 'FOREIGN KEY'");
            $fkStmt->execute([$tableName]);
            $existingFks = $fkStmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tableDef['data'] as $colDef) {
                if (isset($colDef['relations'])) {
                    foreach ($colDef['relations'] as $relDef) {
                        $constraintName = $tableDef['name'] . "_" . $colDef['name'] . "_foreign";
                        if (!in_array($constraintName, $existingFks)) {
                            echo "Relation `$constraintName` missing in table `$tableName`. Adding...\n";
                            $alterFkSql = 'ALTER TABLE ' . self::q($tableName) . ' ADD CONSTRAINT ' . self::q($constraintName) .
                                ' FOREIGN KEY (' . self::q($colDef['name']) . ') REFERENCES ' . self::q($relDef['table']) . ' (' . self::q($relDef['key']) . ')';
                            try {
                                $this->conn->exec('CREATE INDEX IF NOT EXISTS ' . self::q($tableDef['name'] . '_' . $colDef['name'] . '_index') .
                                    ' ON ' . self::q($tableName) . ' (' . self::q($colDef['name']) . ')');
                                $this->conn->exec($alterFkSql);
                                echo "Successfully added relation `$constraintName` to `$tableName`.\n";
                            } catch (\PDOException $e) {
                                echo "Failed to add relation `$constraintName` to `$tableName`: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
            }
        }
        echo "     Done\n";
    }
}
