import { T } from '@angular/cdk/keycodes';
import { HttpClient, HttpErrorResponse, HttpEvent, HttpEventType, HttpResponse } from '@angular/common/http';
import { Injectable, NgModule } from '@angular/core';
import { Observable, catchError, map, throwError } from 'rxjs';
import { LoginService } from '../login.service';
import { HttpHeaders } from '@angular/common/http';
import { remove_empty } from '../../Function/Form';
@Injectable({
  providedIn: 'root'
})
export class FormDataService {
  constructor(public LoginService: LoginService, private http: HttpClient) {
  }
  formData = new FormData();
  method = "POST";
  action!: string;
  httpheader?: HttpHeaders;
  setheader(header: string | {
    [name: string]: string | number | (string | number)[];
  }) {
    this.httpheader = new HttpHeaders(header);
    return this;
  }
  get<T>(url: string, json: Record<string, any> = {}) {
    return this.reset().Req<T>("get", url + "?" + (new URLSearchParams(json).toString()));
  }
  post<T>(url: string, json?: Record<string, any>) {
    return this.reset().Req<T>("post", url, json);
  }
  put<T>(url: string, json?: Record<string, any>) {
    return this.reset().Req<T>("put", url, json);
  }
  patch<T>(url: string, json?: Record<string, any>) {
    return this.reset().Req<T>("patch", url, json);
  }
  delete<T>(url: string, json?: Record<string, any>) {
    return this.reset().Req<T>("delete", url, json);
  }
  reset() {
    this.formData = new FormData();
    this.method = "POST";
    this.action = "";
    return this;
  }
  HTML(form: HTMLFormElement): this {
    this.reset();
    const m = form.getAttribute("method");
    if (m) {
      this.method = m;
    }
    if (form.action) {
      this.action = form.action;
    }
    form.querySelectorAll("input").forEach((element) => {
      if (element.name) {
        this.formData.append(element.name, element.value);
      }
    });
    return this;
  }
  FilesAppend(list: FileList, name = "files[]") {
    for (let i = 0; i < list.length; i++) {
      const file = list[i];
      this.formData.append(name, file, file.name);
    }
    return this;
  }
  Req<T>(method?: string, url?: string, json?: Record<string, any>, checklogin = true): Observable<T> {
    method && (this.method = method);
    url && (this.action = url);
    json && this.Json(json);
    this.method == "get" && (this.formData = new FormData());
    this.php();
    return this.http.request(this.method, this.action, {
      body: this.formData,
      observe: "response",
      headers: this.httpheader
    }).pipe(catchError((error: HttpErrorResponse): Observable<never> => {
      if (checklogin) {
        !(error.status == 401 || error.status == 403) && this.LoginService.logcheck();
      }
      return throwError(() => { return error });
    }), map((i) => i.body as T));
  }
  ReqReport(): Observable<HttpEvent<Object>> {
    //only for the php 
    this.php();
    return this.http.request(this.method, this.action, {
      body: this.formData,
      headers: this.httpheader,
      observe: "events",
      reportProgress: true,
    }).pipe(catchError((error: HttpErrorResponse): Observable<never> => {
      error.status !== 200 && this.LoginService.logcheck();
      return throwError(() => { return error });
    }));
  }
  php() {
    if (!["GET", "POST"].includes(this.method.toUpperCase())) {
      this.formData.append("_method", this.method);
      this.method = "POST";
    }
  }
  Json(json: any) {
    json = remove_empty(json);
    Object.keys(json).forEach((key) => key.endsWith("[]")
      ? json[key].forEach((element: any) => this.formData.append(key,
        (typeof element == "string") ? element : JSON.stringify(element)))
      : this.formData.append(key,
        (typeof json[key] == "boolean") ?
          json[key] + 0
          : (typeof json[key] == "string")
            ? json[key]
            : JSON.stringify(json[key])));
    return this;
  }
}
