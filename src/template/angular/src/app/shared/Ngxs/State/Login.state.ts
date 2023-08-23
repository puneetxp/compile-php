import { Injectable } from "@angular/core";
import { Router } from "@angular/router";
import { State, Selector, Action, StateContext } from "@ngxs/store";
import { Login } from "../../Interface/Login";
import { SetLogin, DeleteLogin } from "../Action/Login.action";

@State<Login |false>({
    name: "login",
    defaults: false
})
@Injectable()
export class LoginState {
    constructor() { router: Router }
    @Selector()
    static getLogin(state: Login) {
      return state;
    }
    @Selector()
    static isLogin(state: Login | false, router: Router) {
      if (state) {
        return state;
      }
      return false;
    }
    @Action(SetLogin)
    SetLogin({ setState }: StateContext<Login>, { payload }: SetLogin): void {
      setState(payload);
    }
    @Action(DeleteLogin, { cancelUncompleted: true })
    DeleteLogin({ setState }: StateContext<false>) {
      setState(false);
    }
}