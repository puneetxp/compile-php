<?php

use App\Controller\Isuper\IsuperManagerController;

$ienv = [
 [
  "path" => "/env",
  "roles" => ["isuper"],
  "child" => [
   [
    "method" => "POST",
    "path" => "/img",
    "handler" => [IsuperManagerController::class, "img"]
   ],
   [
    "method" => "POST",
    "path" => "/favicon",
    "handler" => [IsuperManagerController::class, "favicon"]
   ],
   [
    "path" => "",
    "handler" => [IsuperManagerController::class, "all"]
   ],
   [
    "method" => "PUT",
    "path" => "",
    "handler" => [IsuperManagerController::class, "upsert"]
   ],
   [
    "method" => "PATCH",
    "path" => "/.+",
    "handler" => [IsuperManagerController::class, "update"]
   ],
   [
    "method" => "DELETE",
    "path" => "/.+",
    "handler" => [IsuperManagerController::class, "delete"]
   ]
  ]
 ]

];
