<?php

namespace Puneetxp\CompilePhp\Compile;

use Puneetxp\CompilePhp\Class\index;

class CompilePhp
{
    public $config;
    public array $files = [];
    public function __construct(
        public $dir = 'View',
        public $pre = __DIR__ . "/../../Resource",
    ) {
        $this->config = json_decode(file_get_contents($this->pre . '/config.json'), TRUE);
        $dir = $this->pre . DIRECTORY_SEPARATOR . $this->dir;
        $this->folderscan($dir);
    }
    function folderscan($dir)
    {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file == '.') {
                } elseif ($file == "..") {
                } elseif (is_file("$dir/$file")) {
                    $this->ComponentDir($dir, $file);
                } elseif (is_dir("$dir/$file")) {
                    $this->folderscan("$dir/$file");
                }
            }
        }
    }

    public function ComponentDir($dir, $file)
    {
        $namespace = strtolower(str_replace($this->pre . DIRECTORY_SEPARATOR . 'Resource/', "", $dir));
        $filename = strtolower(str_replace(".html", "", $file));
        if (filesize($dir . DIRECTORY_SEPARATOR . $file) > 0) {
            $file = fread(fopen($dir . DIRECTORY_SEPARATOR . $file, "r"), filesize($dir . DIRECTORY_SEPARATOR . $file));
        } else {
            $file = "";
        }
        preg_match_all("/[@]props\((\{[\s\S]*?\})\)/m", $file, $parameter, PREG_SET_ORDER);
        $parameter =  (array) json_decode(str_replace(["\n", "\r\n", "\r", "\t"], "", $parameter[0][1] ?? "[]"));
        // print_r($parameter);
        $html = preg_replace("/[@]props\((\{[\s\S]*?\})\)/m", "", $file);
        //print_r($file);
        $html = (new htmlParser(htmlstring: $file, config: $this->config))->parse();
        $name =  str_replace($this->pre, "", $namespace . DIRECTORY_SEPARATOR . $filename);
        $this->files[$name] = (object)["html" => $html, "t_tag" => array_unique($html->t_tags()), "filename" => $filename, "directory" => $namespace, "parameter" => $parameter];
    }
    public function php($destination)
    {
        foreach ($this->files as $index => $value) {
            index::createfile(
                $destination . DIRECTORY_SEPARATOR . $index . ".php",
                "<?php namespace " .
                    str_replace('/', '\\', $value->directory) . "; " .
                    implode("", array_map(fn ($value) => "use view\\" . str_replace(".", "\\", $value) . "; ", $value->t_tag)) .
                    "class $value->filename { public function __construct(" .
                    ' $data = [], $attribute = [], $child = "",' .
                    implode(",", (array_map(fn ($value, $key) =>
                    '$' . "$key = " .
                        (is_array($value) || is_object($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($value->parameter), array_keys($value->parameter)))) .
                    ")  {
                    ?> " . $value->html->tostring() . "<?php }
            }"
            );
        }
    }
}
