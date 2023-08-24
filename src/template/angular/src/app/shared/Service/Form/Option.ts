import { Observable, map } from "rxjs";

export interface option {
  key: string, value: string | number
}
export function SelectObservable(data: Observable<any>, value: string = "id", key: string | number = "name") {
  return data.pipe(map((i: any) => ({ key: i[key], value: i[value] })));
}
export function SelectOptions(data: any[], key: string = "id", value: string | number = "name"): any[] {
  return data.map((i: any) => ({ key: i[key], value: i[value] }));
}