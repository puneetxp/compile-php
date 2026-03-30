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
            $clauses[] = $prefix . 'CONSTRAINT ' . $indexName . ' UNIQUE (' .
                implode(',', array_map(fn($col) => '"' . $col . '"', $columnNames)) . ')';
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

    /**
     * Convert MySQL data types to PostgreSQL data types
     */
    public static function convertDataType($mysqlType) {
        $mysqlType = strtolower(trim($mysqlType));
        
        // Handle types with parameters
        if (preg_match('/^varchar\((\d+)\)$/i', $mysqlType, $matches)) {
            return "VARCHAR(" . $matches[1] . ")";
        }
        if (preg_match('/^decimal\((\d+),(\d+)\)$/i', $mysqlType, $matches)) {
            return "DECIMAL(" . $matches[1] . "," . $matches[2] . ")";
        }
        if (preg_match('/^vector\((\d+)\)$/i', $mysqlType, $matches)) {
            return "vector(" . $matches[1] . ")";
        }
        
        // Map MySQL types to PostgreSQL types
        $typeMap = [
            'bigint' => 'BIGINT',
            'bigint unsigned' => 'BIGINT',
            'int' => 'INTEGER',
            'integer' => 'INTEGER',
            'tinyint(1)' => 'SMALLINT',
            'timestamp' => 'TIMESTAMP',
            'date' => 'DATE',
            'text' => 'TEXT',
            'text[]' => 'TEXT[]',
            'longtext' => 'TEXT',
            'jsonb' => 'JSONB',
            'json' => 'JSONB',
            'boolean' => 'BOOLEAN',
        ];
        
        return $typeMap[$mysqlType] ?? strtoupper($mysqlType);
    }

    /**
     * Convert MySQL attributes to PostgreSQL attributes
     */
    public static function convertAttributes($sqlAttribute, $isId = false) {
        $attr = trim($sqlAttribute);
        
        // Remove MySQL-specific keywords
        $attr = str_replace('UNSIGNED', '', $attr);
        $attr = str_replace('AUTO_INCREMENT', '', $attr);
        
        // Remove MySQL COMMENT syntax (COMMENT 'text' or COMMENT "text")
        $attr = preg_replace("/COMMENT\s+['\"][^'\"]*['\"]/i", '', $attr);
        
        // Handle PRIMARY KEY for id field
        if ($isId && strpos($attr, 'PRIMARY KEY') !== false) {
            return 'PRIMARY KEY';
        }
        
        // Handle DEFAULT CURRENT_TIMESTAMP
        $attr = str_replace('DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'DEFAULT CURRENT_TIMESTAMP', $attr);
        
        // Clean up extra spaces
        $attr = preg_replace('/\s+/', ' ', $attr);
        
        return trim($attr);
    }

    public static function tablealter($table) {
        $columns = array_map(
            fn($item) =>
            "ADD COLUMN IF NOT EXISTS \"" . $item['name'] . "\" " .
            self::convertDataType($item['mysql_data']) . ' ' .
            self::convertAttributes($item['sql_attribute']),
            $table['data']
        );
        $uniqueConstraints = self::uniqueClauses($table, 'ADD ');
        
        return 'ALTER TABLE ' . $table['table'] . ' ' . implode(", ", array_merge($columns, $uniqueConstraints)) . ';';
    }

    public static function table($table) {
        $columns = [];
        $seenColumns = []; // Track column names to avoid duplicates
        
        foreach ($table['data'] as $item) {
            // Skip duplicate column names
            if (in_array($item['name'], $seenColumns)) {
                continue;
            }
            $seenColumns[] = $item['name'];
            
            $isId = ($item['name'] === 'id');
            $dataType = self::convertDataType($item['mysql_data']);
            $attributes = self::convertAttributes($item['sql_attribute'], $isId);
            
            // Special handling for id field with auto-increment
            if ($isId && strpos($item['sql_attribute'], 'AUTO_INCREMENT') !== false) {
                $columns[] = "\"" . $item['name'] . "\" BIGSERIAL PRIMARY KEY";
            } else {
                $columns[] = "\"" . $item['name'] . "\" " . $dataType . ' ' . $attributes;
            }
        }
        $uniqueConstraints = self::uniqueClauses($table);
        
        return 'CREATE TABLE IF NOT EXISTS ' . $table['table'] . ' (' . implode(", ", array_merge($columns, $uniqueConstraints)) . ');';
    }

    public static function migrate_table($table) {
        $relation_data = [];
        foreach ($table['data'] as $items) {
            if (isset($items['relations'])) {
                $relation_data[] = $items;
            }
        }
        
        if (count($relation_data) > 0) {
            $constraints = [];
            
            foreach ($relation_data as $items) {
                foreach ($items['relations'] as $value) {
                    $constraintName = $table['name'] . "_" . $value['name'] . "_foreign";
                    $constraints[] = "ALTER TABLE " . $table['table'] . 
                                   " ADD CONSTRAINT " . $constraintName . 
                                   " FOREIGN KEY (\"" . $value['name'] . "\") " .
                                   "REFERENCES " . $value['table'] . " (\"" . $value['key'] . "\");";
                }
            }
            
            return implode("\n", $constraints);
        }
        
        return '';
    }

    public static function alltable($tables, $insert = []) {
        echo "building PostgreSQL sql";
        
        // Create structure.sql with all tables
        $allTables = [];
        foreach ($tables as $table) {
            $allTables[] = self::table($table);
        }
        $structureFile = index::fopen_dir($_ENV['dir'] . "/database/structure.sql");
        fwrite($structureFile, implode("\n\n", $allTables));
        
        // Create relation.sql with all foreign keys
        $allRelations = [];
        foreach ($tables as $table) {
            $relation = self::migrate_table($table);
            if (!empty($relation)) {
                $allRelations[] = $relation;
            }
        }
        $relationFile = index::fopen_dir($_ENV['dir'] . "/database/relation.sql");
        fwrite($relationFile, implode("\n\n", $allRelations));
        
        // Create insert.sql for inserts
        $insertFile = index::fopen_dir($_ENV['dir'] . "/database/insert.sql");
        if (count($insert) > 0) {
            fwrite($insertFile, implode("\n\n", $insert));
        } else {
            fwrite($insertFile, "");
        }
        
        // Create Migration.sql with everything
        $migrationFile = index::fopen_dir($_ENV['dir'] . "/database/Migration.sql");
        fwrite($migrationFile, implode("\n\n", $allTables));
        if (count($allRelations) > 0) {
            fwrite($migrationFile, "\n\n-- Foreign Keys\n\n" . implode("\n\n", $allRelations));
        }
        if (count($insert) > 0) {
            fwrite($migrationFile, "\n\n-- Inserts\n\n" . implode("\n\n", $insert));
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
                    $dataType = self::convertDataType($colDef['mysql_data']);
                    $attr = self::convertAttributes($colDef['sql_attribute'], $colName === 'id');
                    $alterSql = "ALTER TABLE \"$tableName\" ADD COLUMN \"$colName\" $dataType $attr";
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
                            $alterFkSql = "ALTER TABLE \"$tableName\" ADD CONSTRAINT \"$constraintName\" FOREIGN KEY (\"" . $colDef['name'] . "\") REFERENCES \"" . $relDef['table'] . "\" (\"" . $relDef['key'] . "\")";
                            try {
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
