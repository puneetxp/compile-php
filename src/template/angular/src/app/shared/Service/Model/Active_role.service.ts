import { Injectable } from '@angular/core';
import { Select, Store } from '@ngxs/store';
import { map, Observable } from 'rxjs';
import { AddActive_role, DeleteActive_role, DeleteRoleByUser, EditActive_role, SetActive_role , UpsertActive_role } from '../../Ngxs/Action/Active_role.action';
import { Active_role } from '../../Interface/Model/Active_role';
import { Active_roleStateModel } from '../../Ngxs/State/Active_role.state';
import { AsyncPipe } from '@angular/common';
import { IndexedDBService } from '../indexed-db.service';
import { FormDataService } from '../Form/FormData.service';
type keys = 'id' | 'updated_at' | 'user_id' | 'role_id';
interface find {
  key?: keys;
  value: number | string
};
@Injectable({
  providedIn: 'root'
})
export class Active_roleService {
  @Select() active_role$!: Observable<Active_roleStateModel>;
  constructor(private AsyncPipe: AsyncPipe, private indexdb: IndexedDBService, private store: Store, private form: FormDataService) { }
  private model = 'active_role';
  prefix(prefix: string) {
    this.url = '/api/' + prefix + '/' + this.model
    return this;
  }
  async checkinit() {
    await this.indexdb.The_getall<Active_role[]>(this.model).then(i => {
      this.store.dispatch(new SetActive_role(i));
    });
  }
  private url = '/api/' + this.model;
  create(_value: any): void {
    this.form.post<Active_role>(this.url, _value).subscribe(i => this.store.dispatch(new AddActive_role(i)));
  }
  get(slug: string): Observable<Active_role> {
    return this.form.get<Active_role>(this.url + '/' + slug);
  }
  getState(id: number | string, key: keys = 'id'): Observable<Active_role[]> {
    return this.active_role$.pipe(map(i => { return i.active_roles.filter(a => a[key] == id) }));
  }
  addState(data: any) {
    this.store.dispatch(new AddActive_role(data));
  }
  upsertState(data: any[]) {
    this.store.dispatch(new UpsertActive_role(data));
  }
  array() {
    return this.AsyncPipe.transform(this.allState());
  }
  all(): void {
    const active_roles: Active_role[] = this.AsyncPipe.transform(this.active_role$.pipe(map(i => i.active_roles))) || [];
    if (active_roles.length > 0) {
      this.refresh(active_roles);
    } else {
      this.fresh();
    }
  }
  fresh() {
    this.form.get<Active_role[]>(this.url).subscribe((i) =>
      this.store.dispatch(new SetActive_role(i))
    );
  }
  
  refresh(active_roles: Active_role[]) {
    active_roles.sort((x, y) =>
      new Date(x.updated_at) < new Date(y.updated_at) ? 1 : -1
    );
    this.form.get<Active_role[]>(this.url, { 'latest': active_roles[0].updated_at }).subscribe((i) => this.store.dispatch(new UpsertActive_role(i)));
  }
  allState() {
    return this.active_role$.pipe(map((i) => {
      return i.active_roles;
    }));
  }
  mutlifind(find: find[]) {
    let x = this.allState();
    find.forEach(r => x = x.pipe(map(i => i.filter(a => a[r.key || 'id'] == r.value))))
    return x.pipe(map(i => i[0]));
  }
  find(id: number | string, key: keys = 'id'): Observable<Active_role | undefined> {
    return this.active_role$.pipe(map((i) => { return i.active_roles.find((a: Active_role) => a[key] == id) }));
  }
  update(id: number, _update: any) {
    return this.form.patch<Active_role>(this.url + '/' + id, _update).subscribe(i => this.store.dispatch(new EditActive_role(i)));
  }
  upsert(_upsert: any) {
    return this.form.put<Active_role[]>(this.url, _upsert).subscribe(i => this.store.dispatch(new UpsertActive_role(i)));
  }
  del(id: number) {
    return this.form.delete<number>(this.url + '/' + id).subscribe(i => this.store.dispatch(new DeleteActive_role(i)));
  }
  delbyuserid(id: number) {
    this.store.dispatch(new DeleteRoleByUser(id));
  }
}