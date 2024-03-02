<?php

use App\Controller\Web\guestController;

$public = [
    [
        "path" => "",
        "handler" => [guestController::class, "homepage"]
    ],
    [
        "path" => "about-us",
        "handler" => [guestController::class, "aboutus"]
    ],
    [
        "path" => "contact-us",
        "handler" => [guestController::class, "contactus"]
        ]];
