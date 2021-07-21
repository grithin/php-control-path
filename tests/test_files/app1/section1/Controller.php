<?php
namespace App\Control\section1;

class Controller{
	public function __construct(){
		$this->bill = 'bill';
	}
	public function _always(){
		return $this->bill;
	}
	public function index(){
		return 'section index';
	}
	public function page1(){
		return __METHOD__;
	}
}

