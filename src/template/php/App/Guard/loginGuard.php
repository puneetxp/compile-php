<?php

namespace App\Guard;

use The\Sessions;

class loginGuard {

    public static function IsloginView() {
        if (!Sessions::get_current_user()) {
            header("Location: /login");
            exit;
            return false;
        }
        return true;
    }
}
