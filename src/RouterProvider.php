<?php
  namespace elpho\mvc;

  class RouterProvider implements DependencyProvider{
    public function getProvidedClassName(){
      return 'Router';
    }
    public function getInstance(){
      return Router::getInstance();
    }
  }