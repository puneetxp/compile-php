import { response, Session } from "../../dep.ts";

export class Public {
  static async Home(session: Session): Promise<Response> {
    return await response.JSON("Home", session);
  }
}
