<?php

use App\Controller\Web\guestController;

$auth = [
 [
  "path" => "login",
  "child" => [
   ["handler" => [guestController::class, "login"]],
   ["method" => "POST", "handler" => [guestController::class, "login"]]
  ]
 ],
 [
  "path" => "register",
  "handler" => [guestController::class, "register"]
 ]
];
