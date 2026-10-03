<?php

use The\{
    Auth,
    SocialAuth
};

// Routes that must work without a session (mounted under /api, outside the islogin group).
$inotlogin = [
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
        "path" => "/googleauth/.+",
        "handler" => [SocialAuth::class, "g_auth"]
    ],
    [
        "path" => "/facebookauth/.+",
        "handler" => [SocialAuth::class, "f_auth"]
    ]
];
