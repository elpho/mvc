<?php
  namespace elpho\mvc;

  use elpho\lang\String;
  use elpho\lang\Dynamic;
  use elpho\mvc\helpers;

  class View extends Dynamic{
    private $sandboxedFileName;
    private static $helpersList = array("elpho\\mvc\\helpers\\url");

    public static function addHelper($name){
      $helperClass = self::findHelperClass($name);
      if(!class_exists($helperClass))
        throw new \Exception(String::format(
          "Helper \"%s\" could not be initialized. Missing \"%s\" class.",
          $name,
          $helperClass));

      self::$helpersList[] = $name;
    }

    private static function findHelperClass($name){
      $name = new String($name);
      $parts = $name->split('\\');
      $last = $parts->length()-1;
      $parts[$last] = $parts[$last]->capitalize();
      return $parts->join('\\')."Helper";
    }

    public function __construct($sandboxedFileName){
      $this->sandboxedFileName = $sandboxedFileName;
    }
    public function getFile(){
      return $this->sandboxedFileName;
    }

    public function render($model=null){
      call_user_func(function($viewbag,$model){
        foreach (self::$helpersList as $fullHelperName){
          $helper = basename(str_replace('\\','/',$fullHelperName));
          $__helperClass = self::findHelperClass($fullHelperName);
          $$helper = new $__helperClass($this);
        }

        ob_start();
        try{
          if(!include($this->sandboxedFileName))
            throw new \Exception("Unimplemented view \"$this->sandboxedFileName\".");
        }catch(\Exception $ex){
          ob_end_clean();
          throw $ex;
        }finally{
          ob_end_flush();
        }
      }, $this, $model);
    }
  }
