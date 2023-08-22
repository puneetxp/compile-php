<?php

namespace Puneetxp\CompilePhp;

class index
{
    private $table = [];
    public function __construct(private $rawtable)
    {
    }
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
    public function defaultsetup()
    {
        if (in_array('id', $this->rawtable)) {
            $table[] = ['name' => 'id', 'mysql_data' => 'int', 'datatype' => 'number', 'fillable' => "false", 'sql_attribute' => 'UNSIGNED PRIMARY KEY AUTO_INCREMENT'];
        }
        if (in_array('created_at', $this->rawtable)) {
            $table[] = ['name' => 'created_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP NOT NULL'];
        }
        if (in_array('updated_at', $this->rawtable)) {
            $table[] = ['name' => 'updated_at', 'mysql_data' => 'timestamp', 'datatype' => 'Date', 'fillable' => "false", 'sql_attribute' => 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL'];
        }
        return $this;
    }
    public function addional_default()
    {
        isset($this->rawtable['enable']) ?
            $this->table[] = ['name' => 'enable', 'mysql_data' => 'TINYINT(1)', 'datatype' => 'number', 'default' => 1, 'sql_attribute' => 'DEFAULT ' . $this->rawtable['enable'] . ' NOT NULL '] : '';
        if (isset($this->rawtable["additional"])) {
            switch ($this->rawtable->additional) {
                case "seo":
                    $this->table[] = ['name' => 'enable', 'mysql_data' => 'VARCHAR(255)', 'datatype' => 'string', 'sql_attribute' => ' NOT NULL '];
                    break;
                case "slug":
                    $this->table = [...$this->table, ['name' => 'title', 'mysql_data' => 'VARCHAR(255)', 'datatype' => 'string', 'sql_attribute' => ' NULL '], ['name' => 'seo_description', 'mysql_data' => 'longtext', 'datatype' => 'string', 'sql_attribute' => ' NULL ']];
                    break;
            }
        }
        return $this;
    }
    public function default_attribute($item)
    {
        if (isset($item['sql_attribute'])) {
            if (str_contains($item['sql_attribute'], 'NULL')) {
            } else {
                $item['sql_attribute'] = $item['sql_attribute'] . " NOT NULL";
            }
        } elseif (isset($item['default'])) {
            $item['sql_attribute'] = " ";
        } else {
            $item['sql_attribute'] = " NOT NULL";
        }
        return $item;
    }
    public static function table_set($table)
    {
        return (new static($table))->defaultsetup()->addional_default();
    }
    public static function templatecopy(string $folder, string $destination)
    {
        foreach (index::scanfullfolder(__DIR__ . "/template/$folder") as $file) {
            $pre = __DIR__ . '/../' . $destination;
            $target = str_replace(__DIR__ . "/template/$folder", "",  $file);
            if (!is_file($pre . $target)) {
                copy($file, $pre . $target);
            }
        }
    }
}
