import { HttpClient, HttpErrorResponse } from "@angular/common/http";
import { Injectable } from "@angular/core";
import { Router } from "@angular/router";
import { Select, Store } from "@ngxs/store";
import { map, Observable, throwError } from "rxjs";
import { Login } from "../Interface/Login";
import { DeleteLogin, SetLogin } from "../Ngxs/Action/Login.action";

@Injectable({
  providedIn: 'root'
})
export class LoginService {
  constructor(
    private router: Router,
    private store: Store,
    public http: HttpClient,
  ) { }

  @Select() login$!: Observable<Login | false>;
  logcheck(): void {
    this.http.get<Login>(
      "/api/login",
    ).subscribe({
      next: (j) => {
        this.store.dispatch(new SetLogin(j));
      },
      error: (e) => {
        this.logout();
      },
    }
    );
  }
  get() {
    return this.login$.pipe(map((i) => {
      return i;
    }));
  }
  logout(): void {
    this.http.get<string>("/api/logout").subscribe(
      (j: string) => {
        this.store.dispatch(new DeleteLogin()).subscribe(i => this.router.navigateByUrl("/login"));
      }
    );
  }
  errorMgmt(error: HttpErrorResponse) {
    let errorMessage = "";
    if (error.error instanceof ErrorEvent) {
      // Get client-side error
      errorMessage = error.error.message;
    } else {
      // Get server-side error
      errorMessage = `Error Code: ${error.status}\nMessage: ${error.message}`;
    }
    return throwError(() => {
      return errorMessage;
    });
  }
}
