<?php

namespace Puneetxp\CompilePhp\Class;

class phpset {

    private $routes = [];

    public function __construct(public $table, public $json) {
        foreach ($table as $item) {
            $model = index::fopen_dir($_ENV['dir'] . "/php/App/" . ucfirst('model/') . ucfirst($item['name']) . '.php');
            $model_write = $this->phpModel($item);
            fwrite($model, $model_write);
            if (isset($item['crud']['roles'])) {
                foreach ($item['crud']['roles'] as $key => $value) {
                    $this->phpwritec(item: $item, value: $value, key: $key, role: "$key", prefix: "i");
                }
            }
            if (isset($item['crud']['isuper'])) {
                $this->phpwritec(item: $item, value: $item['crud']['isuper'], key: 'isuper', role: 'isuper');
            }
            if (isset($item['crud']['islogin'])) {
                $this->phpwritec(item: $item, value: $item['crud']['islogin'], key: 'islogin', role: "");
            }
            if (isset($item['crud']['ipublic'])) {
                $this->phpwritec(item: $item, value: $item['crud']['ipublic'], key: 'ipublic', role: "");
            }
        }
        if (isset($this->routes['roles'])) {
            foreach ($this->routes['roles'] as $key => $value) {
                if ($key != "isuper" && $key != "islogin" && $key != "ipublic") {
                    $this->phproterc($key, $value, "i");
                }
            }
        }
        if (isset($this->routes['isuper'])) {
            $this->phproterc('isuper', $this->routes['isuper']);
        }
        if (isset($this->routes['islogin'])) {
            $this->phproterc('islogin', $this->routes['islogin']);
        }
        if (isset($this->routes['ipublic'])) {
            $this->phproterc('ipublic', $this->routes['ipublic']);
        }
        $this->phpenv($json);
        index::templatecopy("php", "php");
    }

    function phpmodel($table) {
        $relations_key = array_keys($table['relations']);
        $relations = '';
        if (count($relations_key) > 0) {
            $relations .= '[';
            $t = 0;
            foreach ($relations_key as $key) {
                if ($t == 0 || $t == count($relations_key)) {
                    $relations .= '';
                } else {
                    $relations .= ',';
                }
                $relations .= "'$key'=>[";
                $f = 0;
                $callback = null;
                foreach ($table['relations'][$key] as $id => $value) {
                    if ($id === 'callback') {
                        $callback = $value;
                        continue;
                    }
                    if ($f == 0 || $f == count($table['relations'][$key])) {
                        $relations .= '';
                    } else {
                        $relations .= ',';
                    }
                    $relations .= "'$id'" . '=>'
                            . "'$value'";
                    ++$f;
                }
                $callbackClass = $callback ? ucfirst($callback) : ucfirst($key);
                $relations .= ($f > 0 ? ',' : '') . "'callback'" . '=>' . $callbackClass . "::class" . ']';
                ++$t;
            }
            $relations .= ']';
        }
        $nullable = array_column(array_filter($table["data"], fn($r) => !(isset($r["sql_attribute"]) && (str_contains($r['sql_attribute'], 'NOT NULL') || str_contains($r['sql_attribute'], 'PRIMARY')))), "name");
        $fillable = array_column(array_filter($table["data"], fn($r) => !isset($r["fillable"])), "name");
        return index::php_w('
namespace App\Model;

use The\Model;

class ' . ucfirst($table['name']) . ' extends Model {
    public $model = ' . json_encode(array_column($table['data'], 'name')) . ';
    public $name = "' . $table['name'] . '";
    public $nullable = ' . json_encode($nullable) . ';
    protected $enable = ' . (in_array("enable", array_column($table['data'], 'name')) ? 'true' : 'false') . ';
    protected $table = "' . $table['table'] . '";
    protected $relations = ' . ($relations == '' ? '""' : $relations) . ';
    protected $fillable = ' . json_encode($fillable) . ';
}');
    }

    function phpdefaultController(array $table, array $curd, string $key = '', string $prefix = "") {
        return index::php_w('
namespace App\Controller\\' . ucfirst($prefix . $key) . ';

use App\Model\{
    ' . ucfirst($table['name']) . '
};

class ' . ucfirst($prefix . $key) . ucfirst($table['name']) . 'Controller {' . (in_array("a", $curd) ? '

    public static function all() {
        if (isset($_GET["latest"])) {
            return ' . ucfirst($table['name']) . '::wherec([["updated_at", ">", $_GET["latest"]]])->get();
        }
        return ' . ucfirst($table['name']) . '::all();
    }' : '') .
                        (in_array("w", $curd) ? '

    public static function where() {
        return ' . ucfirst($table['name']) . '::where(json_decode($_POST["' . $table['table'] . '"]))->getsInserted();
    }' : '') .
                        (in_array("r", $curd) ? '

    public static function show($id) {
        return ' . ucfirst($table['name']) . '::find($id);
    }' : '') .
                        (in_array("c", $curd) ? '

    public static function store() {
        return ' . ucfirst($table['name']) . '::create($_POST)->getInserted();
    }' : '') .
                        (in_array("u", $curd) ? '

    public static function update($id) {
        ' . ucfirst($table['name']) . '::where(["id" => [$id]])->update($_POST);
        return ' . ucfirst($table['name']) . '::find($id);
    }' : '') .
                        (in_array("p", $curd) ? '

    public static function upsert() {
        return ' . ucfirst($table['name']) . '::upsert(json_decode($_POST["' . $table['table'] . '"]))->getsInserted();
    }' : '') .
                        (in_array("d", $curd) ? '

    public static function delete($id) {
        ' . ucfirst($table['name']) . '::delete(["id" => $id]);
        return $id;
    }' : '') . '
}
');
    }

    function phpphotoController(array $table, array $curd, string $key = '', string $prefix = "") {
        return index::php_w('
namespace App\Controller\\' . ucfirst($prefix . $key) . ';

use The\{
    FileAct,
    Img,
    Response
};
use App\Model\{
    ' . ucfirst($table['name']) . '
};

class ' . ucfirst($prefix . $key) . ucfirst($table['name']) . 'Controller {' . (in_array("a", $curd) ? '

    public static function all() {
        if (isset($_GET["latest"])) {
            return ' . ucfirst($table['name']) . '::wherec([["updated_at", ">", $_GET["latest"]]])->get();
        }
        return ' . ucfirst($table['name']) . '::all();
    }' : '') .
                        (in_array("r", $curd) ? '

    public static function show($id) {
        return ' . ucfirst($table['name']) . '::find($id);
    }' : '') .
                        (in_array("c", $curd) ? '

    public static function store() {
        $file = FileAct::init($_FILES[' . '"photo"' . '])->public("")->fileupload($_FILES[' . '"photo"' . '], $_POST[' . '"name"' . ']);
        return ' . ucfirst($table['name']) . '::create($file)->getInserted();
    }' : '') .
                        (in_array("u", $curd) ? '

    public static function update($id) {
        $file = FileAct::init($_FILES[' . '"photo"' . '])->public("")->fileupload($_FILES[' . '"photo"' . '], $_POST[' . '"name"' . ']);
        Photo::where(["id" => [$id]])->update($file);
        return ' . ucfirst($table['name']) . '::find($id);
    }' : '') .
                        (in_array("p", $curd) ? '

    public static function upsert() {
        if (isset($_POST["' . 'dir' . '"])) {
            if ($_POST["' . 'dir' . '"] !== "") {
                $files = FileAct::init($_FILES["' . 'photo' . '"])->public($_POST["' . 'dir' . '"])->ups()->files;
                ' . (isset($table['type']['version']) && count($table['type']['version']) > 0 ? ('foreach ($files as $file) {
                    if (getimagesize($file["path"])) {
                    ' . (implode('', array_map(fn($key, $value) => '    Img::webpImage(source: $file[' . '"path"' . '], destination: $file[' . '"dir"' . '] . DIRECTORY_SEPARATOR . "' . $key . '/" . $file[' . '"name"' . '], x: ' . $value['width'] . ', quality: ' . $value['quality'] . ');
                    ', array_keys($table['type']['version']), array_values($table['type']['version'])))) . '}
                }') : "") . '
                return Photo::upsert($files)->getsInserted();
            }
        }
        return Response::bad_req("It seem you Missed Directory");
    }' : '') .
                        (in_array("d", $curd) ? '

    public static function delete($id) {
        $' . $table['name'] . ' = ' . ucfirst($table['name']) . '::find($id)->array();
        if ($' . $table['name'] . ') {
            is_file($' . $table['name'] . '["path"]) ? unlink($' . $table['name'] . '["path"]) : "";
            ' . ucfirst($table['name']) . '::delete(["id" => $id]);
        }
        return $id;
    }' : '') . '
}
');
    }

    function phpController(array $table, array $curd, string $key = '', string $prefix = "") {
        if (isset($table["type"])) {
            if ($table["type"]['name'] == "file") {
                
            } elseif ($table["type"]['name'] == "photo") {
                return $this->phpphotoController($table, $curd, $key, $prefix);
            }
        } else {
            return $this->phpdefaultController($table, $curd, $key, $prefix);
        }
    }

    function phpwritec($item, $value, $key, $role = '', $prefix = '') {
        $json_set = json_decode(file_get_contents($_ENV['dir'] . '/config.json'), TRUE);
        if ($prefix == "") {
            if (!isset($this->routes[$key])) {
                $this->routes[$key] = ['path' => "/" . $key, "controller" => [], 'child' => []];
            }
            $this->routes[$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
            $this->routes[$key]["controller"][] = 'use App\\' . ucfirst('controller\\') . ucfirst($key) . '\\' . ucfirst($key) . ucfirst($item['name']) . 'Controller;';
        } else {
            if (!isset($this->routes['roles'])) {
                $this->routes['roles'] = [];
            }
            if (!isset($this->routes['roles'][$key])) {
                $this->routes['roles'][$key] = ["path" => '/' . $key];
                $this->routes["roles"][$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($prefix . $key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
                $this->routes["roles"][$key]["controller"][] = 'use App\\' . ucfirst('controller\\') . ucfirst($prefix . $key) . '\\' . ucfirst($prefix . $key) . ucfirst($item['name']) . 'Controller;';
            } else {
                $this->routes["roles"][$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($prefix . $key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
                $this->routes["roles"][$key]["controller"][] = 'use App\\' . ucfirst('controller\\') . ucfirst($prefix . $key) . '\\' . ucfirst($prefix . $key) . ucfirst($item['name']) . 'Controller;';
            }
        }
        if (!isset($json_set["table"][$item["name"]])) {
            $controller_write = $this->phpController($item, $value, $key, $prefix);
            $controller = index::fopen_dir($_ENV['dir'] . "/php/App/" . ucfirst('controller/') . ucfirst($prefix . $key) . '/' . ucfirst($prefix . $key) . ucfirst($item['name']) . 'Controller.php');
            fwrite($controller, $controller_write);
        }
    }

    function phproterc($key, $route, $prefix = "") {
        if ($key != "ipublic" && $key != "islogin") {
            $route['roles'] = [$key];
        }
        $controller = $route['controller'];
        unset($route['controller']);
        $route = var_export($route, true);
        $route_controller = implode("\n", $controller);
        $routx = index::fopen_dir($_ENV['dir'] . "/php/" . ucfirst("routes/pre/api/") . ucfirst($prefix . $key) . '.php');
        fwrite($routx, preg_replace("/'class' => '(.+?)'/", '"class" => ${1}::class', "<?php\n\n$route_controller \n\n$" . $prefix . "$key = $route;\n\n"));
    }

    function phpenv($json) {
        $env = index::fopen_dir($_ENV['dir'] . "/php/env.php");
        fwrite($env, index::php_wrapper(implode("", array_map(function ($key, $value) {
                                    return "
      define('$key', " . json_encode($value) . ");";
                                }, array_keys($json["env"]), $json["env"]))));
    }
}
