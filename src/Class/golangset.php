<?php

namespace Puneetxp\CompilePhp\Class;

class golangset {

    public $table;
    public $json;

    public function __construct($table, $json) {
        $this->table = $table;
        $this->json = $json;
    }

    public function golangset() {
        index::templatecopy("go", "go");
        
        foreach ($this->table as $item) {
            index::createfile($_ENV['dir'] . "/go/models/" . strtolower($item['name']) . ".go", $this->golangModel($item));
        }
        index::createfile($_ENV['dir'] . "/go/routes/generated.go", $this->golangRoutes());
        echo "Golang Build\n";
    }

    /** A crud entry is a list of letters or { "can", "owner", "under", "path" } (see denoset::access). */
    private static function access($value): array {
        return array_is_list($value) ? ['can' => $value] : $value + ['can' => []];
    }

    /** Owner column of $parent for $audience, as denoset::ownerOf: no default. */
    private function ownerOf(string $parent, string $child, string $audience): string {
        foreach ($this->table as $t) {
            if ($t['name'] === $parent) {
                $entry = $t['access'][$audience] ?? $t['access']['roles'][$audience] ?? null;
                $owner = $entry === null ? null : (self::access($entry)['owner'] ?? null);
                if ($owner === null) {
                    throw new \Exception("$child.json \"$audience\" says \"under\": \"$parent\", but $parent.json has no \"owner\" for \"$audience\".");
                }
                return $owner;
            }
        }
        throw new \Exception("$child.json says \"under\": \"$parent\", but there is no $parent.json.");
    }

    /**
     * go/routes/generated.go: the CRUD routes of every model for every
     * audience, the same paths the Deno generator writes (Routes/Islogin.ts,
     * Isuper.ts, Ipublic.ts, <role>.ts), served by the_go's router.Crud.
     * Models marked hand-written in config.json ("table": {"<model>": true})
     * are left out: their routes go in routes/custom.go.
     */
    public function golangRoutes(): string {
        $groups = [];   // audience => [route lines]
        $order = [];
        $skipped = [];
        foreach ($this->table as $item) {
            if (($this->json['table'][$item['name']] ?? false) === true) {
                $skipped[] = $item['name'];
                continue;
            }
            $access = $item['access'] ?? [];
            $entries = [];
            foreach ($access['roles'] ?? [] as $role => $value) {
                $entries[$role] = $value;
            }
            foreach (['isuper', 'islogin', 'public'] as $aud) {
                if (isset($access[$aud])) {
                    $entries[$aud] = $access[$aud];
                }
            }
            foreach ($entries as $aud => $value) {
                $a = self::access($value);
                if ($aud === 'public' && (isset($a['owner']) || isset($a['under']))) {
                    throw new \Exception($item['name'] . '.json "public" cannot use "owner" or "under": public routes have no logged-in user.');
                }
                $can = implode('', array_values(array_unique($a['can'])));
                if ($can === '') {
                    continue;
                }
                $seg = '/' . ($a['path'] ?? $item['name']);
                $crud = 'Model: models.' . self::goName($item['name']) . ', Can: ' . self::goStr($can);
                if (isset($a['under'])) {
                    $parent = $a['under'];
                    $param = $parent . '_id';
                    $seg = '/' . $parent . '/:' . $param . $seg;
                    $crud .= ', Under: &router.Under{Parent: models.' . self::goName($parent) . ', Param: ' . self::goStr($param)
                        . ', Owner: ' . self::goStr($this->ownerOf($parent, $item['name'], $aud)) . '}';
                } elseif (isset($a['owner'])) {
                    $crud .= ', Owner: ' . self::goStr($a['owner']);
                }
                if (!isset($groups[$aud])) {
                    $order[] = $aud;
                }
                $groups[$aud][] = "{Path: " . self::goStr($seg) . ", Crud: &router.Crud{" . $crud . "}},";
            }
        }

        // One group of routes at an indent depth (tabs), laid out as gofmt does.
        $block = function (string $aud, int $depth) use (&$groups): string {
            $t = str_repeat("\t", $depth);
            $path = $aud === 'public' ? '/public' : '/' . $aud;
            $roles = in_array($aud, ['islogin', 'public'], true) ? '' : ', Roles: []string{' . self::goStr($aud) . '}';
            return $t . '{Path: ' . self::goStr($path) . $roles . ", Child: []router.Route{\n"
                . implode('', array_map(fn($l) => "$t\t$l\n", $groups[$aud])) . "$t}},\n";
        };
        // Same order as the Deno template's Routes/index.ts: islogin, then the
        // role groups (isuper, manager, ...), all behind login; public after.
        $login = isset($groups['islogin']) ? $block('islogin', 3) : '';
        foreach ($order as $aud) {
            if ($aud !== 'islogin' && $aud !== 'public') {
                $login .= $block($aud, 3);
            }
        }
        $out = "// Code generated by compile-php from database/Model/*.json. DO NOT EDIT.\n"
            . "// Hand-written routes go in custom.go" . ($skipped ? " (left out here, marked hand-written in config.json: " . implode(', ', $skipped) . ")" : '') . ".\n\n"
            . "package routes\n\nimport (\n\t\"app/models\"\n\n\t\"github.com/puneetxp/the_go/router\"\n)\n\n"
            . "// Generated are the CRUD routes of every model, for every audience.\n"
            . "func Generated() []router.Route {\n\treturn []router.Route{\n";
        if ($login !== '') {
            $out .= "\t\t{Islogin: true, Child: []router.Route{\n" . $login . "\t\t}},\n";
        }
        if (isset($groups['public'])) {
            $out .= $block('public', 2);
        }
        return $out . "\t}\n}\n";
    }

    public function getGoType($type) {
        switch ($type) {
            case 'number': return 'int64';
            case 'string': return 'string';
            case 'Date': return 'time.Time';
            case 'boolean': return 'bool';
            default: return 'string';
        }
    }

    private function modelForTable(string $table): ?string {
        foreach ($this->table as $t) {
            if (($t['table'] ?? null) === $table) {
                return $t['name'];
            }
        }
        return null;
    }

    /** A Go string literal (JSON quoting without escaped slashes is valid Go). */
    public static function goStr($v): string {
        return json_encode((string) $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** book_stock -> BookStock */
    public static function goName(string $name): string {
        $id = str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', $name)));
        return preg_match('/^[0-9]/', $id) ? "M$id" : $id;
    }

    private static function goStrings(array $list): string {
        return '[]string{' . implode(', ', array_map(fn($s) => self::goStr((string) $s), $list)) . '}';
    }

    /**
     * One model for github.com/puneetxp/the_go/model: the definition queries
     * start from (Book.Where(...)), registered by name so relations resolve,
     * plus a BookRow struct for typed JSON.
     */
    public function golangModel($table) {
        $name = self::goName($table['name']);
        $columns = array_values(array_unique(array_column($table['data'], 'name')));
        $fillable = [];
        foreach ($table['data'] as $col) {
            if (!isset($col['fillable']) || $col['fillable'] == 'true') {
                $fillable[] = $col['name'];
            }
        }
        // Lines are padded the way gofmt aligns them, so the output is gofmt-clean.
        $rels = [];
        foreach ($table['relations'] ?? [] as $rel => $r) {
            // Relations added in reverse (setup::table_set) carry no callback;
            // the related model is the one that owns the table.
            $model = $r['callback'] ?? $this->modelForTable($r['table']);
            if ($model === null) {
                fwrite(STDERR, "golang: {$table['name']}.$rel: no model has table {$r['table']}, relation skipped\n");
                continue;
            }
            $rels[] = [self::goStr((string) $rel) . ':', '{Model: ' . self::goStr($model)
                . ', Table: ' . self::goStr($r['table']) . ', Name: ' . self::goStr($r['name'])
                . ', Key: ' . self::goStr($r['key']) . '},'];
        }
        $keyWidth = $rels ? max(array_map(fn($r) => strlen($r[0]), $rels)) : 0;
        $relations = implode('', array_map(fn($r) => "\t\t" . str_pad($r[0], $keyWidth + 1) . $r[1] . "\n", $rels));

        $rows = [];
        $needsTime = false;
        $seen = [];
        foreach ($table['data'] as $col) {
            if (isset($seen[$col['name']])) {
                continue;
            }
            $seen[$col['name']] = true;
            $attr = $col['sql_attribute'] ?? '';
            $notNull = str_contains($attr, 'NOT NULL') || str_contains($attr, 'PRIMARY');
            // DECIMAL comes back as a string from the model layer, like every adapter.
            $type = preg_match('/^\s*(decimal|numeric)/i', $col['mysql_data'] ?? '') ? 'string' : $this->getGoType($col['datatype']);
            $needsTime = $needsTime || $type === 'time.Time';
            $rows[] = [self::goName($col['name']), ($notNull ? '' : '*') . $type, '`json:"' . $col['name'] . '"`'];
        }
        $nameWidth = $rows ? max(array_map(fn($r) => strlen($r[0]), $rows)) : 0;
        $typeWidth = $rows ? max(array_map(fn($r) => strlen($r[1]), $rows)) : 0;
        $fields = implode('', array_map(fn($r) => "\t" . str_pad($r[0], $nameWidth + 1) . str_pad($r[1], $typeWidth + 1) . $r[2] . "\n", $rows));

        return "// Code generated by compile-php from database/Model/{$table['name']}.json. DO NOT EDIT.

package models

import (
" . ($needsTime ? "\t\"time\"\n\n" : '') . "\t\"github.com/puneetxp/the_go/model\"
)

// $name: start queries here, e.g. $name.Where(model.W{\"id\": 1}).Get(ctx).
var $name = model.Define(&model.Model{
\tName:     " . self::goStr($table['name']) . ",
\tTable:    " . self::goStr($table['table']) . ",
\tColumns:  " . self::goStrings($columns) . ",
\tFillable: " . self::goStrings($fillable) . ",
\tRelations: map[string]model.Relation{
$relations\t},
})

// {$name}Row is one row of {$table['table']} as JSON.
type {$name}Row struct {
$fields}
";
    }

}
