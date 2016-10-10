<?php
  namespace elpho\mvc;

  use elpho\lang\Annotation;

  class RouteAnnotation extends Annotation{
    protected static $name = "route";
    protected static $parameters = array("path", "method");
  }