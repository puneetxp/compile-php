import { HttpClient, HttpEvent } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, catchError } from 'rxjs';
import { LoginService } from '../login.service';

@Injectable({
  providedIn: 'root'
})
export class FormJsonService {

  constructor(private http: HttpClient, private login: LoginService) { }
  method = "GET";
  action!: string;
  formJson: Record<string, any> = {};
  get<T>(url: string, json: Record<string, any> = {}) {
    return this.http.get<T>(url + "?" + (new URLSearchParams(json).toString()));
  }
  reset() {
    this.formJson = {};
    this.method = "POST";
    this.action = "";
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
        this.formJson[element.name] = element.value;
      }
    });
    return this;
  }
  Req<T>(): Observable<T> {
    return this.http.request<T>(this.method, this.action, {
      body: this.formJson,
    });
  }
  ReqReport(): Observable<HttpEvent<Object>> {
    //only for the php 
    return this.http.request(this.method, this.action, {
      body: this.formJson,
      observe: "events",
      reportProgress: true,
    }).pipe(catchError(this.login.errorMgmt));
  }
}
