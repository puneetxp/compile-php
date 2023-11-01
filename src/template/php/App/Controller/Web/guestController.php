<?php

namespace App\Controller\Web;

use view\pages\public\{
    index,
    aboutus,
    contactus,
    login,
    register
};

class guestController {

    public static function homepage() {
        return (new index())->view();
    }

    public static function aboutus() {
        return (new aboutus())->view();
    }

    public static function contactus() {
        return (new contactus())->view();
    }

    public static function login() {
        return (new login())->view();
    }

    public static function register() {
        return (new register())->view();
    }
}
