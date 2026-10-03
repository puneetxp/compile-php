import { _Routes, compile_routes, compile_url_pattern } from "../../dep.ts";
import { Public } from "../Controller/PublicController.ts";
import { Auth } from "./Auth.ts";
import { islogin } from "./Islogin.ts";
import { isuper } from "./Isuper.ts";

// Generated role files (Islogin.ts, Isuper.ts, Ipublic.ts, <Role>.ts) are rewritten by `php setup.php`;
// add any extra role groups here.
const route_pre: _Routes = [
  { handler: Public.Home },
  {
    islogin: true,
    child: [
      ...islogin,
      ...isuper,
    ],
  },
  ...Auth,
];
export const routes = compile_url_pattern(compile_routes(route_pre));
