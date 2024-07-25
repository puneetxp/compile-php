<?php

namespace Puneetxp\CompilePhp\Compile;

use Puneetxp\CompilePhp\Class\index;

class phpCompile
{
    public $file;
    public function __construct(private string $destination, private $files)
    {
        foreach ($this->files as $index => $value) {
            $this->file = $value;
            $parameter = implode(",", (array_map(fn($value, $key) =>
            '$' . "$key = " .
                (is_array($value) || is_object($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($value->parameter), array_keys($value->parameter))));
            index::createfile(
                $this->destination . DIRECTORY_SEPARATOR . $index . ".php",
                "<?php namespace " .
                    str_replace('/', '\\', $value->directory) . "; " .
                    implode("", array_map(fn($value) => "use view\\" . str_replace(".", "\\", $value) . "; ", $value->t_tag)) .
                    "class $value->filename { public function __construct(" .
                    ' $data = [], $attribute = [], $child = "",' .
                    $parameter . ")  {?> " .
                    preg_replace("/\{\{([\s\S]*?)\}\}/m", '<?= ' . "$1" . ' ?>',  $this->tostring($value->html->tags))
                    . "<?php }}"
            );
        }
    }
    public function tostring($file)
    {
        $string = "";
        foreach ($file as $tag) {
            if (isset($tag["tag"]) && $tag["tag"] !== "") {
                if (($tag["tag"][0] ?? '') . ($tag["tag"][1] ?? '') == "t-") {
                    $x = explode(".", str_replace("t-", "", $tag["tag"]));
                    $x = $x[count($x) - 1];
                    $parameter = '';
                    $native = '[]';
                    if (isset($tag['attribute'])) {
                        $y =  array_filter($tag['attribute'], fn($x) => count(str_split($x)) && (str_split($x)[0] == ":"), ARRAY_FILTER_USE_KEY);
                        if (count($y)) {
                            $parameter = implode(
                                '',
                                array_map(
                                    fn($k, $value) =>
                                    str_replace(':', '', $k) . ": " .
                                        (is_array($value['value']) || is_object($value['value']) ? var_export($value['value'], true) : (preg_match("/\d/", $value['value']) ? $value['value'] : ($value['quote'] . $value['value'] . $value['quote']))) . ",",
                                    array_keys($y),
                                    $y
                                )
                            );
                        }
                        $y = array_filter($tag['attribute'], fn($x) => count(str_split($x)) && (str_split($x)[0]  != ":"), ARRAY_FILTER_USE_KEY);
                        if (count($y)) {
                            $native =  implode('', array_map(fn($key, $value) => $key . '=' . $value["quote"] . $value["value"] . $value["quote"], array_keys($y), $y));
                        }
                    }
                    $string .= "<?php new $x(" . "child: function() use (" . ' $attribute, $data , $child ' . implode('', array_map(fn($key) => ", $" . $key, array_keys($this->file->parameter ?? []))) . " ) {?>" .
                        ($this->tostring($tag['childern'] ?? []) ?? '') .
                        "<?php }," .
                        $parameter .
                        "attribute: " . $native
                        . ");?>";
                } elseif (($tag["tag"][0] ?? '') . ($tag["tag"][1] ?? '') == "f-") {
                    $string .= $this->phpFunction(str_replace("f-", "", $tag["tag"]), array_map(fn($value) => $value["value"], $tag['attribute']), $this->tostring($tag['childern'] ?? []));
                    /*                    $string .= "<?php new $x(" . "child: function(){?>" .
                       ($this->tostring($tag['childern'] ?? []) ?? '') .
                       "<?php }," . $parameter .
                       "attribute: " . $native .
                        ")?>";
*/
                } elseif ($tag["tag"] == "slot") {
                    $string .= '<?php is_callable($child) ? $child() : $child ?>';
                } else {
                    $string .= "<" . $tag["tag"];
                    foreach (($tag["attribute"] ?? []) as $key => $value) {
                        //print_r($key);
                        //print_r($value);
                        if (str_split($key)[0] !== ':') {
                            $string .= " " . $key . ($value["value"] != "" ? ("=" . ($value["quote"] ?? "") . ($value["value"] ?? "") . ($value["quote"] ?? "") . " ") : "");
                        } else {
                            $string .= " " . str_replace(":", "", $key) . "= " . ($value["quote"] ?? "") . "<?=" . ($value["value"] ?? "") . "?>" . ($value["quote"] ?? "");
                        }
                    }
                    if (isset($tag["case"])) {
                        if ($tag["case"] === "self") {
                            $string .= "/>";
                        }
                        if ($tag["case"] === "noclose") {
                            $string .= ">";
                        }
                    } else {
                        $string .= ">";
                    }
                    if (isset($tag['childern']) && $tag['childern']) {
                        $string .= $this->tostring($tag['childern']);
                    }
                    if (!isset($tag['case']) && isset($tag["tag"]) && $tag["tag"] !== "") {
                        $string .= "</" . $tag["tag"] . ">";
                    }
                }
            }
            if (isset($tag['string'])) {
                $string .= $tag['string'];
            }
        }
        return $string;
    }
    public function phpFunction(string $tagname, array $attribute, string $html)
    {
        if ($tagname == "for") {
            return  "<?php foreach($" . $attribute['array'] . " as $" . $attribute['value'] . " ){?> " . $html . " <?php }?>";
        } elseif ($tagname == "find2d") {
            /*
                <?= $array[array_search($find, array_column($array,$col))][$getvalue] ?>
            */
            return  "<?= $" . $attribute['array'] . "[array_search($" . $attribute["find"] . ", array_column($" . $attribute["array"] . ",'" . $attribute["col"] . "'))][" . $attribute['getvalue'] . "] ?>";
        } elseif ($tagname == "find") {
            return  str_replace([], [], $html);
        } elseif ($tagname == "if") {
            return "<?php if($" . $attribute["condition"] . "){ ?>" .
                $html .
                "<?php } ?>";
        }
    }
}
