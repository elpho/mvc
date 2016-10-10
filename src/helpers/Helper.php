<?php
  namespace elpho\mvc\helpers;

  use elpho\mvc\View;

  abstract class Helper{
    protected $view;
    public function __construct(View $view){
      $this->view = $view;
    }
  }