<?php

namespace App\Controller\Isuper;

use The\FileAct;
use The\Response;

class IsuperManagerController {

    public static function all() {
        return Response::json($_ENV);
    }

    public static function img() {
        $file = FileAct::init($_FILES['photo'])->public("")->fileupload($_FILES['photo'], $_POST['name']);
        $_ENV[$_POST['name']] = $file['public'];
        self::set();
        return Response::json($_ENV);
    }

    public static function favicon() {
        $file = FileAct::init($_FILES['photo'])->public("../../public_html")->fileupload($_FILES['photo'], $_POST['name']);
        $_ENV[$_POST['name']] = $file['path'];
        self::set();
        return Response::json($_ENV);
    }

    public static function update($id) {
        $_ENV[$id] = $_POST['update'];
        self::set();
        return Response::json($_ENV);
    }

    public static function upsert() {
        foreach ($_POST as $key => $value) {
            $_ENV[$key] = $value;
        }
        self::set();
        return Response::json($_ENV);
    }

    public static function delete($id) {
        unset($_ENV[$id]);
        self::set();
        return Response::json($_ENV);
    }

    public static function set() {
        FileAct::createfile(__DIR__ . "/../../../additionalenv.php", '<?php $_ENV =' . var_export($_ENV, true) . ";");
    }
}
