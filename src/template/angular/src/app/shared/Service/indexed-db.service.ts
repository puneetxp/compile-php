
// type dbt = () => Promise<IDBDatabase>;

import { Injectable } from "@angular/core";

let tables: string[] = [];
let dbName: string = "";
export type UseStore = <T>(
  txMode: IDBTransactionMode,
  callback: (store: IDBObjectStore) => T | PromiseLike<T>
) => Promise<T>;

@Injectable({
  providedIn: "root",
})
export class IndexedDBService {
  // (function () {
  dbVersion = 3;
  setTable(data: string[]) {
    tables = data;
  }
  setDb(data: string) {
    dbName = data;
  }
  The_putSomeData(table: string, data: unknown) {
    const open = indexedDB.open(dbName, this.dbVersion);
    open.onupgradeneeded = () => this.init(open.result, tables);
    open.onsuccess = function () {
      const db = open.result;
      const tx = db.transaction(table, 'readwrite');
      const store = tx.objectStore(table);
      if (Array.isArray(data)) {
        data.forEach((element) => {
          store.put(element);
        });
      } else {
        store.put(data);
      }
      tx.oncomplete = function () {
        db.close();
      };
    };
  }
  The_delSomeData(table: string, del: string | number) {
    const open = indexedDB.open(dbName, this.dbVersion);
    open.onupgradeneeded = () => this.init(open.result, tables);
    open.onsuccess = function () {
      const db = open.result;
      const tx = db.transaction(table, 'readwrite');
      const store = tx.objectStore(table);
      store.delete(del);
      tx.oncomplete = function () {
        db.close();
      };
    };
  }
  The_getAllData(table: string, callback: (value: any) => void) {
    const open = indexedDB.open(dbName, this.dbVersion);
    open.onupgradeneeded = () => this.init(open.result, tables);
    open.onsuccess = function () {
      const db: IDBDatabase = open.result;
      const tx = db.transaction(table, 'readonly');
      const store = tx.objectStore(table);

      // add, clear, count, delete, get, getAll, getAllKeys, getKey, put
      const allSavedItems = store.getAll();
      allSavedItems.onsuccess = function () {
        callback(allSavedItems.result);
      };
      tx.oncomplete = function () {
        db.close();
      };
    };
  }
  promisifyRequest<T = undefined>(
    request: IDBRequest<T> | IDBTransaction | any
  ): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      request.oncomplete = request.onsuccess = () => resolve(request.result);
      request.onabort = request.onerror = () => reject(request.error);
    });
  }
  getTable(table: string): UseStore {
    const open = indexedDB.open(dbName, this.dbVersion);
    open.onupgradeneeded = () => this.init(open.result, tables);
    return (txMode, callback) =>
      this.promisifyRequest<IDBDatabase>(open).then((db) =>
        callback(db.transaction(table, txMode).objectStore(table))
      );
  }
  The_getall<T>(table: string): Promise<T> {
    const t = this.getTable(table);
    return t('readwrite', (store) => this.promisifyRequest(store.getAll()));
  }
  The_clearobject(table: string) {
    const open = indexedDB.open(dbName, this.dbVersion);
    open.onsuccess = function () {
      const db = open.result;
      const tx = db.transaction(table, 'readwrite');
      tx.objectStore(table).clear();
      db.close();
    };
  }
  The_clearData() {
    indexedDB.deleteDatabase(dbName);
  }
  The_setData(table: string, data: any) {
    this.The_clearobject(table);
    this.The_putSomeData(table, data);
  }
  init(db: IDBDatabase, table: Array<string>) {
    table.forEach((element) => {
      db.createObjectStore(element, { keyPath: 'id' });
    });
  }
}
// less then 75 line
