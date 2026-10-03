import { Route_Group_with } from "../../dep.ts";
import { AuthController } from "../Controller/AuthController.ts";

export const Auth: Route_Group_with[] = [
  { path: "/login", method: "GET", handler: AuthController.Status, islogin: true },
  { path: "/login", method: "POST", handler: AuthController.Login },
  { path: "/register", method: "POST", handler: AuthController.Register },
  { path: "/profile", method: "GET", handler: AuthController.Profile, islogin: true },
  { path: "/logout", method: "GET", handler: AuthController.Logout, islogin: true },
];
