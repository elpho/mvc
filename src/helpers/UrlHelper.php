<?php
  namespace elpho\mvc\helpers;

  use elpho\lang\Text;
  use elpho\mvc\Router;

  class UrlHelper extends Helper{
    public static function route($path){
      return Router::route($path);
    }
    public static function file($path){
      if(!file_exists($path))
        throw new \Exception(Text::format(
          "File \"%s\" could not be found for linking in view \"%s\".",
          $path,
          self::$view->getFile()
          ));
      return Router::fileRoute($path);
    }
    public static function action($callback, $args=array(), $method="get"){
      $path = Router::routeByAction($callback, $args, $method);
      if($path == null)
        throw new \Exception(Text::format(
          "No action \"%s::%s\" could be found matching the arguments and its types for linking in view \"%s\".\nArgument %s",
          $callback[0],
          $callback[1],
          self::$view->getFile(),
          print_r($args,true)
          ));

      return $path;
    }
  }