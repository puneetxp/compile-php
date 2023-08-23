<?php

namespace Puneetxp\CompilePhp;

class vueset
{
    public function __construct(public $table, public $json)
    {
    }
    public function set()
    {
        foreach ($this->table as $item) {
            $Interface_write = index::Interface_set($item);
            $vuedjs = 'vuets/src/shared/';
            $Interface = index::fopen_dir($_ENV["dir"] . $vuedjs . 'Interface/' . ucfirst('model/') . ucfirst($item['name']) . '.ts');
            fwrite($Interface, $Interface_write);
            $vuestore = index::fopen_dir($_ENV["dir"] . $vuedjs . 'Store/' . ucfirst('model/') . ucfirst($item['name']) . '.js');
            $vuestore_write = $this->Vue_StoreJs($item);
            fwrite($vuestore, $vuestore_write);
            $vueservice = index::fopen_dir($_ENV["dir"] . $vuedjs . 'Service/' . ucfirst('model/') . ucfirst($item['name']) . '.js');
            $vueservice_write = $this->Vue_ServiceJs($item);
            fwrite($vueservice, $vueservice_write);
        }
    }
    public static function Vue_ServiceJs($table)
    {
        return 'import { use' . ucfirst($table["name"]) . 'Store } from "/src/Store/Model/' . ucfirst($table["name"]) . '.js";

const link = "/api/' . $table["name"] . '"
function all() {
    fetch("/api/' . $table["name"] . '").then(
        (response) => response.json()
    ).then((i) => { use' . ucfirst($table["name"]) . 'Store().upsertItem(i) });
}
function create(' . $table["name"] . ') {
    fetch("/api/' . $table["name"] . '", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(' . $table["name"] . ')
    }).then(
        (response) => response.json()
    ).then((i) => { use' . ucfirst($table["name"]) . 'Store().addItem(i) })
}
function upsert(' . $table["name"] . 's) {
    fetch("/api/' . $table["name"] . '", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(' . $table["name"] . 's)
    }).then(
        (response) => response.json()
    ).then((i) => { use' . ucfirst($table["name"]) . 'Store().upsertItem(i) })
}
function update(' . $table["name"] . ') {
    fetch("/api/' . $table["name"] . '/" + ' . $table["name"] . '.id, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(' . $table["name"] . ')
    }).then(
        (response) => response.json()
    ).then((i) => { use' . ucfirst($table["name"]) . 'Store().upsertItem([i]) })
}
function del(id) {
    fetch("/api/' . $table["name"] . '/" + id, {
        method: "DELETE",
        headers: { "Content-Type": "application/json" }
    }).then(
        (response) => response.json()
    ).then((i) => { use' . ucfirst($table["name"]) . 'Store().removeItem(id) })
}
export default { all, create, update, del, upsert };';
    }
    function Vue_StoreJs($table)
    {
        return 'import { defineStore, acceptHMRUpdate } from "/cdn/js/pinia.js";
export const use' . ucfirst($table["name"]) . 'Store = defineStore({
    id: "' . ucfirst($table["name"]) . '",
    state: () => ({
        rawItems: [],
    }),
    getters: {
        items: (state) => state.rawItems
    },
    actions: {
        addItem(' . $table["name"] . ') {
            this.rawItems.push(' . $table["name"] . ')
        },
        removeItem(id) {
            this.rawItems = this.rawItems.filter(i => i.id != id);
        },
        editItem(' . $table["name"] . ') {
            this.rawItems = this.rawItems.filter(i => i.id != ' . $table["name"] . '.id);
            this.rawItems.push(' . $table["name"] . ');
        },
        upsertItem(' . $table["name"] . 's) {
            ' . $table["name"] . 's.forEach(' . $table["name"] . ' => {
                this.rawItems = this.rawItems.filter(i => i.id != ' . $table["name"] . '.id);
                this.rawItems.push(' . $table["name"] . ');
            });
        }
    },
})
if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(use' . ucfirst($table["name"]) . 'Store, import.meta.hot))
}
    ';
    }
}
