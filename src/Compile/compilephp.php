<?php


namespace Puneetxp\CompilePhp\Compile;
use Puneetxp\CompilePhp\Class\index;
class compilephp
{

    // public $y;
    public $x = [];
    public $config;
    public $t_pattern = "<t-(.+?)(((\/>|>)(((([\s\S]*?|)(<\/(t-.*)>)))|()))|(( (.+?)\/>))|(( (.+?)>)((([\s\S]*?|)(<\/(t-.*)>)))))";
    public $foreach_pattern = "[@]foreach\((.+) i (.+)\)([\s\S]*?)[@]endforeach";
    public $files = [];
    public $active = [];

    public function __construct(
        public $dir = 'View',
        public $pre = __DIR__ . "/../../Resource",
        public $destination = "/php",
    ) {
        $this->config = json_decode(file_get_contents($this->pre . '/config.json'), TRUE);
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

    public function component_nested($set, $x, $n = 0)
    {
        if (preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            $nested_set = (isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : '');
            while (preg_match("/" . $this->t_pattern . "/m", $nested_set)) {
                $set = str_replace($nested_set, $this->component_nested(set: $nested_set, x: $x, n: $n + 1), $set);
                preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER);
                $nested_set = (isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : '');
            }
        }
        if ($n && preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            $this->files[$this->active]['child'][$x . $n] = $this->childvariable((isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : ''));
        } elseif (preg_match("/" . $this->t_pattern . "/m", $set) && preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            if ($this->childvariable($child[0][8] . (isset($child[0][20]) ? $child[0][20] : "")) != "") {
                $this->files[$this->active]['child'][$x . $n] = $this->childvariable($child[0][8] . (isset($child[0][20]) ? $child[0][20] : ""));
            }
        }
        return $this->repfunction("/()" . $this->t_pattern . "/m", $set, $n, $x);
    }

    public function repfunction($__pattern, $set, $n, $x)
    {
        preg_match_all($__pattern, $set, $child, PREG_SET_ORDER);
        $this->files[$this->active]["namespaces"][] = "use view\\" . $this->replacefunction($child[0][2]) . ";";
        return preg_replace_callback(
            $__pattern,
            fn ($match) => '<?php ' . $match[1] . '' . preg_replace("/((.*)[.])?(.*)/", "$3", $match[2]) . "::run( " . $this->attribute_rep("attribute: (" . (isset($match[18]) ? $match[18] : '') . (isset($match[15]) ? $match[15] : '') . ")") . (($match[9] . (isset($match[21]) ? $match[21] : "") != "") ? ("," . "child :" . ' $this->child' . $x . $n . '()') : "") . ' )' . '?>',
            $set,
            1
        );
    }

    public function repforeach($__pattern, $set, $n, $x)
    {
        return preg_replace_callback(
            $__pattern,
            fn ($match) => '<?php ' . $match[1] . '' . preg_replace("/((.*)[.])?(.*)/", "$3", $match[2]) . "::run( " . $this->attribute_rep("attribute: (" . (isset($match[18]) ? $match[18] : '') . (isset($match[15]) ? $match[15] : '') . ")") . (($match[9] . (isset($match[21]) ? $match[21] : "") != "") ? ("," . "child :" . ' $this->child' . $x . $n . '()') : "") . ' )' . '?>',
            $set,
            1
        );
    }

    public function childvariable($file)
    {
        return preg_replace("/^(?!<?php $)[$]{1,1}+([a-zA-Z\d\_-]+)?/", '<?= $this->' . "$1" . ' ?>', $file);
    }

    // $prez = "../";
    public function attribute_rep(string $file)
    {
        preg_match_all("/attribute: \(([^\)]{0,})\)/m", $file, $use_temp_multiple, PREG_SET_ORDER);
        foreach ($use_temp_multiple as $value) {
            preg_match_all("/([a-zA-Z\d?:\.\-_+]+)=?(\"([A-Za-z\d\s?$%&+=;_:'.\-\/\\%]*)\"|(.*))?/m", $value[1], $test, PREG_SET_ORDER);
            $a = [];
            $n = [];
            foreach ($test as $i) {
                if (preg_match("/[:]([a-zA-Z\d?:\-_+]{1,})/", $i[1])) {
                    if (isset($i[4]) && $i[4] !== "") {
                        $variable = json_decode($i[4], true);
                        if ($variable) {
                            $n[] = str_replace(":", "", $i[1]) . ": " . var_export($variable, true);
                        }
                    } else
                        $n[] = str_replace(":", "", $i[1]) . ": " . $i[3];
                } elseif (preg_match("/[$]([a-zA-Z_]{1,1}+([a-zA-Z\d\_-]+)?)/", $i[3]) || preg_match("/[\d]+?/", $i[3])) {
                    $a[] = '"' . $i[1] . '"' . "=>" . $i[3];
                } else {
                    $a[] = '"' . $i[1] . '"' . '=>' . $i[2];
                }
            }
            return str_replace(
                $value[0],
                "attribute: " . "[" . implode(",", $a) . "]" . (count($n) > 0 ? ", " . implode(",", $n) : ''),
                $file
            );
        }
    }

    public function variablecon($match, $x = 0, $exception = [])
    {
        return preg_replace_callback(
            "/[$]{1,1}+([a-zA-Z\d\_-]+)?/",
            fn ($match) => in_array($match[1], $exception) ? $match[1] : '$this->' . $match[1],
            $match[1]
        );
    }

    public function conditioncheck($file)
    {
        $file = preg_replace_callback("/[@]if\((.*?)\)/m", fn ($match) => '  <?php if(' . $this->variablecon($match) . ') { ?> ', $file);
        $file = preg_replace_callback("/[@]elseif\((.*?)\)/m", fn ($match) => "<?php }elseif(" . $this->variablecon($match) . "){ ?>", $file);
        $file = preg_replace("/[@]else/m", "<?php }else { ?>", $file);
        $file = preg_replace("/[@]endif/m", "<?php } ?>", $file);
        return $file;
    }

    public function compile_Tfunc($file)
    {
        $__x = 0;
        while (preg_match("/" . $this->t_pattern . "/m", $file)) {
            $file = $this->component_nested(set: $file, n: 0, x: $__x);
            $__x++;
        }
        return $file;
    }

    public function foreachnested($file)
    {
        while (preg_match("/" . $this->foreach_pattern . "/m", $file)) {
            $file = $this->foreachcompile(set: $file, variable: []);
        }
        return $file;
    }

    public function foreachcompile($set, $variable = [], $n = 0)
    {
        if (preg_match_all("/" . $this->foreach_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            $nested_set = $child[0][3];
            if (preg_match("/" . $this->foreach_pattern . "/m", $nested_set)) {
                $set = str_replace($nested_set, $this->component_nested(set: $nested_set, x: $child[0][2], n: $n + 1), $set);
            }
        }
        return preg_replace_callback("/" . $this->foreach_pattern . "/m", fn ($match) => "<?php foreach( " . $this->variablecon($match) . " as $match[2] ) { ?> $match[3] <?php } ?>", $set);
    }

    public function replacefunction($function)
    {
        foreach ((array) $this->config["alias"] as $key => $value) {
            $function = preg_replace("/$value\./", $key . "\\", $function);
        }
        return str_replace(".", "\\", $function);
    }

    public function ComponentDir($dir, $file)
    {
        $namespace = strtolower(str_replace($this->pre . DIRECTORY_SEPARATOR . 'Resource/', "", $dir));
        print_r($namespace."\n");
        $filename = strtolower(str_replace(".html", "", $file));
        $this->active = $namespace . DIRECTORY_SEPARATOR . $filename;
        $this->files[$this->active] = ["namespace" => $namespace, "filename" => $filename, "namespaces" => [], "child" => []];
        if (filesize($dir . DIRECTORY_SEPARATOR . $file) > 0) {
            $file = fread(fopen($dir . DIRECTORY_SEPARATOR . $file, "r"), filesize($dir . DIRECTORY_SEPARATOR . $file));
        } else {
            $file = "";
        }
        preg_match_all("/[@]props\((\{[\s\S]*?\})\)/m", $file, $parameter, PREG_SET_ORDER);
        $file = preg_replace("/[@]props\((\{[\s\S]*?\})\)/m", "", $file);
        $file = preg_replace("/\{\{(?!\$this)[$]{1,1}+([a-zA-Z\d\_-]+)?\}\}/m", '<?= $' . "$1" . ' ?>', $file);
        $file = preg_replace("/\{(?!\$this)[$]{1,1}+([a-zA-Z\d\_-]+)?\}/m", '<?= $this->' . "$1" . ' ?>', $file);
        $file = $this->conditioncheck($file);
        $file = $this->foreachcompile($file);
        $file = $this->compile_Tfunc($file);
        $param = "";
        $keyparm = "";
        $parampublic = "";
        if (isset($parameter[0])) {
            $r = (array) json_decode(str_replace(["\n", "\r\n", "\r", "\t"], "", $parameter[0][1]));
            $keyparm = "," . implode(",", (array_map(fn ($key) => '$' . "$key", array_keys($r))));
            $parampublic = "," . implode(",", (array_map(fn ($value, $key) =>
            'public $' . "$key = " .
                (is_array($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($r), array_keys($r))));
            $param = "," . implode(",", (array_map(fn ($value, $key) => '$' . "$key = " .
                (is_array($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($r), array_keys($r))));
        }
        if (count($this->files[$this->active]['child']) > 0) {
            $childx = implode("", (array_map(fn ($value, $key) => "public function child$key() { 
              ob_start(); ?>" . "$value" . "<?php  return ob_get_clean(); }", array_values($this->files[$this->active]['child']), array_keys($this->files[$this->active]['child']))));
        } else {
            $childx = "";
        }
        $r = "namespace " . str_replace("/", '\\', $namespace) . ";  " . implode("", array_unique($this->files[$this->active]["namespaces"])) . " class $filename { $childx" . ' public function __construct(public $data = [],public $attribute = [],public $child = ""' . $parampublic . '){ } ' . " public static function run(" . '$data = [] , $attribute = [] ,$child = "" ' . "$param) {" . 'return (new self($data,$attribute,$child' . $keyparm . '))->view();' . " } public function  view" . '(' . " ){?>  " . $file . " <?php } } \n \r\n \r";
        $this->files[$this->active]['body'] = str_replace(["\n", "\r\n", "\r", "\t", "    ", "   ", "                  "], "", $this->addthis($this->withoutthis($r)));
        $this->active = "";
    }

    public function withoutthis($r)
    {
        return  preg_replace("/\{\{(?!\$this)[$]{1,1}+([a-zA-Z\d\_-]+)?((\[.*\])|())\}\}/m", '<?= $' . "$1" . '$3 ?>', $r);
    }

    public function addthis($r)
    {
        return  preg_replace("/\{(?!\$this)[$]{1,1}+([a-zA-Z\d\_-]+)?((\[.*\])|())\}/m", '<?= $this->' . "$1" . '$3 ?>', $r);
    }

    public function run()
    {
        $dir = $this->pre . DIRECTORY_SEPARATOR . $this->dir;
        $this->folderscan($dir);
        foreach ($this->files as $key => $value) {
            print_r($this->dir."   d\n" .  $this->destination . "\n");
            print_r("oooooo\n" . $this->pre . $this->destination .DIRECTORY_SEPARATOR. $value["namespace"] . DIRECTORY_SEPARATOR . $value["filename"] . "\n.php\n");
            index::createfile($this->pre  . $this->destination .DIRECTORY_SEPARATOR. $value["namespace"] . DIRECTORY_SEPARATOR . $value["filename"] . ".php", "<?php " . $value['body']);
        }
    }
}