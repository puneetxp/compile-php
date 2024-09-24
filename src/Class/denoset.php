<?php

namespace Puneetxp\CompilePhp\Class;

class denoset {

    public function __construct(public $table, public $json) {

    }

    function denoController($table, $curd, $key = '') {
        return 'import { response ,Session} from "../../../dep.ts";
import { ' . ucfirst($table['name']) . '$ } from "../../Model/' . ucfirst($table['name']) . '.ts";
export class ' . ucfirst($key) . ucfirst($table['name']) . 'Controller {' .
                (in_array("a", $curd) ? '
   static async all(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.all();
      return response.JSON(' . $table['name'] . '.items, session);
   }' : '') .
                (in_array("w", $curd) ? '
   static async where(session: Session) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.where(await session.req.json()).get();
      return response.JSON(' . $table['name'] . '.items, session);
   }' : '') .
                (in_array("r", $curd) ? '
   static async show(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.find(param[0].toString());
      return response.JSON(' . $table['name'] . '.item, session);
   }' : '') .
                (in_array("c", $curd) ? '
   static async store(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.create([await session.req.json()]);
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                (in_array("u", $curd) ? '
   static async update(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.where({ id: [param[0]] }).update(await session.req.json());
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                (in_array("p", $curd) ? '
   static async upsert(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.create(await session.req.json());
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                (in_array("d", $curd) ? '
   static async delete(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$.delete({ col: "id", value: [param[0]] });
      return response.JSON(' . $table['name'] . ', session);
   }' : '') . '
}';
    }

    function denoModel($table) {
        $nullable = [];
        $import = '';
        foreach ($table['data'] as $sql) {
            if (str_contains($sql['sql_attribute'], 'NOT NULL')) {

            } else {
                $nullable[] = $sql['name'];
            }
        }
        $relations_key = array_keys($table['relations']);
        $relations = '';
        if (count($relations_key) > 0) {
            $relations .= '{';
            $t = 0;
            foreach ($relations_key as $key) {
                if ($t == 0 || $t == count($relations_key)) {
                    $relations .= '';
                } else {
                    $relations .= ',';
                }
                $relations .= "'$key':{";
                $f = 0;
                foreach ($table['relations'][$key] as $id => $value) {
                    if ($f == 0 || $f == count($table['relations'][$key])) {
                        $relations .= '';
                    } else {
                        $relations .= ',';
                    }
                    $relations .= "'$id'" . ':'
                            . "'$value'";
                    ++$f;
                }
                $relations .= ",'callback'" . ':()=>' . ucfirst($key) . "$" . '}';
                $import .= "import { " . ucfirst($key) . "$ } from './" . ucfirst($key) . ".ts';
";
                ++$t;
            }
            $relations .= '}';
        }
        $import .= "import { " . ucfirst($table["name"]) . ' } from "../../App/Interface/Model' . "/" . ucfirst($table["name"]) . '.ts";';
        $fillable = "['";
        $fillable_array = [];
        foreach ($table['data'] as $value) {
            if (!isset($value['fillable'])) {
                $fillable_array[] = $value['name'];
            } else {
                if ($value['fillable'] == 'true') {
                    $fillable_array[] = $value['name'];
                }
            }
        }
        $fillable .= implode("','", $fillable_array);
        $fillable .= "']";
        return "import { Model, relation } from '../../dep.ts';
" . $import . "
class Standard extends Model<" . ucfirst($table["name"]) . "> {
  name = '" . $table['name'] . "';
  table = '" . $table['table'] . "';
  nullable: string[] = " . json_encode($nullable) . ";
  fillable: string[] = " . $fillable . ";
  model: string[] = " . json_encode(array_column($table['data'], 'name')) . ";
  relations:  Record<string,  relation>  = " . ($relations == '' ? '[]' : $relations) . ";
}
export const " . ucfirst($table['name']) . "$: Standard = new Standard().set('" . $table['table'] . "');";
    }

    public $For = [];
    public $all = [];

    function denoset() {
        index::templatecopy("deno", "deno");
        $GLOBALS['For'] = [];
        foreach ($this->table as $item) {
            $model = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            $model_write = $this->denoModel($item);
            index::createfile($_ENV['dir'] . "/deno/App/Interface/Model/" . ucfirst($item['name']) . '.ts', index::interface_set($item));
            fwrite($model, $model_write);
            if (isset($item['crud']['roles'])) {
                foreach ($item['crud']['roles'] as $key => $value) {
                    $this->denowritec($item, $value, $key, $key);
                }
            }
            if (isset($item['crud']['isuper'])) {
                $this->denowritec($item, $item['crud']['isuper'], 'isuper', 'isuper');
            }
            if (isset($item['crud']['islogin'])) {
                $this->denowritec($item, $item['crud']['islogin'], 'islogin');
            }
            if (isset($item['crud']['public'])) {
                $this->denowritec($item, $item['crud']['public'], 'public');
            }
        }
        if (isset($GLOBALS['For']['roles'])) {
            foreach ($GLOBALS['For']['roles'] as $key => $value) {
                $this->denoroterc($key, $value);
            }
        }
        if (isset($GLOBALS['For']['isuper'])) {
            $this->denoroterc('isisuper', $GLOBALS['For']['isuper']);
        }
        if (isset($GLOBALS['For']['islogin'])) {
            $this->denoroterc('islogin', $GLOBALS['For']['islogin']);
        }
        if (isset($GLOBALS['For']['public'])) {
            $this->denoroterc('ipublic', $GLOBALS['For']['public']);
        }
        index::createfile($_ENV['dir'] . '/deno/.env', implode("\n", [
            "DBHOST=" . $this->json['env']['dbhost'],
            "DBUSER=" . $this->json['env']['dbuser'],
            "DBPWD=" . $this->json['env']['dbpwd'],
            "DBNAME=" . $this->json['env']['dbname'],
            "HOST=" . $this->json['env']['host']
        ]));
    }

    function denowritec($item, $value, $key, $role = '') {
        if ($role == '') {
            if (!isset($GLOBALS['For'][$key])) {
                $GLOBALS['For'][$key] = ['path' => $key, "controller" => [], 'child' => []];
            }
            $GLOBALS['For'][$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
            $GLOBALS['For'][$key]["controller"][] = 'import { ' . ucfirst($key) . ucfirst($item['name']) . 'Controller} from "../' . ucfirst('controller/') . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts";';
        } else {
            if (!isset($GLOBALS['For']['roles'])) {
                $GLOBALS['For']['roles'] = [];
            }
            if (!isset($GLOBALS['For']['roles'][$key])) {
                $GLOBALS['For']['roles'][$key] = ["path" => '/' . $key];
                $GLOBALS['For']["roles"][$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
                $GLOBALS['For']["roles"][$key]["controller"][] = 'import {' . ucfirst($key) . ucfirst($item['name']) . 'Controller} from "../' . ucfirst('controller/') . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts";';
            } else {
                $GLOBALS['For']["roles"][$key]["child"][] = ['path' => "/" . $item['name'], "crud" => ["class" => ucfirst($key) . ucfirst($item['name']) . ucfirst("controller"), "crud" => $value]];
                $GLOBALS['For']["roles"][$key]["controller"][] = 'import {' . ucfirst($key) . ucfirst($item['name']) . 'Controller} from "../' . ucfirst('controller/') . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts";';
            }
        }
        $controller_write = $this->denoController($item, $value, $key);
        // print_r($_ENV['dir'] . "/deno/App/" . ucfirst('controller/') . ucfirst($key) . '/' .  ucfirst($item['name']) . 'Controller.ts');
        $controller = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('controller/') . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts');
        fwrite($controller, $controller_write);
    }

    function denoroterc($key, $route) {
        if ($key != "ipublic" && $key != "islogin") {
            $route['roles'] = [$key];
        }
        $controller = $route['controller'];
        unset($route['controller']);
        $route_write = implode("
", $controller) . '
   ' . preg_replace('/"class": "(.+?)"/', '"class":${1}', str_replace("\/", "/", 'export const ' . $key . ' = [' . json_encode($route, JSON_PRETTY_PRINT) . '];'));
        $route = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('routes/') . ucfirst($key) . '.ts');
        fwrite($route, $route_write);
    }
}
