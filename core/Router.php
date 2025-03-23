<?php
class Router {
    public static function route($url) {
        $url = rtrim($url, '/');
        $url = explode('/', $url);

        $controllerName = !empty($url[0]) ? ucfirst($url[0]) . 'Controller' : 'UserController';
        $method = isset($url[1]) ? $url[1] : 'index';

        require_once "controllers/$controllerName.php";
        $controller = new $controllerName();

        if (method_exists($controller, $method)) {
            $controller->$method();
        } else {
            echo "Method not found";
        }
    }
}


require_once 'core/Router.php';
$url = isset($_GET['url']) ? $_GET['url'] : '';
Router::route($url);
?>