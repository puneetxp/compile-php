<?php
// Run: php tests/postgresql_test.php
// Checks the MySQL -> PostgreSQL column conversion used for generated DDL.

require __DIR__ . '/../src/Class/index.php';
require __DIR__ . '/../src/Class/postgresql.php';

use Puneetxp\CompilePhp\Class\postgresql;

$failures = 0;
function check(string $label, $got, $want): void {
    global $failures;
    if ($got === $want) {
        echo "ok   $label\n";
    } else {
        $failures++;
        echo "FAIL $label\n     got:  " . var_export($got, true) . "\n     want: " . var_export($want, true) . "\n";
    }
}

$types = [
    'varchar(255)' => ['VARCHAR(255)', ''],
    'varchar(255) UNIQUE' => ['VARCHAR(255)', 'UNIQUE'],
    'varchar(255)  UNIQUE' => ['VARCHAR(255)', 'UNIQUE'],
    'VARCHAR(255)' => ['VARCHAR(255)', ''],
    'int' => ['INTEGER', ''],
    'int UNSIGNED' => ['BIGINT', ''],
    'bigint' => ['BIGINT', ''],
    'bigint UNSIGNED' => ['BIGINT', ''],
    'tinyint' => ['SMALLINT', ''],
    'TINYINT(1)' => ['SMALLINT', ''],
    'float(8,2)' => ['REAL', ''],
    'DOUBLE(8,2)' => ['DOUBLE PRECISION', ''],
    'decimal(12,3)' => ['NUMERIC(12,3)', ''],
    'decimal(12, 2)' => ['NUMERIC(12,2)', ''],
    'LONGTEXT' => ['TEXT', ''],
    'Longtext' => ['TEXT', ''],
    'text[]' => ['TEXT[]', ''],
    'timestamp' => ['TIMESTAMP', ''],
    'datetime' => ['TIMESTAMP', ''],
    'date' => ['DATE', ''],
    'json' => ['JSONB', ''],
    'boolean' => ['BOOLEAN', ''],
    'longblob' => ['BYTEA', ''],
    'vector(1536)' => ['vector(1536)', ''],
];
foreach ($types as $in => $want) {
    check("type $in", postgresql::convertType($in), $want);
}

$attrs = [
    ['UNSIGNED PRIMARY KEY AUTO_INCREMENT', true, 'PRIMARY KEY'],
    ['DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL', false, 'DEFAULT CURRENT_TIMESTAMP NOT NULL'],
    [" NOT NULL  DEFAULT 'draft'", false, "NOT NULL DEFAULT 'draft'"],
    [' NOT NULL DEFAULT "cash" COMMENT "how it was paid"', false, "NOT NULL DEFAULT 'cash'"],
    ["DEFAULT \"it's\"", false, "DEFAULT 'it''s'"],
    [' DEFAULT NULL', false, 'DEFAULT NULL'],
    ['NOT NULL CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', false, 'NOT NULL'],
];
foreach ($attrs as [$in, $isId, $want]) {
    check("attr $in", postgresql::convertAttributes($in, $isId), $want);
}

check('id column', postgresql::column(['name' => 'id', 'mysql_data' => 'bigint', 'sql_attribute' => 'UNSIGNED PRIMARY KEY AUTO_INCREMENT']), '"id" BIGSERIAL PRIMARY KEY');
check('unique in type', postgresql::column(['name' => 'email', 'mysql_data' => 'varchar(255) UNIQUE', 'sql_attribute' => ' NOT NULL ']), '"email" VARCHAR(255) UNIQUE NOT NULL');
check('enum', postgresql::column(['name' => 'kind', 'mysql_data' => "enum('buy','sell')", 'sql_attribute' => " NOT NULL DEFAULT 'buy'"]), "\"kind\" TEXT NOT NULL DEFAULT 'buy' CHECK (\"kind\" IN ('buy','sell'))");
check('reserved name quoted', postgresql::column(['name' => 'order', 'mysql_data' => 'int', 'sql_attribute' => ' NOT NULL DEFAULT 0']), '"order" INTEGER NOT NULL DEFAULT 0');

$table = [
    'name' => 'order', 'table' => 'order',
    'data' => [
        ['name' => 'id', 'mysql_data' => 'bigint', 'sql_attribute' => 'UNSIGNED PRIMARY KEY AUTO_INCREMENT'],
        ['name' => 'updated_at', 'mysql_data' => 'timestamp', 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL'],
        ['name' => 'user_id', 'mysql_data' => 'bigint UNSIGNED', 'sql_attribute' => ' NOT NULL ', 'relations' => ['user' => ['name' => 'user_id', 'table' => 'users', 'key' => 'id']]],
    ],
    'unique' => [['user_id', 'updated_at']],
];
check('table', postgresql::table($table),
    'CREATE TABLE IF NOT EXISTS "order" ("id" BIGSERIAL PRIMARY KEY, "updated_at" TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, "user_id" BIGINT NOT NULL, CONSTRAINT "order_user_id_updated_at_unique" UNIQUE ("user_id","updated_at"));' . "\n" .
    'DROP TRIGGER IF EXISTS "order_on_update" ON "order";' . "\n" .
    'CREATE TRIGGER "order_on_update" BEFORE UPDATE ON "order" FOR EACH ROW EXECUTE FUNCTION the_on_update_timestamp(\'updated_at\');');
check('relations', postgresql::migrate_table($table),
    'CREATE INDEX IF NOT EXISTS "order_user_id_index" ON "order" ("user_id");' . "\n" .
    'ALTER TABLE "order" ADD CONSTRAINT "order_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id");');

echo $failures ? "\n$failures failed\n" : "\nall passed\n";
exit($failures ? 1 : 0);
