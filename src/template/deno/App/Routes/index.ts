import {
  _Routes,
  compile_routes,
} from "../../dep.ts";
import { Public } from "../Controller/PublicController.ts";
import { Auth } from "./Auth.ts";
import { islogin } from "./Islogin.ts";
import { isuper } from "./Isuper.ts";
import { manager } from "./Manager.ts";
import { webhook } from "./webhook.ts";

const route_pre: _Routes = [
  { handler: Public.Home },
  {
    islogin: true,
    child: [
      ...islogin,
      ...isuper,
      ...manager,
    ],
  },
  ...webhook,
  ...Auth,
];
export const routes = compile_routes(route_pre);
