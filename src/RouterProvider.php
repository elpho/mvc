<?php
  namespace elpho\mvc;

  use elpho\di\DependencyProvider;

  class RouterProvider implements DependencyProvider{
    public function getProvidedClassName(){
      return 'Router';
    }
    public function getInstance(){
      return Router::getInstance();
    }
  }