<?php

use The\Auth;

// Routes for a signed-in user (mounted inside the islogin group).
$iauth = [
    [
        "method" => "GET",
        "path" => "/logout",
        "handler" => [Auth::class, "logout"]
    ],
    [
        "path" => "/auth/profile",
        "child" => [
            ["handler" => [Auth::class, "profile"]],
            ["method" => "POST", "handler" => [Auth::class, "profileupdate"]]
        ]
    ]
];
