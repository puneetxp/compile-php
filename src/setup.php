<?php

namespace Puneetxp\CompilePhp;

use Puneetxp\CompilePhp\Class\{
    mysql,
    index,
    denoset,
    phpset,
    solidset,
    vueset,
    angularset
};
use Puneetxp\CompilePhp\compile\compilephp;

class setup {

    public $pattern_route = '/\$route.*?;/';
    public $pattern_use_only = '/use.*?\w;/';
    public $pattern_use_multple = "/use (.*?){(.*?)};/";
    public $route_use_single = '';
    public $route_use_array = [];
    public $route_use_multiple = '';
    public $route_app = ' (new Route())';
    public $json_set;
    public $files;
    public $table;
    public $roles = ['isuper'];

    public function __construct($dir = __DIR__ . "/../../") {
        $_ENV["dir"] = $dir;
        $this->route_use_array['The\\'] = ["Route"];
        $this->json_set = json_decode(file_get_contents($_ENV["dir"] . '/config.json'), TRUE);
        foreach (glob($_ENV["dir"] . "/database/Model/*.json") as $file) {
            $filename = preg_replace("/.*.\/(.*).json/", "$1", $file);
            $j = json_decode(file_get_contents($file), TRUE);
            $this->files[$filename] = $j;
        }
    }

    public function config() {
        $this->table_set();
        //deno
        if (in_array('deno', $this->json_set['back-end'])) {
            $this->deno_set($this->table, $this->json_set);
            echo "Deno Build\n";
        }
        //php
        if (in_array('php', $this->json_set['back-end'])) {
            $this->php_set();
        }
        //angular
        if (in_array('angular', $this->json_set['front-end'])) {
            $this->angular_set();
        }
        //solid
        if (in_array('solidjs', $this->json_set['front-end'])) {
            $this->solidjs_set();
            echo "Solid Build\n";
        }
        //vue
        if (in_array('vuets', $this->json_set['front-end'])) {
            $this->vuejs_set();
            echo "Vue Build\n";
        }
        //write mysql
        $this->write();
        return $this;
    }

    public function add_table() {
        $j = [];
        foreach (glob($_ENV["dir"] . "/database/Model/Additional/*.json") as $file) {
            $j[] = json_decode(file_get_contents($file), true);
        }
        $x = mysql::addattribute($j);
        foreach ($x as $j) {
            index::createfile($_ENV['dir'] . "/database/" . ucfirst('mysql/') . ucfirst('alter/') . ucfirst($j["name"]) . '_alter.sql', mysql::tablealter($j));
            foreach ($j["data"] as $item) {
                $this->table[$j["name"]]["data"][] = $item;
            }
        }
        return $this;
    }

    public function resetTable() {
        $x = [];
        for ($i = 0; $i < count($this->table); ++$i) {
            $x[$this->table[$i]['name']] = $this->table[$i];
        }
        $this->table = $x;
    }

    public function table_set() {
        foreach ($this->files as $key => $item) {
            if (isset($item['crud']['roles'])) {
                if (is_array($item['crud']['roles'])) {
                    $this->roles = array_merge(array_keys($item['crud']['roles']), $this->roles);
                }
            }
            $this->table[] = index::table_set($item, array_values($this->files))->table;
        }
        $this->roles = array_filter(array_unique($this->roles), fn($role) => !($role == "*" || $role == "-"));
        for ($i = 0; $i < count($this->table); ++$i) {
            if (isset($this->table[$i]['relations']) && count($this->table[$i]['relations']) > 0) {
                foreach ($this->table[$i]['relations'] as $key => $items) {
                    for ($t = 0; $t < count($this->table); ++$t) {
                        if ($this->table[$t]['name'] == $key) {
                            $this->table[$t]['relations'][$this->table[$i]['name']] = ['table' => $this->table[$i]['table'], 'name' => $items['key'], 'key' => $items['name']];
                        }
                    }
                }
            }
        }
        $x = [];
        $this->resetTable();
        $this->table = mysql::addattribute(array_values($this->table));
        $this->resetTable();
        $this->add_table();
        echo "Tables Build\n";
        foreach ($this->table as $item) {
            isset($this->json_set['table'][$item['name']]) ? '' : $this->json_set['table'][$item['name']] = false;
        }
        return $this;
    }

    public function php_set() {
        new phpset($this->table, $this->json_set);
        echo "PHP Build\n";
        return $this;
    }
    public function deno_set($param) {
        (new denoset($this->table, $this->json_set, param: $param))->denoset();
        echo "Deno Build\n";
        return $this;
    }

    public function angular_set() {
        (new angularset($this->table, $this->json_set))->angularset();
        echo "Angular Build\n";
        return $this;
    }

    public function vuejs_set() {
        new vueset($this->table, $this->json_set);
        echo "Angular Build\n";
        return $this;
    }

    public function solidjs_set() {
        new solidset($this->table, $this->json_set);
        echo "Solid Build\n";
        return $this;
    }

    public function write() {
        foreach ($this->route_use_array as $key => $value) {
            $this->route_use_multiple .= "use $key{" . implode(',', array_unique($value)) . "}; ";
        }
        mysql::alltable($this->table, ["roles" => 'INSERT INTO roles (name) VALUES ("' . implode('"),("', array_values(array_unique($this->roles))) . '");']);
        $migration_sql = '';
        $migration_relation = '';
        $migration_insert = '';
        foreach ($this->table as $item) {
            $migration_sql .= file_get_contents($_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('structure/') . ucfirst($item['name']) . '.sql', 'TRUE');
            $migration_relation .= file_get_contents($_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('relations/') . ucfirst($item['name']) . '_relation.sql', 'TRUE');
            if (is_file($_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('insert/') . ucfirst($item['name']) . '_insert.sql')) {
                $migration_insert .= file_get_contents($_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('insert/') . ucfirst($item['name']) . '_insert.sql', 'TRUE');
            }
        }
        file_put_contents($_ENV["dir"] . '/database/structure.sql', ($migration_sql));
        file_put_contents($_ENV["dir"] . '/database/relation.sql', ($migration_relation));
        file_put_contents($_ENV["dir"] . '/database/insert.sql', ($migration_insert));
        file_put_contents($_ENV["dir"] . '/database/Migration.sql', ($migration_sql . ' ' . $migration_relation . ' ' . $migration_insert));
        file_put_contents($_ENV["dir"] . '/config.json', json_encode($this->json_set, JSON_PRETTY_PRINT));
        return $this;
    }
   
    public function migrate() {
        (new mysql())->migrate();
        return $this;
    }

    public function migratealter() {
        mysql::migrateAlter();
        return $this;
    }
}
