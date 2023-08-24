<?php

namespace Puneetxp\CompilePhp\Class;

class index
{
    static function interface_set($table)
    {
        $x = [];
        foreach ($table['data'] as $item) {
            if (isset($item['sql_attribute']) && (str_contains($item['sql_attribute'], 'NOT NULL') || str_contains($item['sql_attribute'], 'PRIMARY') || str_contains($item['sql_attribute'], 'UNIQUE'))) {
                $x[] = $item['name'] . ': ' . $item['datatype'];
            } else {
                $x[] = $item['name'] . ': ' . $item['datatype'] . ' | null';
            }
        }
        return "export interface " . ucfirst($table['name']) . " {
       " . implode(',
       ', $x) . '
    }';
    }
    static function php_wrapper($data)
    {
        return '<?php ' . $data . '?> ';
    }

    static function php_w($data)
    {
        return '<?php ' . $data;
    }

    static function class_wrapper($name, $data)
    {
        return ' class ' . $name . ' {' . $data . '} ';
    }

    static function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }

    static function fopen_dir($link)
    {
        $filename = $link;
        $dirname = dirname($filename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        return fopen($filename, 'w');
    }
    static function createfile($dir, $string)
    {
        fwrite(index::fopen_dir($dir), $string);
    }
    public static function copyfile($from, $to)
    {
        fwrite(index::fopen_dir($to), file_get_contents($from));
    }
    static function scanfullfolder($dir)
    {
        $x = [];
        if (is_dir($dir)) {
            $d = scandir($dir);
            for ($i = 2; $i < count($d); $i++) {
                if (is_file("$dir/$d[$i]")) {
                    array_push($x, "$dir/$d[$i]");
                } else {
                    $x = [...$x, ...index::scanfullfolder("$dir/$d[$i]")];
                }
            }
        }
        return $x;
    }
    public $table = [];
    public function __construct(private $rawtable, private $all)
    {
        $this->table["name"] = $this->rawtable['name'];
        $this->table["table"] = $this->rawtable['table'];
        if (isset($rawtable['type'])) {
            $this->table["type"] = $this->rawtable['type'];
        }
        if (isset($rawtable['crud'])) {
            $this->table["crud"] = $this->rawtable['crud'];
        }
        $this->table["data"] = [];
    }
    public function defaultsetup($default)
    {
        if (in_array('id', $default)) {
            $this->table["data"][] = ['name' => 'id', 'mysql_data' => 'int', 'datatype' => 'number', 'fillable' => "false", 'sql_attribute' => 'UNSIGNED PRIMARY KEY AUTO_INCREMENT'];
        }
        if (in_array('created_at', $default)) {
            $this->table["data"][] = ['name' => 'created_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP NOT NULL'];
        }
        if (in_array('updated_at', $default)) {
            $this->table["data"][] = ['name' => 'updated_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL'];
        }
        return $this;
    }
    public function addional_default()
    {
        isset($this->rawtable['enable']) ?
            $this->table["data"][] = ['name' => 'enable', 'mysql_data' => 'TINYINT(1)', 'datatype' => 'number', 'default' => 1, 'sql_attribute' => 'DEFAULT ' . $this->rawtable['enable'] . ' NOT NULL '] : '';
        if (isset($this->rawtable["additional"])) {
            switch ($this->rawtable->additional) {
                case "seo":
                    $this->table["data"][] = ['name' => 'enable', 'mysql_data' => 'VARCHAR(255)', 'datatype' => 'string', 'sql_attribute' => ' NOT NULL '];
                    break;
                case "slug":
                    $this->table["data"][] = ['name' => 'title', 'mysql_data' => 'VARCHAR(255)', 'datatype' => 'string', 'sql_attribute' => ' NULL '];
                    $this->table["data"][] = ['name' => 'seo_description', 'mysql_data' => 'longtext', 'datatype' => 'string', 'sql_attribute' => ' NULL '];
                    break;
            }
        }
        return $this;
    }
    public static function table_set($table, $all)
    {
        return (new static($table, $all))->defaultsetup(isset($table["default"]) ? $table["default"] : ['id', 'created_at', 'updated_at'])->addional_default()->importdata()->relation();
    }
    public function importdata()
    {
        if (isset($this->rawtable['data']) && count($this->rawtable["data"])) {
            $this->table["data"] = array_merge($this->table["data"], $this->rawtable["data"]);
        }
        return $this;
    }
    public function relation()
    {
        if (isset($this->rawtable['relation'])) {
            foreach ($this->rawtable['relation'] as $relation) {
                $r = [];
                is_array($relation) ? $r['name'] = $relation['name'] : $r['name'] = $relation;
                $r = array_search($r['name'], array_column($this->all, 'name'));
                $rx = ['table' => $this->all[$r]['table'], 'name' => isset($relation['alias']) ? $relation['alias'] : $this->all[$r]['name'] . '_id', 'key' => 'id'];
                $this->table["data"][] = [
                    'name' => isset($relation['alias']) ? $relation['alias'] : $this->all[$r]['name'] . '_id',
                    'mysql_data' => 'int UNSIGNED',
                    'datatype' => 'number',
                    ...(isset($relation["default"]) ? ["default" => $relation["default"]] : []),
                    ...(isset($relation["sql_attribute"]) ? ["sql_attribute" => $relation["sql_attribute"]] : []),
                    'relations' => [$this->all[$r]['name'] => $rx]
                ];
                $this->table['relations'][$this->all[$r]['name']] = $rx;
            }
        }
        return $this;
    }
    public static function templatecopy(string $folder, string $destination)
    {
        foreach (index::scanfullfolder(__DIR__ . "/../template/$folder") as $file) {
            $target = str_replace(__DIR__ . "/../template/$folder", "",  $file);
            if (!is_file($_ENV['dir']  . DIRECTORY_SEPARATOR . $destination . $target)) {
                index::copyfile($file, $_ENV['dir'] . DIRECTORY_SEPARATOR  . $destination .  $target);
            }
        }
    }
}
