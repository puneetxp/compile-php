<?php

namespace Puneetxp\CompilePhp\Compile;

class htmlParser {

    public array $tags = [];
    private $status = null;
    public int $length;
    public $selfClosing = [
        'area',
        'base',
        'basefont',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'keygen',
        'link',
        'meta',
        'param',
        'source',
        'spacer',
        'track',
        'wbr',
    ];

    public function __construct(private $config, public string $htmlstring = "", public int $key = 0, public array $html = []) {
        if (count($this->html)) {
            $this->length = count($this->html);
        } else {
            $this->html = str_split($htmlstring);
            $this->length = strlen($htmlstring);
        }
    }

    private $activetag;
    private $debug = false;
    private $string = "";

    public function parse(bool $debug = null) {
        $this->debug = $debug;
        while (
        $this->length > $this->key &&
        $this->checktagisclose(true, $this->string)
        ) {
            if ($this->checktagisopen()) {
                $this->addstring($this->string);
                $this->string = "";
                $this->next("checkitisopen");
                $this->checktag();
            } else {
                $this->string .= $this->html[$this->key];
                $this->next($this->string);
            }
        }
        if (isset($this->status)) {
            $this->closetag(true);
        }
        return $this;
    }

    private function checktag() {
        while (preg_match("/[A-Za-z\-\.0-9]/m", $this->html[$this->key])) {
            $this->settag();
            $this->next();
        }
        $this->status = "pending";
        while ($this->length > $this->key && $this->activetag && $this->status && ($this->status == "open" || $this->status == "pending") && $this->checktagisclose()) {
            if (!$this->checktagisopen()) {
                if ($this->html[$this->key] === " ") {
                    $this->next();
                    $this->addattribute();
                    // $this->activetag["tag"] = ($this->activetag["tag"] ?? "") . $this->html[$this->key];
                } elseif ($this->html[$this->key] . $this->html[$this->key + 1] === "/>") {
                    $this->activetag["case"] = "self";
                    $this->closetag(true);
                    $this->next();
                } elseif ($this->html[$this->key] === ">") {
                    $this->next();
                    $this->closetag();
                    if ($this->status !== "close" && $this->activetag) {
                        $child = (new self(key: $this->key, html: $this->html, config: $this->config))->parse($this->debug);
                        $this->activetag["childern"] = [...$this->activetag["childern"] ?? [], ...$child->tags];
                        $this->next("child", key: $child->key);
                    }
                } else {
                    $this->next();
                }
            }
        }
    }

    public function addattribute() {
        $attribute = "";
        $this->activetag["attribute"] = [];
        while ($this->activetag && isset($this->status) && $this->status !== "open" && $this->status !== "close") {
            if (!$this->checktagisopen()) {
                if ($this->html[$this->key] == "=") {
                    $this->next("equal");
                    $this->activetag["attribute"][$attribute] = ["quote" => '', "value" => ''];
                    if ($this->html[$this->key] == "'" || $this->html[$this->key] == '"') {
                        if ($this->html[$this->key] == '"') {
                            $this->next("suspect");
                            $this->activetag["attribute"][$attribute]["quote"] = '"';
                            while ($this->html[$this->key] != '"') {
                                $this->activetag["attribute"][$attribute]["value"] .= $this->html[$this->key];
                                $this->next();
                            }
                        } elseif ($this->html[$this->key] == "'") {
                            $this->next();
                            $this->activetag["attribute"][$attribute]["quote"] = "'";
                            while ($this->html[$this->key] != "'") {
                                $this->activetag["attribute"][$attribute]["value"] .= $this->html[$this->key];
                                $this->next();
                            }
                        }
                        $this->next();
                    } else {
                        while ($this->html[$this->key] !== " " && $this->html[$this->key] !== ">" && $this->status !== "open" && $this->status !== "close") {
                            if (!$this->checktagisopen()) {
                                $this->activetag["attribute"][$attribute]["value"] .= $this->html[$this->key];
                            }
                            $this->next();
                        }
                    }
                    $attribute = "";
                } elseif ($this->html[$this->key] == " ") {
                    if (chop($attribute) !== "") {
                        //print_r($this->html[$this->key]);
                        $this->activetag["attribute"][$attribute] = ["value" => "", "quote" => ''];
                        $attribute = '';
                    }
                    $this->next();
                } elseif ($this->html[$this->key] == ">") {
                    //print_r("open is" . $this->html[$this->key] . "");
                    $this->status = "open";
                } else {
                    if ($this->html[$this->key] !== "\n" && $this->html[$this->key] !== " ") {
                        $attribute .= $this->html[$this->key];
                    }
                    $this->next();
                }
            }
        }
    }

    public function addstring(string $string) {
        // print_r($string);
        if (chop($string) !== "") {
            if ($this->activetag) {
                $this->tagtostring($string);
            } else {
                array_push($this->tags, ["tag" => "", "string" => $string]);
            }
        }
    }

    private function tagtostring(string $addtionalstring = "") {
        if ($this->activetag) {
            $string = $this->activetag["tag"] ?? "";
            foreach ($this->activetag["attribute"] ?? [] as $key => $value) {
                $string .= " " . $key . "=" . $value["quote"] ?? "" . $value["value"] ?? "" . $value["quote"] ?? "" . " ";
            }
            $this->activetag = null;
            if (chop($string . $addtionalstring) != "") {
                array_push($this->tags, ["tag" => "", "string" => $string . $addtionalstring]);
            }
        }
    }

    private function checktagisopen() {
        if ($this->html[$this->key] == "<") {
            if (preg_match("/[A-Za-z]/", $this->html[$this->key + 1])) {
                if (isset($this->status) && $this->status == "open") {
                    $this->closetag(true);
                } else {
                    $this->tagtostring();
                    return true;
                }
            }
        }
        return false;
    }

    private function settag() {
        $this->activetag["tag"] = ($this->activetag["tag"] ?? "") . $this->html[$this->key];
        //print_r("\n" . $this->activetag["tag"] . "\n");
    }

    private function next($any = null, $key = null) {
        //print_r($any);
        $this->key++;
        if ($key) {
            $this->key = $key;
        }
        //print_r($this->html[$this->key]);
    }

    private function closetag(bool $bool = false, string $print = null, $additionalattribute = []) {
        foreach ($additionalattribute as $key => $value) {
            $this->activetag[$key] = $value;
        }
        if (in_array($this->activetag["tag"] ?? '', $this->selfClosing)) {
            $this->aliasoverwrite();
            $this->activetag["case"] = "self";
            $this->tags[] = $this->activetag;
            $this->status = null;
            $this->activetag = null;
            $this->string = '';
        } elseif ($bool) {
            $this->aliasoverwrite();
            $this->tags[] = $this->activetag;
            $this->status = null;
            $this->activetag = null;
            $this->string = '';
        } else {
            $this->status = "open";
        }
    }

    private function aliasoverwrite() {
        if (isset($this->activetag["tag"])) {
            if (strlen($this->activetag["tag"]) > 1 && $this->activetag["tag"][0] . $this->activetag["tag"][1] == "t-") {
                $this->activetag["tag"] = "t-" . implode(".", array_map(fn ($v) =>
                in_array($v, $this->config['alias']) ?
                    str_replace($v, array_search($v, $this->config['alias']), $v) :
                    $v, explode(".",  str_replace("t-", "", $this->activetag["tag"]))));
            }
        }
    }

    private function checktagisclose($close = false, $string = null) {
        if ($this->length > $this->key + 1) {
            $end = $this->html[$this->key] . $this->html[$this->key + 1] ?? "";
            //   print_r($end . "\n");
            $x = $end == "</";
            if ($x) {
                if ($close) {
                    // print_r($this->html[$this->key] . "\n");
                    //print_r($string . "\nok\n");
                    $this->addstring($string);
                } elseif (isset($this->activetag)) {
                    $key = $this->key;
                    $this->key += 2;
                    $string = "";
                    while ($this->length > $this->key && $this->html[$this->key] !== ">") {
                        $string .= $this->html[$this->key];
                        $this->next();
                    }
                    //print_r("String " . $string . "\n");
                    //print_r($this->activetag);
                    //print_r("ActiveTags " . $this->activetag["tag"], "\n");
                    if ($string == ($this->activetag["tag"] ?? '')) {
                        $this->next();
                        $this->closetag(true);
                    } else {
                        $this->key = $key;
                        if ($this->activetag['tag'] == "p") {
                            $this->closetag(true, additionalattribute: ["case" => "noclose"]);
                        } elseif (str_starts_with($this->activetag["tag"], "t-")) {
                            if (isset($this->activetag["child"])) {
                                $child = $this->activetag["child"];
                                unset($this->activetag["child"]);
                                $this->closetag(true, additionalattribute: ["case" => "noclose"]);
                                $this->tags[] = $child;
                            }
                        } else {
                            $this->closetag(true);
                        }
                    }
                } else {
                    $this->addstring($string);
                }
            }
            return !$x;
        }
    }

    public function tostring($tags = null) {
        $string = "";
        $tags = $tags ?? $this->tags;
        foreach ($tags as $tag) {
            if (isset($tag["tag"]) && $tag["tag"] !== "") {
                if (isset($tag["start"])) {
                    $string .= $tag["start"];
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
                }
            }
            if (isset($tag['string'])) {
                $string .= $tag['string'];
            }
            if (isset($tag['childern'])) {
                $string .= $this->tostring($tag['childern']);
            }
            if (!isset($tag['case']) && isset($tag["tag"]) && $tag["tag"] !== "") {
                $string .= "</" . $tag["tag"] . ">";
            }
        }
        return $string;
    }

    public function startwithtags(string $string, $tags = null): array {
        $_tags = [];
        $tags = $tags ?? $this->tags;
        foreach ($tags as $tag) {
            if (isset($tag["tag"])) {
                if (strlen($tag["tag"]) > 2 && $string == $tag["tag"][0] . $tag["tag"][1]) {
                    $tag["tag"] = str_replace($string, "", $tag["tag"]);
                    $_tags[] = $tag["tag"];
                }
            }
            if (isset($tag['childern'])) {
                $_tags = [...$_tags, ...$this->startwithtags($string, tags: $tag['childern'])];
            }
        }
        return $_tags;
    }
}
