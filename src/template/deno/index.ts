import { Router, setRole } from "./dep.ts";
import { routes } from "./App/Routes/index.ts";
import { Role$ } from "./App/Model/Role.ts";

// Roles must be loaded before any session is created. Run with: deno run --allow-all --unstable-kv index.ts
setRole((await Role$().all()).items);

Deno.serve(
  { port: 9000 },
  async (req: Request): Promise<Response> =>
    (await new Router(routes, req).URLPattern()?.run()) ??
      new Response("Not Found", { status: 404 }),
);
