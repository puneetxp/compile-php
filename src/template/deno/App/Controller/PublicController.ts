import { response, hash } from "../../dep.ts";
export class Public {
  static async Home(): Promise<Response> {
    return new Response("Home");
  }
  static async Register(req: Request): Promise<Response> {
    const body = await req.json();
    body.password = await hash.sha3_256(body.password);
    return response.JSON(body);
  }
}
