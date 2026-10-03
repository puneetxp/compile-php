import { hash, response, Session } from "../../dep.ts";
import { Active_role } from "../Interface/Model/Active_role.ts";
import { User } from "../Interface/Model/User.ts";
import { Active_role$ } from "../Model/Active_role.ts";
import { User$ } from "../Model/User.ts";

type UserRow = User & { password?: string | null; enable?: number };
// the runtime's session user type (it may declare extra optional fields such as telegram_id)
type SessionUser = Parameters<Session["startnew"]>[0];

function publicUser(user: UserRow) {
  const { password: _password, ...rest } = user;
  return rest;
}

export class AuthController {
  static async Status(session: Session) {
    if (session.ActiveLoginSession) {
      return await response.JSON(session.getLogin().Login, session);
    }
    return await response.JSONF(false);
  }

  static async Login(session: Session) {
    const body = await session.req.json();
    const user: UserRow | undefined = (await User$().find(body.email, "email")).item;
    if (!user) {
      return await response.JSONF("User Not Found", {}, 404);
    }
    if (user.enable === 0) {
      return await response.JSONF("This account has been disabled", {}, 403);
    }
    if ((await hash.sha3_256(body.password)) !== user.password) {
      return await response.JSONF("Password is Incorrect", {}, 401);
    }
    const active_roles: Active_role[] = (await Active_role$().where({ user_id: [user.id] }).get()).items;
    const _session = session.startnew(user as SessionUser, active_roles);
    return await response.JSONF(_session.getLogin().Login, _session.returnCookie());
  }

  static async Register(session: Session) {
    const body = await session.req.json();
    if (!body.email || !body.password) {
      return await response.JSONF("Email and password are required", {}, 422);
    }
    if ((await User$().find(body.email, "email")).item) {
      return await response.JSONF({ email: "Email Already Taken" }, {}, 422);
    }
    const user: UserRow = (await User$().create({
      name: body.name,
      email: body.email,
      password: await hash.sha3_256(body.password),
    })).item;
    const _session = session.startnew(user as SessionUser, []);
    return await response.JSONF(_session.getLogin().Login, _session.returnCookie());
  }

  static async Profile(session: Session) {
    const user: UserRow = (await User$().find(session.Login.id)).item;
    return await response.JSON(publicUser(user), session);
  }

  static async Logout(session: Session) {
    session.removeSession();
    return await response.JSON({ ok: "Logout" }, session);
  }
}
