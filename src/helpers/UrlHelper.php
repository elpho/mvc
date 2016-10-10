<?php
  namespace elpho\mvc\helpers;

  use elpho\mvc\Router;

  class UrlHelper extends Helper{
    public static function route($path){
      return Router::route($path);
    }
    public static function file($path){
      if(!file_exists($path))
        throw new \Exception(String::format(
          "File \"%s\" could not be found for linking in view \"%s\".",
          $path,
          $this->view->getFile()
          ));
      return Router::fileRoute($path);
    }
    public static function action($callback, $args=array(), $method="get"){
      $path = Router::routeByAction($callback, $args, $method);
      if($path == null)
        throw new \Exception(String::format(
          "No action \"%s::%s\" could be found matching the arguments and its types for linking in view \"%s\".\nArgument %s",
          $callback[0],
          $callback[1],
          $this->view->getFile(),
          print_r($args,true)
          ));

      return $path;
    }
  }