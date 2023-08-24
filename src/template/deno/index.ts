import { Router } from "./dep.ts";
import { routes } from "./App/Routes/index.ts";
import { setRole } from "./the_deno/mod.ts";
import { Role$ } from "./App/Model/Role.ts";
setRole(await Role$.all());
Deno.serve(
  { port: 9000 },
  async (req: Request): Promise<Response> => {
    return await new Router(routes).route(req);
  }
);