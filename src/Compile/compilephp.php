<?php

namespace Puneetxp\CompilePhp\Compile;

use Puneetxp\CompilePhp\Class\index;

class compilephp {

    // public $y;
    public $x = [];
    public $config;
    public $t_pattern = "<t-(.+?)(((?<!this-)(\/>|>)(((([\s\S]*?|)(<\/(t-.*)(?<!this-)>)))|()))|(( (.+?)\/(?<!this-)>))|(( (.+?)(?<!this-)>)((([\s\S]*?|)(<\/(t-.*)>)))))";
    public $html_pattern = "<t-(.+?)(((?<!this-)(\/>|>)(((([\s\S]*?|)(<\/(t-.*)(?<!this-)>)))|()))|(( (.+?)\/(?<!this-)>))|(( (.+?)(?<!this-)>)((([\s\S]*?|)(<\/(t-.*)>)))))";
    //public $t_pattern = "<t-(.+?)(((\/>|>)(((([\s\S]*?|)(<\/(t-.*)>)))|()))|(( (.+?)\/>))|(( (.+?)>)((([\s\S]*?|)(<\/(t-.*)>)))))\b(?<!this->)";
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

    function folderscan($dir) {
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

    public function compile_Tfunc($file) {
        $__x = 0;
        while (preg_match("/" . $this->t_pattern . "/m", $file)) {
            $file = $this->component_nested(set: $file, n: 0, x: $__x);
            $__x++;
        }
        return $file;
    }

    public function component_nested($set, $x, $n = 0) {
        if (preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            $nested_set = (isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : '');
            while (preg_match("/" . $this->t_pattern . "/m", $nested_set)) {
                $set = str_replace($nested_set, $this->component_nested(set: $nested_set, x: $x, n: $n + 1), $set);
                preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER);
                $nested_set = (isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : '');
            }
        }
        if ($n && preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            $this->files[$this->active]['child'][$x . $n] = (isset($child[0][8]) ? $child[0][8] : '') . (isset($child[0][20]) ? $child[0][20] : '');
        } elseif (preg_match("/" . $this->t_pattern . "/m", $set) && preg_match_all("/" . $this->t_pattern . "/m", $set, $child, PREG_SET_ORDER)) {
            if ($child[0][8] . (isset($child[0][20]) ? $child[0][20] : "") != "") {
                $this->files[$this->active]['child'][$x . $n] = $child[0][8] . (isset($child[0][20]) ? $child[0][20] : "");
            }
        }
        return $this->repfunction("/()" . $this->t_pattern . "/m", $set, $n, $x);
    }

    public function repfunction($__pattern, $set, $n, $x) {
        return preg_replace_callback(
                $__pattern,
                function ($match) use ($x, $n) {
                    $this->files[$this->active]["namespaces"][] = "use view\\" . $this->replacefunction($match[2]) . ";";
                    //isset($match[18]) &&  print_r($match[18] . "\n");
                    return '<?php ' . $match[1] . '' . preg_replace("/((.*)[.])?(.*)/", "$3", $match[2]) . "::run( " . $this->attribute_rep((isset($match[18]) ? $match[18] : '') . (isset($match[15]) ? $match[15] : '')) . (($match[9] . (isset($match[21]) ? $match[21] : "") != "") ? ("," . "child :" . ' $this->child' . $x . $n . '()') : "") . ' )' . '?>';
                },
                $set,
                1
        );
    }

    public function attribute_rep(string $file) {
        $a = [];
        $n = [];
        //print_r($file);
        while (preg_match("/([:\w|data-]+)=[\"']?((?:.(?![\"']?\s+(?:\S+)=|[\"\']$))+.)[i\"']?/m", $file, $i)) {
            //print_r($i);
            $json = "/^\[.+?/";
            if (preg_match("/[:]([a-zA-Z\d?:>\-_\$+]{1,})/", $i[1], $attribute)) {
                if (preg_match($json, $i[2])) {
                    $n[] = $attribute[1] . ": " . var_export(json_decode($i[2]), true);
                } else {
                    $n[] = str_replace(":", "", $i[1]) . ": " . str_replace($i[1] . "=", "", $i[0]);
                }
            } elseif (preg_match("/[$]([a-zA-Z_]{1,1}+([a-zA-Z\d\_-]+)?)/", $i[2]) || preg_match("/[\d]+?/", $i[2])) {
                $a[] = '"' . $i[1] . '"' . "=>" . $i[2];
            } else {
                $a[] = '"' . $i[1] . '"' . '=>' . $i[2];
            }
            $file = str_replace($i[0], "", $file);
        }
        //print_r($n);
        return "attribute: " . "[" . implode(",", $a) . "]" . (count($n) > 0 ? ", " . implode(",", $n) : '');
    }

    public function data_attribute($file) {
        //$attribute = "/[\:]([\w|data-]+)=((?:.(?![\"\']?\s+(?:\S+)=|[\"\']$))+.)[\"\']?(?:(?:\/)(?:\>))/m";
        //$attribute = "/([\:][\w|data-]+)=((?:.(?![\"\']?\s+(?:\S+)=|[\"\']$))+.)[\"\']?/m";

        /* preg_replace_callback($attribute, function ($match) {
          print_r($match);
          return "";
          }, $file); */

        $this->files[$this->active]['body'] = str_replace(["\r", "\t", "    ", "   ", "                  "], "", preg_replace_callback("/(<[\w].+? )(.+?)((?:\/|)(?<!this-)>)/m", function ($html) {
                    $attribute = "/( |\"|\')[\:]([\w|data-]+)=?((?:.(?![\"\']?\s+(?:\S+)=|[\"\']$))+.[\"\']?)/m";
                    return $html[1] . preg_replace_callback($attribute, function ($match) {
                        return $match[1] . $match[2] . "=<?=" . $match[3] . "?>";
                    }, $html[2]) . $html[3];
                    return $html[0];
                }, $file));
        $this->files[$this->active]['body'] = str_replace(["\n", "\r\n"], " ", $this->files[$this->active]['body']);
        $this->active = "";
        //return $file;
    }

    public function find(string $file): string {
        $find_pattern = "@find\((.*)\:\:([\s\S]*?) i ([\s\S]*?)\:\:(.+)\)([\s\S]*?)@endfind";
        $file = preg_replace_callback(
                "/" . $find_pattern . "/m",
                function ($match) {
                    //print_r($match);
                    return "<?= $match[3]" . '[array_search(' . $match[1] . '["' . $match[2] . '"], array_column(' . $match[3] . ',"' . $match[4] . '" ))]' . $match[5] . " ?>";
                },
                $file
        );
        return $file;
    }

    /* public function repforeach($__pattern, $set, $n, $x)
      {
      return preg_replace_callback(
      $__pattern,
      fn ($match) => '<?php ' . $match[1] . '' . preg_replace("/((.*)[.])?(.*)/", "$3", $match[2]) . "::run( " . $this->attribute_rep("attribute: (" . (isset($match[18]) ? $match[18] : '') . (isset($match[15]) ? $match[15] : '') . ")") . (($match[9] . (isset($match[21]) ? $match[21] : "") != "") ? ("," . "child :" . ' $this->child' . $x . $n . '()') : "") . ' )' . '?>',
      $set,
      1
      );
      } */
    public function envfunction($file){
        return preg_replace_callback("/[@]env\((.*?)\)[@]/m", fn($match) => ' <?= $_ENV['.$match[1].'] ?> ', $file);
    }

    public function conditioncheck($file) {
        $file = preg_replace_callback("/[@]isset\((.*?)\)[@]/m", fn($match) => "  <?= $match[1] ?? '' ?> ", $file);
        $file = preg_replace_callback("/[@]isset\((.*?)\)/m", fn($match) => '  <?php if(isset(' . $match[1] . ')) { ?> ', $file);
        $file = preg_replace_callback("/[@]auth\(\)/m", fn() => '  <?php  if(session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["user_id"])){ ?> ', $file);
        $file = preg_replace_callback("/[@]if\((.*?)\)/m", fn($match) => '  <?php if(' . $match[1] . ') { ?> ', $file);
        $file = preg_replace_callback("/[@]elseif\((.*?)\)/m", fn($match) => "<?php }elseif(" . $match[1] . "){ ?>", $file);
        $file = preg_replace("/[@]else/m", "<?php }else { ?>", $file);
        $file = preg_replace("/[@]endif/m", "<?php } ?>", $file);
        $file = preg_replace("/[@]endisset/m", "<?php } ?>", $file);
        return $file;
    }

    public function foreachnested($file) {
        while (preg_match("/" . $this->foreach_pattern . "/m", $file)) {
            $x = [];
            $y = $file;
            // $z = true;
            while (preg_match("/" . $this->foreach_pattern . "/m", $y)) {
                array_push($x, "");
                $y = $this->foreach_replace(set: $y);
            }
            if (count($x) > 1) {
                $foreach = $this->foreach_pattern . implode("([\s\S]*?)[@]endforeach", $x);
                $foreach_pattern = "[@]foreach\((.+) i (.+)\)([\s\S]*)[@]endforeach";
                if (preg_match_all("/" . $foreach . "/m", $file, $child, PREG_SET_ORDER)) {
                    $file = str_replace($child[0][0], $this->foreach_replace($child[0][0], $foreach_pattern), $file);
                }
            } else {
                $file = $y;
            }
        }
        return $file;
    }

    public function foreach_replace($set, $foreach_pattern = null) {
        return preg_replace_callback("/" . ($foreach_pattern ? $foreach_pattern : $this->foreach_pattern) . "/m", function ($match) {
            $x = str_replace("this->", "", $match[2]);
            return "<?php foreach( " . $match[1] . " as " . $x . " ) { ?> " . str_replace($match[2], $x, $match[3]) . " <?php } ?>";
        }, $set);
    }

    public function replacefunction($function) {
        foreach ((array) $this->config["alias"] as $key => $value) {
            $function = preg_replace("/$value\./", $key . "\\", $function);
        }
        return str_replace(".", "\\", $function);
    }

    public function ComponentDir($dir, $file) {
        $namespace = strtolower(str_replace($this->pre . DIRECTORY_SEPARATOR . 'Resource/', "", $dir));
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
        $file = preg_replace("/[$]{1,1}+([a-zA-Z\d_-]+)?/m", '$this->' . "$1", $file);
        //$foreach_pattern = "[@]foreach\((.+) i (.+)\)([\s\S]*)[@]endforeach";
        $file = preg_replace("/\{\{([\s\S]*?)\}\}/m", '<?= ' . "$1" . ' ?>', $file);
        /* $file = preg_replace("/\{(.+)?\}/m", '<?= ' . "$1" . ' ?>', $file); */
        $file = $this->conditioncheck($file);
        $file = $this->envfunction($file);
        $file = $this->foreachnested($file);
        $file = $this->find($file);
        $file = $this->compile_Tfunc($file);
        $param = "";
        $keyparm = "";
        $parampublic = "";
        if (count($parameter)) {
            $r = (array) json_decode(str_replace(["\n", "\r\n", "\r", "\t"], "", $parameter[0][1]));
            $keyparm = "," . implode(",", (array_map(fn($key) => '$' . "$key", array_keys($r))));
            $parampublic = "," . implode(",", (array_map(fn($value, $key) =>
                                    'public $' . "$key = " .
                                    (is_array($value) || is_object($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($r), array_keys($r))));
            $param = "," . implode(",", (array_map(fn($value, $key) => '$' . "$key = " .
                                    (is_array($value) || is_object($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($r), array_keys($r))));
        }
        if (count($this->files[$this->active]['child']) > 0) {
            $childx = implode("", (array_map(fn($value, $key) => "public function child$key() {
            ob_start(); ?>" . "$value" . "<?php  return ob_get_clean(); }", array_values($this->files[$this->active]['child']), array_keys($this->files[$this->active]['child']))));
        } else {
            $childx = "";
        }
        $r = "namespace " . str_replace("/", '\\', $namespace) . ";  " . implode("", array_unique($this->files[$this->active]["namespaces"])) . " class $filename { $childx" . ' public function __construct(public $data = [],public $attribute = [],public $child = ""' . $parampublic . '){ } ' . " public static function run(" . '$data = [] , $attribute = [] ,$child = "" ' . "$param) {" . 'return (new self($data,$attribute,$child' . $keyparm . '))->view();' . " } public function  view" . '(' . " ){?>  " . $file . " <?php } } \n \r\n \r";
        $this->data_attribute($r);
    }

    public function run() {
        $dir = $this->pre . DIRECTORY_SEPARATOR . $this->dir;
        $this->folderscan($dir);
        foreach ($this->files as $value) {
            index::createfile($this->pre . $this->destination . DIRECTORY_SEPARATOR . $value["namespace"] . DIRECTORY_SEPARATOR . $value["filename"] . ".php", "<?php " . $value['body']);
        }
        // print_r($this->files);
    }
}
