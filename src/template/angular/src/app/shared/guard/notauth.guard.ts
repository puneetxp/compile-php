import { Injectable } from "@angular/core";
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree } from "@angular/router";
import { Select } from "@ngxs/store";
import { map, Observable } from "rxjs";
import { Login } from "../Interface/Login";

@Injectable({
  providedIn: "root",
})
export class NotauthGuard  {
  @Select()
  login$!: Observable<Login | false>;

  constructor(private router: Router) {
  }
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
        this.router.navigate(["/"], {
          queryParams: { returnUrl: state.url },
        });
        return false;
      } else {
        return true;
      }
    }));
  }
}
