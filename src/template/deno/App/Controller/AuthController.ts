import {
  hash,
  response,
  Session,
} from "../../dep.ts";
import { Active_role } from "../Interface/Model/Active_role.ts";
import { Role } from "../Interface/Model/Role.ts";
import { Active_role$ } from "../Model/Active_role.ts";
import { Book$ } from "../Model/Book.ts";
import { Role$ } from "../Model/Role.ts";
import { User$ } from "../Model/User.ts";

export class AuthController {
  static async Status(
    Session: Session,
  ) {
    if (Session.ActiveLoginSession) {
      return await response.JSON(
        Session.getLogin().Login,
        Session,
      );
    }
    return await response.JSONF(false);
  }

  static async Login(Session: Session) {
    const body = await Session.req.json();
    body.password = await hash.sha3_256(body.password);
    const user = await User$.find(body.email, "email");
    if (user) {
      if (body.password == user.password) {
        const active_roles: Active_role[] = await Active_role$.where({
          "user_id": user.id,
        }).Item;
        const _session = Session.startnew(user, active_roles);
        Book$.create([{ name: body.name, user_id: user.id }]);
        return await response.JSONF(
          _session.getLogin().Login,
          _session.returnCookie(),
        );
      }
      return await response.JSONF("Password is Incorrect");
    }
    return await response.JSONF("User Not Found");
  }

  static async Register(Session: Session) {
    const body = await Session.req.json();
    body.password = await hash.sha3_256(body.password);
    const register = await (await User$.create([body])).lastinsertid();
    const _session = Session.startnew(register[0]);
    Book$.create([{ name: body.name, user_id: register.id }]);
    return await response.JSONF(
      _session.getLogin().Login,
      _session.returnCookie(),
    );
  }

  static async UpdateProfile(Session: Session) {
    return await response.JSON({ ok: "diff" });
  }

  static async Logout(
    session: Session,
  ) {
    session.removeSession();
    return await response.JSON({ ok: "Logout" });
  }
}
