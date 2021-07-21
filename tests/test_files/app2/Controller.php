<?php
namespace Bobsville;

class Controller{
	public function __construct(){
		$this->x = 'bane';
	}
	public function _always(){
		return $this->x;
	}
	public function index(){
		return 'section index';
	}
	public function page1(){
		return __METHOD__;
	}
}

