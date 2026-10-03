<?php

namespace Puneetxp\CompilePhp\Class;

class denoset {
    public function __construct(public $table, public $json, public $param = "string[]") {}

    function denoController($table, $curd, $key = '') {
        if ($this->param == "string[]") {
            return 'import { response ,Session} from "../../../dep.ts";
import { ' . ucfirst($table['name']) . '$ } from "../../Model/' . ucfirst($table['name']) . '.ts";
export class ' . ucfirst($key) . ucfirst($table['name']) . 'Controller {' .
                    (in_array("a", $curd) ? '
   static async all(session: Session, param?: URLPatternResult): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().all(param);
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("w", $curd) ? '
   static async where(session: Session) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().where(await session.req.json()).Item;
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("r", $curd) ? '
   static async show(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().find(param[0].toString());
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("c", $curd) ? '
   static async store(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().create(await session.req.json());
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("u", $curd) ? '
   static async update(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().update(
      { id: [param[0]] },
      await session.req.json(),
      );
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("p", $curd) ? '
   static async upsert(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().insert((await session.req.json()).data);
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') .
                    (in_array("d", $curd) ? '
   static async delete(session: Session, param: string[]) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().del({id: [param[0]] });
      return response.JSON( ' . $table['name'] . ' , session);
   }' : '') . '
}';
        } else {
            return 'import { response ,Session} from "../../../dep.ts";
import { ' . ucfirst($table['name']) . '$ } from "../../Model/' . ucfirst($table['name']) . '.ts";
export class ' . ucfirst($key) . ucfirst($table['name']) . 'Controller {' .
                    (in_array("a", $curd) ? '
   static async all(session: Session, param?: URLPatternResult): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().all(param);
      return response.JSON(' . $table['name'] . '.items, session);
   }' : '') .
                    (in_array("w", $curd) ? '
   static async where(session: Session) {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().where(await session.req.json()).get();
      return response.JSON(' . $table['name'] . '.items, session);
   }' : '') .
                    (in_array("r", $curd) ? '
   static async show(session: Session, param:' . $this->param . ') {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().find(param.pathname.groups.id?.toString());
      return response.JSON(' . $table['name'] . '.item, session);
   }' : '') .
                    (in_array("c", $curd) ? '
   static async store(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().create(await session.req.json());
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                    (in_array("u", $curd) ? '
   static async update(session: Session, param:' . $this->param . ') {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().where({ id: [param.pathname.groups.id] }).update(await session.req.json());
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                    (in_array("p", $curd) ? '
   static async upsert(session: Session): Promise<Response> {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().insert((await session.req.json()).data);
      return response.JSON(' . $table['name'] . ', session);
   }' : '') .
                    (in_array("d", $curd) ? '
   static async delete(session: Session, param:' . $this->param . ') {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().' . (in_array('deleted_at', array_column($table['data'], 'name'))
                        // soft delete only when the model has "additional": ["delete"]; otherwise the column does not exist
                        ? 'where({ id: [param.pathname.groups.id] }).update({ deleted_at: new Date() })'
                        : 'delete({ id: [param.pathname.groups.id] })') . ';
      return response.JSON(' . $table['name'] . ', session);
   }
   static async perma_delete(session: Session, param:' . $this->param . ') {
      const ' . $table['name'] . ' = await ' . ucfirst($table['name']) . '$().delete({ id: [param.pathname.groups.id] });
      return response.JSON(' . $table['name'] . ', session);
   }' : '') . '
}';
        }
    }

    function denoModel($table) {
        $nullable = [];
        $import = [];
        foreach ($table['data'] as $sql) {
            if (str_contains($sql['sql_attribute'], 'NOT NULL')) {
            } else {
                $nullable[] = $sql['name'];
            }
        }
        $relations_key = array_keys($table['relations'] ?? []);
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
                $c = false;
                foreach ($table['relations'][$key] as $id => $value) {
                    if ($id == "callback") {
                        $c = true;
                        $relations .= ",'callback'" . ':()=>' . ucfirst($value) . "$" ;
                        $import[] = "import { " . ucfirst($value) . "$ } from './" . ucfirst($value) . ".ts';";
                        ++$f;
                    } else {
                        if ($f == 0 || $f == count($table['relations'][$key])) {
                            $relations .= '';
                        } else {
                            $relations .= ',';
                        }
                        $relations .= "'$id'" . ':'
                                . "'$value'";
                        ++$f;
                    }
                }
                if ($c == false) {
                    $relations .= ",'callback'" . ':()=>' . ucfirst($key) . "$" ;
                    $import[] = "import { " . ucfirst($key) . "$ } from './" . ucfirst($key) . ".ts';";
                }
                ++$t;
                $relations .= "}";
            }
            $relations .= '}';
        }
        $import[] = "import { " . ucfirst($table["name"]) . ' } from "../../App/Interface/Model' . "/" . ucfirst($table["name"]) . '.ts";';
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
        return "import { Model } from '../../dep.ts';
" . implode("
", array_unique($import)) . "

class Standard extends Model<" . ucfirst($table["name"]) . "> {
    constructor() {
        super(
            '" . $table['name'] . "',
            '" . $table['table'] . "',
            " . json_encode($nullable) . ",
            " . $fillable . ",
            " . json_encode(array_column($table['data'], 'name')).",
            " . ($relations == '' ? '{}' : $relations)."
        );
    }
}
export const " . ucfirst($table['name']) . "$ = () => new Standard();";
    }

    public $For = [];
    public $all = [];

    /**
     * A crud entry is either a list of letters (all rows, as before)
     * or an object: { "can": [...], "under": "book" } or { "can": [...], "owner": "user_id" }.
     */
    static function access($value): array {
        return array_is_list($value) ? ['can' => $value] : $value + ['can' => []];
    }

    /** The crud entry of a model for one audience: "islogin", "public", "isuper" or a role name. */
    static function entryFor(array $model, string $audience) {
        return $model['access'][$audience] ?? $model['access']['roles'][$audience] ?? null;
    }

    /**
     * Owner column of a parent, for the SAME audience as the child:
     *   client.json  "islogin":   { "under": "book" }  → book.json "islogin":   { "owner": "user_id" }
     *   client.json  "executive": { "under": "book" }  → book.json "executive": { "owner": "executive_id" }
     * No default: if the parent does not say who owns it for that audience, stop.
     */
    function ownerOf(string $parent, string $child, string $audience): string {
        foreach ($this->table as $t) {
            if ($t['name'] === $parent) {
                $entry = self::entryFor($t, $audience);
                $owner = $entry === null ? null : (self::access($entry)['owner'] ?? null);
                if ($owner === null) {
                    throw new \Exception("$child.json \"$audience\" says \"under\": \"$parent\", but $parent.json has no \"owner\" for \"$audience\".");
                }
                return $owner;
            }
        }
        throw new \Exception("$child.json says \"under\": \"$parent\", but there is no $parent.json.");
    }

    function denoset() {
        index::templatecopy("deno", "deno");
        $GLOBALS['For'] = [];
        foreach ($this->table as $item) {
            $model = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            $model_write = $this->denoModel($item);
            index::createfile($_ENV['dir'] . "/deno/App/Interface/Model/" . ucfirst($item['name']) . '.ts', index::interface_set($item));
            fwrite($model, $model_write);
            // read "access" (full entries); "crud" only has the letters
            $access = $item['access'] ?? [];
            foreach ($access['roles'] ?? [] as $key => $value) {
                $this->denowritec($item, self::access($value), $key, $key);
            }
            if (isset($access['isuper'])) {
                $this->denowritec($item, self::access($access['isuper']), 'isuper', 'isuper');
            }
            if (isset($access['islogin'])) {
                $this->denowritec($item, self::access($access['islogin']), 'islogin');
            }
            if (isset($access['public'])) {
                $this->denowritec($item, self::access($access['public']), 'public');
            }
        }
        foreach ($GLOBALS['For']['roles'] ?? [] as $key => $value) {
            $this->denoroterc($key, $value);
        }
        if (isset($GLOBALS['For']['islogin'])) {
            $this->denoroterc('islogin', $GLOBALS['For']['islogin']);
        }
        if (isset($GLOBALS['For']['public'])) {
            $this->denoroterc('ipublic', $GLOBALS['For']['public']);
        }
        index::createfile($_ENV['dir'] . '/deno/.env', implode("\n", [
            "DBHOST=" . ($this->json['env']['dbhost'] ?? ''),
            "DBUSER=" . ($this->json['env']['dbuser'] ?? ''),
            "DBPWD=" . ($this->json['env']['dbpwd'] ?? ''),
            "DBNAME=" . ($this->json['env']['dbname'] ?? ''),
            "HOST=" . ($this->json['env']['host'] ?? '')
        ]));
    }

    function denowritec($item, array $access, $key, $role = '') {
        if ($key === 'public' && (isset($access['owner']) || isset($access['under']))) {
            throw new \Exception($item['name'] . '.json "public" cannot use "owner" or "under": public routes have no logged-in user.');
        }
        $class = ucfirst($key) . ucfirst($item['name']) . 'Controller';
        $entry = ['path' => '/' . $item['name'], 'crud' => ['class' => $class, 'crud' => $access['can']]];
        if ($role == '') {
            $GLOBALS['For'][$key] ??= ['path' => $key, 'controller' => [], 'child' => []];
            $group = &$GLOBALS['For'][$key];
        } else {
            $GLOBALS['For']['roles'][$key] ??= ['path' => '/' . $key, 'controller' => [], 'child' => []];
            $group = &$GLOBALS['For']['roles'][$key];
        }
        $group['controller'][] = 'import { ' . $class . ' } from "../Controller/' . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts";';
        if (isset($access['under'])) {
            $group['under'][$access['under']][] = $entry;   // nested later under /<parent>/:<parent>_id
        } else {
            $group['child'][] = $entry;
        }
        unset($group);
        $controller_write = (isset($access['under']) || isset($access['owner']))
            ? $this->denoScopedController($item, $access, $key)
            : $this->denoController($item, $access['can'], $key);
        $controller = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('controller/') . ucfirst($key) . '/' . ucfirst($item['name']) . 'Controller.ts');
        fwrite($controller, $controller_write);
    }

    /** Controller for rows that belong to the user ("owner") or to a parent row the user owns ("under"). */
    function denoScopedController($table, array $access, $key) {
        $name = $table['name'];
        $Model = ucfirst($name) . '$';
        $can = $access['can'];
        $imports = 'import { response, Session } from "../../../dep.ts";
import { ' . $Model . ' } from "../../Model/' . ucfirst($name) . '.ts";';
        if (isset($access['under'])) {
            $parent = $access['under'];
            $col = $parent . '_id';
            $imports .= '
import { ' . ucfirst($parent) . '$ } from "../../Model/' . ucfirst($parent) . '.ts";
import { ownedParent } from "../../scope.ts";';
            // every method first proves the parent in the URL belongs to the user
            $check = '
      const ' . $col . ' = await ownedParent(session, param, ' . ucfirst($parent) . '$, "' . $col . '", "' . $this->ownerOf($parent, $name, $key) . '");
      if (' . $col . ' instanceof Response) return ' . $col . ';';
            $scope = $col . ': [' . $col . ']';
            $set = $col;
        } else {
            $col = $access['owner'];
            $check = '
      const ' . $col . ' = session.Login.id;';
            $scope = $col . ': [' . $col . ']';
            $set = $col;
        }
        $id = 'param.pathname.groups.id';
        $m = [];
        if (in_array('a', $can)) $m[] = '
   static async all(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const rows = await ' . $Model . '().where({ ' . $scope . ' }).get();
      return response.JSON(rows.items, session);
   }';
        if (in_array('w', $can)) $m[] = '
   static async where(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const filters = await session.req.json();
      const rows = await ' . $Model . '().where({ ...filters, ' . $scope . ' }).get();
      return response.JSON(rows.items, session);
   }';
        if (in_array('r', $can)) $m[] = '
   static async show(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const row = await ' . $Model . '().where({ id: [' . $id . '], ' . $scope . ' }).first();
      return row ? response.JSON(row, session) : response.JSON("Not Found", session, 404);
   }';
        if (in_array('c', $can)) $m[] = '
   static async store(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const body = await session.req.json();
      const row = await ' . $Model . '().create({ ...body, ' . $set . ' });
      return response.JSON(row, session);
   }';
        if (in_array('u', $can)) $m[] = '
   static async update(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const body = await session.req.json();
      const row = await ' . $Model . '().where({ id: [' . $id . '], ' . $scope . ' }).update({ ...body, ' . $set . ' });
      return response.JSON(row, session);
   }';
        if (in_array('p', $can)) $m[] = '
   static async upsert(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const rows = ((await session.req.json()).data as Record<string, unknown>[]).map((r) => ({ ...r, ' . $set . ' }));
      return response.JSON(await ' . $Model . '().upsert(rows), session);
   }';
        if (in_array('d', $can)) $m[] = '
   static async delete(session: Session, param: URLPatternResult): Promise<Response> {' . $check . '
      const done = await ' . $Model . '().delete({ id: [' . $id . '], ' . $scope . ' });
      return response.JSON(done, session);
   }';
        return $imports . '

export class ' . ucfirst($key) . ucfirst($name) . 'Controller {' . implode("\n", $m) . '
}
';
    }

    function denoroterc($key, $route) {
        if ($key != "ipublic" && $key != "islogin") {
            $route['roles'] = [$key];
        }
        // "under": put each child below /<parent>/:<parent>_id, next to the parent's own routes
        foreach ($route['under'] ?? [] as $parent => $children) {
            $nest = ['path' => '/:' . $parent . '_id', 'child' => $children];
            $placed = false;
            foreach ($route['child'] as &$c) {
                if ($c['path'] === '/' . $parent) {
                    $c['child'][] = $nest;
                    $placed = true;
                }
            }
            unset($c);
            if (!$placed) {
                $route['child'][] = ['path' => '/' . $parent, 'child' => [$nest]];
            }
        }
        unset($route['under']);
        $controller = $route['controller'];
        unset($route['controller']);
        $route_write = implode("\n", $controller) . "\n" . preg_replace('/"class": "(.+?)"/', '"class": ${1}', str_replace("\/", "/", 'export const ' . $key . ' = [' . json_encode($route, JSON_PRETTY_PRINT) . '];')) . "\n";
        $route = index::fopen_dir($_ENV['dir'] . "/deno/App/" . ucfirst('routes/') . ucfirst($key) . '.ts');
        fwrite($route, $route_write);
    }
}
