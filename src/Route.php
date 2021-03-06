<?php
  namespace elpho\mvc;

  use elpho\lang\Text;
  use elpho\lang\ProtoObject;
  use elpho\lang\ArrayList;
  use elpho\lang\ArrayList_from;
  use elpho\di\DependencyInjector;

  class Route{
    const ARG_TYPE_NUMBER = "ARG_TYPE_NUMBER";
    const ARG_TYPE_ANY = "ARG_TYPE_ANY";

    private $callback;
    private $path;
    private $argsIndexes;
    private $argsNames;
    private $args;
    private $di;

    public function __construct($path, $callback){
      if(is_array($path))
        $path = new ArrayList_from($path);

      if(!is_object($path)){
        $path = new Text($path);
        $path = $path->replace("\\", "/")->split("/");
      }

      $path = $path->filter();

      $this->callback = $callback;
      $this->path = $path;

      $this->argsIndexes = new ArrayList();
      $this->argsNames = new ArrayList();

      for($i=0; $i<$path->length(); $i++){
        $part = $path[$i];

        if($part->startsWith("*")){
          $this->argsIndexes->push(array($i, self::ARG_TYPE_ANY));
          $this->argsNames->push($part->substr(1));
        }

        if($part->startsWith("#")){
          $this->argsIndexes->push(array($i, self::ARG_TYPE_NUMBER));
          $this->argsNames->push($part->substr(1));
        }
      }
    }

    public function setDependencyInjector(DependencyInjector $di){
      $this->di = $di;
    }

    public function parseArgs($request){
      $args = new ProtoObject();
      parse_str(file_get_contents("php://input"),$inputData);

      foreach($this->argsIndexes as $key => $i){
        if($i[0] >= $request->length())
          continue;

        $args->{$this->argsNames[$key]} = $request[$i[0]];
      }

      //external args
      foreach($inputData as $key => $value){
        $args->{$key} = $value;
      }

      //app-level args override user-level args for safety
      $this->injectSessionArgs($args);

      return $args;
    }
    private function injectSessionArgs(ProtoObject $args){
      if(session_status() == PHP_SESSION_ACTIVE)
        foreach($_SESSION as $key => $value){
          $args->{$key} = $value;
        }
    }
    public function getPath(){
      return $this->path;
    }
    public function getStringPath($args=array()){
      $result = new ArrayList();

      $lastArg = -1;
      for($i=0; $i<$this->path->length(); $i++){
        $part = $this->path[$i];

        if($part->startsWith("*") || $part->startsWith("#")){
          $name = $this->argsNames[++$lastArg];
          $result->push($args[$name->toString()]);
          continue;
        }

        $result->push($part);
      }

      return $result->join("/");
    }
    public function matchByAction($callback, $args){
      if(is_array($callback)){
        if($callback[0] != $this->callback[0])
          return false;
        if($callback[1] != $this->callback[1])
          return false;
      }else{
        if($callback != $callback)
          return false;
      }

      if($this->argsNames->length() != count($args))
        return false;

      foreach($this->argsNames as $key => $name){
        $name = $name->toString();
        if(!isset($args[$name]))
          return false;

        switch($this->argsIndexes[$key][1]){
          case self::ARG_TYPE_NUMBER:
            if(!is_numeric($args[$name]))
              return false;
            break;
          case self::ARG_TYPE_ANY:
            if(!is_string($args[$name]))
              return false;
            break;
        }
      }

      return true;
    }
    public function match(ArrayList $path){
      for($i=0; $i<max($this->path->length(),$path->length()); $i++){
        if(!isset($this->path[$i]))
          return false;

        if(!isset($path[$i]))
          return false;

        $local = $this->path[$i];
        $request = $path[$i];

        if($this->argsIndexes->contains(array($i, self::ARG_TYPE_NUMBER))
          && is_numeric($request->toString()))
          continue;

        if($this->argsIndexes->contains(array($i, self::ARG_TYPE_ANY)))
          continue;

        if(!$local->equals($request))
          return false;
      }

      return true;
    }

    public function go($request){
      try{
        if(!is_callable($this->callback))
          throw new UnregisteredRouteException();

        $args = $this->parseArgs($request);
        $signal = true;

        if(is_callable(array($this->callback[0], "_beforeFilter")))
          $signal = call(array($this->callback[0], "_beforeFilter"), $this->callback[1], $args);

        if($signal){
          if($this->di !== null)
            $this->di->inject($this->callback, array($args));
          else
            call($this->callback, $args);
        }

      }catch (\Exception $ex){
        call(array("elpho\mvc\ErrorController", "e500"), array("exception"=>$ex));
      }
    }
  }
