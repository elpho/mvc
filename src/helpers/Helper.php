<?php
  namespace elpho\mvc\helpers;

  use elpho\mvc\View;

  abstract class Helper{
    protected static $view;
    public function __construct(View $view){
      self::$view = $view;
    }
  }