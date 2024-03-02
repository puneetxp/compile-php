<?php

namespace Puneetxp\CompilePhp\Class;

class solidset {

    public function __construct(public $table, public $json) {
        
    }

    public function set() {
        foreach ($this->table as $item) {
            $Interface_write = index::Interface_set($item);
            $solidjs = '/solidjs/src/shared/';
            $Interface = index::fopen_dir($_ENV["dir"] . $solidjs . 'Interface/' . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            fwrite($Interface, $Interface_write);
            $solidstore = index::fopen_dir($_ENV["dir"] . $solidjs . 'Store/' . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            $solidstore_write = solidset::SolidTsStore($item);
            fwrite($solidstore, $solidstore_write);
            $solidservice = index::fopen_dir($_ENV["dir"] . $solidjs . 'Service/' . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            $solidservice_write = solidset::SolidServicesTs($item);
            fwrite($solidservice, $solidservice_write);
        }
    }

    public static function SolidTsStore($table) {
        $Name = ucfirst($table['name']);
        $names = $table['table'];
        $dir = "../../";
        return "import { createStore, unwrap } from 'solid-js/store';
    import { $Name } from '" . $dir . "Interface/Model/$Name';
    interface " . $Name . "StateModel {
    $names :" . $Name . "[]
    }
    const [state, set] = createStore(<" . $Name . "StateModel>{ " . $names . ": [] });
    
    class " . $Name . "Store {
    static get() {
      return state;
    }
    static set(i: " . $Name . "[]) {
      set({ " . $names . ": i });
    }
    static upsert(i: " . $Name . "[]) {
      let Prepare: " . $Name . "[] = unwrap(state." . $names . ");
      i.forEach(element => {
      Prepare = Prepare.filter(a => a.id != element.id);
      Prepare = [...Prepare, element];
      });
      set({ " . $names . ": Prepare });
    }
    static del(i: number) {
      set({ " . $names . ": unwrap(state." . $names . ").filter(a => a.id != i) });
    }
    static add(i: " . $Name . ") {
      set({ " . $names . ": [...unwrap(state." . $names . "), i] })
    }
    static update(i: " . $Name . ") {
      set({ " . $names . ": [...unwrap(state." . $names . ").filter(a => a.id == i.id), i] })
    }
    static findState(i: number) {
      return unwrap(state." . $names . ").find(a => a.id == i);
    }
    static getState(i: number) {
      return unwrap(state." . $names . ").filter(a => a.id == i);
    }
    }
    export { " . $Name . "Store };";
    }

    public static function SolidServicesTs($table) {
        $Name = ucfirst($table['name']);
        $name = $table['name'];
        $dir = "../../";
        $crud = $table['crud'];
        if (isset($table['crud']['roles'])) {
            $crud[] = $table['crud']['roles'];
        }
        unset($crud['roles']);
        return "import { " . $Name . " } from '" . $dir . "Interface/Model/" . $Name . "';
    import { " . $Name . "Store } from '" . $dir . "Store/Model/" . $Name . "';
    import { Where } from '../../Interface/TheType';
    import { JFetch } from '../../thelib';
    class Service {
      constructor(public url: string) {
      }
      async all() {
        const req: " . $Name . "[] = await JFetch(this.url);
        req && " . $Name . "Store.upsert(req);
      }
      async create(body: any) {
        const req: " . $Name . " = await JFetch(this.url, {
          method: 'POST',
          body: JSON.stringify(body),
        });
        req && " . $Name . "Store.upsert([req]);
      }
      async upsert(body: any) {
        const req: " . $Name . "[] = await JFetch(this.url, {
          method: 'PUT',
          body: JSON.stringify(body),
        });
        req && " . $Name . "Store.upsert(req);
      }
      async get(id: number) {
        const req: " . $Name . " = await JFetch(this.url + id);
        req && " . $Name . "Store.upsert([req]);
      }
      async where(body: Where) {
        const req: " . $Name . "[] = await JFetch(this.url, {
          method: 'WHERE',
          body: JSON.stringify(body),
        });
        req && " . $Name . "Store.upsert(req);
      }
      async update(i: number, body: any) {
        const req: " . $Name . " = await JFetch(this.url, {
          method: 'PUT',
          body: JSON.stringify(body),
        });
        req && " . $Name . "Store.update(req);
      }
      async del(id: number) {
        const req = await JFetch(this.url + id);
        req && " . $Name . "Store.del(id);
      }
    }
    export const " . $Name . 'Service = (url: string = "/api/' . $name . '") => new Service(url);';
    }
}
