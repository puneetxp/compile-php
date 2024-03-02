<?php

namespace App\Controller\Web;

use view\pages\auth\dashboard;

class authController {

    public static function dashboard() {
        return (new dashboard())->view();
    }
}
