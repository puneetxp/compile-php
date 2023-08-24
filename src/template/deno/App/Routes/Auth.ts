import { Route_Group_with } from "../../dep.ts";
import { AuthController } from "../Controller/AuthController.ts";

export const Auth: Route_Group_with[] = [
  {
    path: "/login",
    method: "GET",
    handler: AuthController.Status,
    islogin: true,
  },
  {
    path: "/login",
    method: "POST",
    handler: AuthController.Login,
    islogin: false,
  },
  {
    path: "/logout",
    method: "GET",
    handler: AuthController.Logout,
    islogin: true,
  },
  {
    path: "/register",
    method: "POST",
    handler: AuthController.Register,
    islogin: false,
  },
];
