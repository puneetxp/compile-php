<?php

use The\Auth;

$iauth =  [
    [
        "method" => "POST",
        "path" => "/login",
        "handler" => [Auth::class, "login"]
    ],
    [
        "method" => "GET",
        "path" => "/login",
        "handler" => [Auth::class, "status"]
    ],
    [
        "method" => "POST",
        "path" => "/register",
        "handler" => [Auth::class, "register"]
    ],
    [
        "method" => "GET",
        "path" => "/logout",
        "handler" => [Auth::class, "logout"]
    ], [
        "path" => "/reset",
        "roles" => ["isuper"],
        "handler" => [Auth::class, "reseteverything"]
    ]
];
