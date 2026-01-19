<?php
namespace Puneetxp\CompilePhp\Compile;

use Puneetxp\CompilePhp\Class\index;

class phpCompile
{
    public $file;
    public function __construct(private string $destination, private $files)
    {
        foreach ($this->files as $index => $template) {
            $this->file = $template;
            $constructorSignature = $this->buildConstructorSignature($template);
            $classContents = $this->buildClassContents($template, $constructorSignature);

            index::createfile(
                $this->destination . DIRECTORY_SEPARATOR . $index . "Page.php",
                $classContents
            );
        }
    }

    private function buildConstructorSignature(object $template): string
    {
        $templateParams = (array) ($template->parameter ?? []);
        $hasPageParam = array_key_exists('page', $templateParams);

        $parameterParts = array_map(
            fn($paramValue, $paramKey) => '$' . $paramKey . ' = ' . $this->exportParameterDefault($paramValue),
            array_values($templateParams),
            array_keys($templateParams)
        );

        $constructorParams = [' $data = []', ' $attribute = []', ' $child = ""'];
        if (!$hasPageParam) {
            $constructorParams[] = ' $page = []';
        }
        if (!empty($parameterParts)) {
            $constructorParams = array_merge($constructorParams, $parameterParts);
        }

        return implode(',', $constructorParams);
    }

    private function buildClassContents(object $template, string $constructorSignature): string
    {
        $namespace = str_replace('/', '\\', $template->directory);
        $imports = $this->generateImports($template);
        $className = $template->filename . "Page";
        $body = preg_replace(
            "/\{\{([\s\S]*?)\}\}/m",
            '<?= ' . "$1" . ' ?>',
            $this->tostring($template->html->tags)
        );

        return "<?php namespace $namespace; $imports class $className { public function __construct(" .
            $constructorSignature . ")  {?> " .
            $body .
            "<?php }}";
    }

    private function generateImports(object $template): string
    {
        $imports = array_unique($template->t_tag ?? []);
        return implode(
            "",
            array_map(
                fn($value) => "use view\\" . str_replace(".", "\\", $value) . "Page; ",
                $imports
            )
        );
    }

    private function exportParameterDefault($value): string
    {
        if (is_array($value) || is_object($value) || is_null($value)) {
            return var_export($value, true);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return var_export($value, true);
    }
    public function tostring($file)
    {
        $string = "";
        $closureUseVars = [' $attribute', ' $data', ' $child', ' $page'];
        foreach (array_keys($this->file->parameter ?? []) as $paramKey) {
            $var = ' $' . $paramKey;
            if (!in_array($var, $closureUseVars, true)) {
                $closureUseVars[] = $var;
            }
        }
        $closureUse = implode(',', $closureUseVars);
        foreach ($file as $tag) {
            if (isset($tag["tag"]) && $tag["tag"] !== "") {
                if (($tag["tag"][0] ?? '') . ($tag["tag"][1] ?? '') == "t-") {
                    $x = explode(".", str_replace("t-", "", $tag["tag"]));
                    $x = $x[count($x) - 1];
                    $className = $x . "Page";
                    $parameter = '';
                    $native = '[]';
                    if (isset($tag['attribute'])) {
                        $y =  array_filter($tag['attribute'], fn($x) => count(str_split($x)) && (str_split($x)[0] == ":"), ARRAY_FILTER_USE_KEY);
                        $componentHasPage = false;
                        if (count($y)) {
                            $parameter = implode(
                                '',
                                array_map(
                                    fn($k, $value) =>
                                    (function($k, $value, &$componentHasPage) {
                                        $prop = str_replace(':', '', $k);
                                        if ($prop === 'page') {
                                            $componentHasPage = true;
                                        }
                                        return $prop . ": " .
                                            (is_array($value['value']) || is_object($value['value']) ? var_export($value['value'], true) : (preg_match("/\d/", $value['value']) ? $value['value'] : ($value['quote'] . $value['value'] . $value['quote']))) . ",";
                                    })($k, $value, $componentHasPage),
                                    array_keys($y),
                                    $y
                                )
                            );
                        }
                        $y = array_filter($tag['attribute'], fn($x) => count(str_split($x)) && (str_split($x)[0]  != ":"), ARRAY_FILTER_USE_KEY);
                        if (count($y)) {
                            $native = '[' . implode(',', array_map(
                                fn($key, $value) => var_export($key, true) . ' => ' . var_export($value["value"], true),
                                array_keys($y),
                                $y
                            )) . ']';
                        }
                    }
                    if (!($componentHasPage ?? false)) {
                        $parameter .= "page: \$page,";
                    }
                    $string .= "<?php new $className(" . "child: function() use (" . $closureUse . " ) {?>" .
                        ($this->tostring($tag['childern'] ?? []) ?? '') .
                        "<?php }," .
                        $parameter .
                        "attribute: " . $native
                        . ");?>";
                } elseif (($tag["tag"][0] ?? '') . ($tag["tag"][1] ?? '') == "f-") {
                    $string .= $this->phpFunction(str_replace("f-", "", $tag["tag"]), array_map(fn($value) => $value["value"], $tag['attribute'] ?? []), $this->tostring($tag['childern'] ?? []));
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
        return str_replace('?><?php','',$string);
    }    
    public function phpFunction(string $tagname, array $attribute, string $html)
    {
        if ($tagname == "for") {
            $keyPart = isset($attribute['key']) ? '$' . $attribute['key'] . ' => ' : '';
            return  "<?php foreach($" . $attribute['array'] . " as " . $keyPart . "$" . $attribute['value'] . " ){?> " . $html . " <?php }?>";
        } elseif ($tagname == "find2d") {
            /*
                <?= $array[array_search($find, array_column($array,$col))][$getvalue] ?>
            */
            return  "<?= $" . $attribute['array'] . "[array_search($" . $attribute["find"] . ", array_column($" . $attribute["array"] . ",'" . $attribute["col"] . "'))][" . $attribute['getvalue'] . "] ?>";
        } elseif ($tagname == "find") {
            return  str_replace([], [], $html);
        } elseif ($tagname == "if") {
            return "<?php if(" . (str_starts_with($attribute["condition"], "$") ? $attribute["condition"] : "$" . $attribute["condition"])  . "){ ?>" .
                $html .
                "<?php } ?>";
        } elseif ($tagname == "elseif") {
            return "<?php elseif(" . (str_starts_with($attribute["condition"], "$") ? $attribute["condition"] : "$" . $attribute["condition"])  . "){ ?>" .
                $html ."<?php } ?>";
        } elseif ($tagname == "else") {
            return "<?php else { ?>
            $html
            <?php } ?>";
        }
    }
}
