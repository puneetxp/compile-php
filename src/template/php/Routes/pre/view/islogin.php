<?php

use App\Controller\Web\authController;
use App\Guard\loginGuard;
$login = [
 "islogin" => true,
 "guard" => [[loginGuard::class, "IsloginView"]],
 "child" => [
  [
   "path" => "dashboard",
   "handler" => [authController::class, "dashboard"]
  ]
 ]
];