<?php
namespace App3;

class Controller{
	public function __construct($ControlPath){
		$this->ControlPath = $ControlPath;
	}
	public function _always(){
		return $this->ControlPath;
	}
	public function index(\bob $the_bob){
		return $the_bob;
	}
	public function page1(){
		return __METHOD__;
	}
}

