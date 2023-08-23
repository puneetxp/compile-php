import { Injectable } from "@angular/core";
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree } from "@angular/router";
import { Select, Store } from "@ngxs/store";
import { map, Observable } from "rxjs";
import { Login } from "../Interface/Login";
import { LoginService } from "../Service/login.service";

@Injectable({
  providedIn: "root",
})
export class IsuperGurad {
  @Select()
  login$!: Observable<Login | undefined>;

  constructor(private store: Store, private router: Router, public LoginService: LoginService) { }
  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot,
  ):
    | Observable<boolean | UrlTree>
    | Promise<boolean | UrlTree>
    | boolean
    | UrlTree {
    return this.login$.pipe(map((i) => {
      if (i) {
        this.LoginService.logcheck()
        if (i.roles.includes("isuper")) return true;
        else return this.router.parseUrl("/not-allowed");
      } else return this.router.parseUrl("/login");
    }));
  }
}
