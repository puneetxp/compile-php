<?php

namespace Puneetxp\CompilePhp\Class;

use mysqli;

class mysql {

    public static function addattribute($tables) {
        return array_map(fn($item) => array_replace(
                        $item,
                        ["data" => array_map(fn($data) =>
                                    array_replace(
                                            $data,
                                            ["sql_attribute" => ((isset($data['default']) || isset($data['sql_attribute'])) ? ((isset($data['default']) ?
                                                ($data['default'] === "NULL" ? "" : " NOT NULL ") . " DEFAULT " . $data["default"] :
                                                '')
                                                . " " . (isset($data["sql_attribute"]) ? $data["sql_attribute"] : '')) : " NOT NULL ")]
                                    ), $item['data'])]
                ), $tables);
    }

    public static function tablealter($table) {
        return 'ALTER TABLE ' . $table['table'] . ' ' .
                implode(",", array_map(
                                fn($item) =>
                                "ADD COLUMN IF NOT EXISTS `" . $item['name'] . "`" . ' ' .
                                $item['mysql_data'] . ' ' .
                                $item['sql_attribute'] . " ",
                                $table['data']
                        )) . ';';
    }

    public static function table($table) {
        return 'CREATE TABLE ' . $table['table'] . '(' .
                implode(",", array_map(
                                fn($item) =>
                                "`" . $item['name'] . "`" . ' ' .
                                $item['mysql_data'] . ' ' .
                                $item['sql_attribute'],
                                $table['data']
                        )) . ')ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8;';
    }

    public static function migrate_table($table) {
        $relation_data = [];
        foreach ($table['data'] as $items) {
            if (isset($items['relations'])) {
                $relation_data[] = $items;
            }
        }
        if (count($relation_data) > 0) {
            $alter = " ALTER TABLE " . $table['table'];
            $relation_key_sql = $alter;
            $relation_constrain_sql = $alter;
            foreach ($relation_data as $id => $items) {
                foreach ($items['relations'] as $value) {
                    $relation_key_sql .= " ADD KEY " . $table['name'] . "_" . $value['name'] . "_foreign (`" . $value['name'] . "`)";
                    $relation_constrain_sql .= " ADD CONSTRAINT " . $table['name'] . "_" . $value['name'] . "_foreign  FOREIGN KEY  (`" . $value['name'] . "`) REFERENCES " . $value['table'] . " (`" . $value['key'] . "`)";
                }
                if ((int) $id + 1 == count($relation_data)) {
                    $relation_key_sql .= ';';
                    $relation_constrain_sql .= ';';
                } else {
                    $relation_key_sql .= ',';
                    $relation_constrain_sql .= ',';
                }
            }
            return $relation_key_sql . $relation_constrain_sql;
        }
    }

    public static function alltable($tables, $insert = []) {
        echo "building sql";
        foreach ($tables as $table) {
            $mysql_write = mysql::table($table);
            $mysql_relation = mysql::migrate_table($table);
            $mysql = index::fopen_dir($_ENV['dir'] . "/database/" . ucfirst('mysql/') . ucfirst('structure/') . ucfirst($table['name']) . '.sql');
            $mysql_relation_file = index::fopen_dir($_ENV['dir'] . "/database/" . ucfirst('mysql/') . ucfirst('relations/') . ucfirst($table['name']) . '_relation.sql');
            fwrite($mysql_relation_file, $mysql_relation);
            fwrite($mysql, $mysql_write);
        }
        foreach ($insert as $key => $item) {
            $mysql_relation_file = index::createfile($_ENV['dir'] . "/database/" . ucfirst('mysql/') . ucfirst('insert/') . ucfirst($key) . '_insert.sql', $item);
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
        $this->dir["structure"] = $_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('structure');
        $this->dir["relations"] = $_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('relations');
        $this->dir["insert"] = $_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('insert');
        $conn = new mysqli($this->json_set["env"]["dbhost"], $this->json_set["env"]["dbuser"], $this->json_set["env"]["dbpwd"]);
        if ($this->json_set["fresh"] == true) {
            $conn->query("CREATE DATABASE IF NOT EXISTS " . $this->json_set["env"]["dbname"] . ";");
            $conn->query("Drop DATABASE " . $this->json_set["env"]["dbname"] . ";");
        }
        $conn->query("CREATE DATABASE IF NOT EXISTS " . $this->json_set["env"]["dbname"] . ";");
        $conn->select_db($this->json_set["env"]["dbname"]);
        $this->conn = $conn;
        if (mysqli_connect_error()) {
            exit('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
        }
    }

    public function migrate() {
        echo "migrate sql\n";
        foreach ($this->dir as $key => $dir) {
            if ($this->json_set["fresh"] == true)
                ;
            echo "Migrating " . $key . "\n";
            foreach (index::scanfullfolder($dir) as $file) {
                echo $file . "\n";
                $x = file_get_contents($file);
                foreach (explode(";", $x) as $xx) {
                    if ($xx !== "") {
                        $this->conn->query($xx);
                    }
                }
            }
        }
        echo "     Done\n";
    }

    public static function migrateAlter() {
        $json_set = json_decode(file_get_contents($_ENV["dir"] . "/config.json"), TRUE);
        $dir["alter"] = $_ENV["dir"] . "/database/" . ucfirst('mysql/') . ucfirst('alter');
        $conn = new mysqli($json_set["env"]["dbhost"], $json_set["env"]["dbuser"], $json_set["env"]["dbpwd"]);
        $conn->select_db($json_set["env"]["dbname"]);
        echo "migrate Alter sql\n";
        foreach (index::scanfullfolder($dir["alter"]) as $file) {
            echo $file . "\n";
            $x = file_get_contents($file);
            foreach (explode(";", $x) as $xx) {
                if ($xx !== "") {
                    $conn->query($xx);
                }
            }
        }
        echo "     Done\n";
    }
}
