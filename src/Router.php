<?php
  namespace elpho\mvc;

  use elpho\lang\Text;
  use elpho\lang\ProtoObject;
  use elpho\lang\ArrayList;
  use elpho\di\DependencyInjector;

  class Router{
    private static $instance = null;
    private static $di = null;
    private static $root;
    private static $default;

    private $routes = array();

    private $currentRoute = null;

    public static function getInstance($index=null, $default=array("elpho\mvc\ErrorController", "e404")){
      if(self::$instance == null){
        if($index == null)
          throw new \Exception("No index for new Router.");

        self::$instance = new self($index, $default);
      }
      return self::$instance;
    }

    public static function setDependencyInjector(DependencyInjector $di){
      $di->registerProvider('elpho\mvc\RouterProvider');
      self::$di = $di;
    }

    public static function fileRoute($uri){
      $uri = new Text($uri);
      if(!$uri->startsWith("/"))
        $uri = new Text("/".$uri);

      $router = self::getInstance();

      return self::$root.$uri;
    }
    public static function routeByAction($callback, $args, $method){
      $router = self::getInstance();
      $route = $router->findRouteByAction($callback, $args, $method);
      if($route === self::$default)
        return null;

      return self::$root.'/'.$route->getStringPath($args);
    }
    public static function route($uri="", $method="get"){
      if (is_object($uri) and is_a($uri, Route)){
        if (!in_array($uri, $this->routes[$method]))
          throw new UnregisteredRouteException();

        $uri = $uri->getPath();
      } else {
        $uri = new Text($uri);
        if(!$uri->startsWith("/"))
          $uri = new Text("/".$uri);
      }

      return self::$root.$uri;
    }

    private function __construct($index, $default){
      $index = new Text($index);
      $docRoot = new Text($_SERVER["DOCUMENT_ROOT"]);

      $this->routes["get"] = array();

      $this->routes["put"] = array();
      $this->routes["post"] = array();
      $this->routes["delete"] = array();

      self::$default = new Route("error/404", $default);
      self::$root = $index->replace("\\", "/")->replace($docRoot->replace("\\","/")->toString(), "");
    }

    public function mapFor($class){
      $type = new \ReflectionClass($class);
      $sets = array();

      $methods = $type->getMethods(\ReflectionMethod::IS_STATIC);
      foreach($methods as $method){
        $args = RouteAnnotation::read($method);

        if($args->length() === 0)
          continue;

        $defaults = new ProtoObject();
        $defaults->path = '';
        $defaults->method = 'get';

        foreach($args as $route){
          $routeArgs = ProtoObject::merge($defaults, $route);

          $sets[] = array(
            $routeArgs->path,
            array($method->class, $method->name),
            strtolower($routeArgs->method));
        }
      }

      $routes = array();
      foreach($sets as $set){
        $routes[] = $this->map($set[0], $set[1], $set[2]);
      }

      return $routes;
    }

    public function map($url, $callback, $method="get"){
      if(is_array($method)){
        $routes = array();
        foreach($method as $allowed){
          $routes[] = $this->map($url, $callback, $allowed);
        }
        return $routes;
      }

      if(is_array($url)){
        $routes = array();
        foreach ($url as $value) {
          $routes[] = $this->map($value, $callback, $method);
        }
        return $routes;
      }

      if(is_string($url))
        $url = new Text($url);

      $parts = $url->replace("\\", "/")->split("/");
      $parts = $parts->filter();

      $route = new Route($parts, $callback);

      if(self::$di !== null)
        $route->setDependencyInjector(self::$di);

      $this->routes[$method][] = $route;

      return $route;
    }
    public function mapResource($baseUrl, $controller){
      if(!is_object($baseUrl))
        $baseUrl = new Text($baseUrl);

      $parts = $baseUrl->replace("\\", "/")->split("/");
      $parts = $parts->filter();
      $baseUrl = $parts->join("/");

      $this->map($baseUrl, array($controller, "index"));
      $this->map($baseUrl, array($controller, "create"), "post");
      $this->map($baseUrl."/new", array($controller, "newModel"));

      $this->map($baseUrl."/#id", array($controller, "show"));
      $this->map($baseUrl."/#id", array($controller, "update"), "put");
      $this->map($baseUrl."/#id", array($controller, "delete"), "delete");
      $this->map($baseUrl."/#id/edit", array($controller, "edit"));
    }

    public function getRequest(){
      $requestUri = new Text();
      if (isset($_SERVER["REQUEST_URI"]))
        $requestUri = new Text($_SERVER["REQUEST_URI"]);

      if ($requestUri->startsWith(self::$root))
        $requestUri = $requestUri->substr(strlen(self::$root));

      $request = $requestUri->split("?")->get(0)->split("/")->filter();
      return $request;
    }
    public function getCurrentRoute(){
      return $this->currentRoute;
    }
    public function getCurrentPath(){
      return $this->currentRoute->getStringPath($this->currentRoute->parseArgs($this->getRequest()));
    }

    public function findRoute($request=null, $method=null){
      if($request === null or $request == array())
        $request = $this->getRequest();

      $routes = array();
      if ($method == null)
        if (isset($_SERVER['REQUEST_METHOD']))
          $method = strtolower($_SERVER['REQUEST_METHOD']);
        else
          $method = "get";

      if (isset($this->routes[$method]))
        $routes = $this->routes[$method];

      foreach($routes as $route){
        if(!$route->match($request))
          continue;

        return $route;
      }

      return self::$default;
    }
    public function findRouteByAction($callback, $args, $method="get"){
      $routes = array();
      if(isset($this->routes[$method]))
        $routes = $this->routes[$method];

      foreach($routes as $route){
        if(!$route->matchByAction($callback, $args))
          continue;

        return $route;
      }

      return self::$default;
    }

    public function serve() {
      $route = $this->findRoute();

      $this->currentRoute = $route;

      $request = $this->getRequest();
      $route->go($request);
    }
  }
