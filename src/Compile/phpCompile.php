<?php

namespace Puneetxp\CompilePhp\Compile;

use Puneetxp\CompilePhp\Class\index;

class phpCompile
{
    public function __construct(private string $destination, private $files)
    {
        foreach ($this->files as $index => $value) {
            index::createfile(
                $this->destination . DIRECTORY_SEPARATOR . $index . ".php",
                "<?php namespace " .
                    str_replace('/', '\\', $value->directory) . "; " .
                    implode("", array_map(fn ($value) => "use view\\" . str_replace(".", "\\", $value) . "; ", $value->t_tag)) .
                    "class $value->filename { public function __construct(" .
                    ' $data = [], $attribute = [], $child = "",' .
                    implode(",", (array_map(fn ($value, $key) =>
                    '$' . "$key = " .
                        (is_array($value) || is_object($value) ? var_export($value, true) : (preg_match("/\d/", $value) ? $value : ('"' . "$value" . '"'))), array_values($value->parameter), array_keys($value->parameter)))) .
                    ")  {
                    ?> " . $this->tostring($value->html->tags) . "<?php }}"
            );
        }
    }
    public function tostring($tags)
    {
        $string = "";
        foreach ($tags as $tag) {
            if (isset($tag["tag"]) && $tag["tag"] !== "") {
                if (($tag["tag"][0] ?? '') . ($tag["tag"][1] ?? '') == "t-") {
                    $x = explode(".", str_replace("t-", "", $tag["tag"]));
                    $x = $x[count($x) - 1];
                    $string .= "<?php new $x(" . "child: function(){?>" .
                        ($this->tostring($tag['childern'] ?? []) ?? '') .
                        "<?php },attribute: " . var_export(array_filter($tag['attribute'] ?? [], fn ($x) => isset($x[0]) && $x[0] !== ":"), true)
                        . var_export(array_filter($tag['attribute'] ?? [], fn ($x) => isset($x[0]) && $x[0] == ":"), true)
                        . " )";
                } else {
                    $string .= "<" . $tag["tag"];
                    foreach (($tag["attribute"] ?? []) as $key => $value) {
                        $string .= " " . $key . "=" . ($value["quote"] ?? "") . ($value["value"] ?? "") . ($value["quote"] ?? "") . " ";
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
}
