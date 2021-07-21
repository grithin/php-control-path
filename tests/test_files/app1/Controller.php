<?php
namespace App\Control;

class Controller{
	public function __construct(){
		$this->bob = 'bob';
	}
	public function _always(){
		return $this->bob;
	}
	public function index(){
		return 'section index';
	}
	public function page1(){
		return __METHOD__;
	}
}

