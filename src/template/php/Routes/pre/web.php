<?php
require __DIR__ . "/../../vendor/autoload.php";
require __DIR__ . "/api/Ipublic.php";
require __DIR__ . "/api/Islogin.php";
require __DIR__ . "/api/Isuper.php";
require __DIR__ . "/api/Iauth.php";
require __DIR__ . "/api/Ienv.php";
require __DIR__ . "/view/public.php";
require __DIR__ . "/view/auth.php";
require __DIR__ . "/view/islogin.php";

use The\compile\{RouteCompile, Thefun};

$routes = [
    [
        "path" => "api",
        "child" =>
        [
            $ipublic,
            [
                "ilogin" => true,
                "child" => [$isuper, ...$ienv, ...$iauth, $islogin]
            ]
        ]
    ],
    ...$public,
    $login,
    ...$auth
];

$route = var_export((new RouteCompile($routes))->route, true);
$routx = Thefun::fopen_dir(__DIR__ . "/../web.php");
fwrite($routx, preg_replace(["/'(.*::class)'/"], [' ${1} '], "<?php \n" . '$route =' . stripslashes($route) . ";"));
