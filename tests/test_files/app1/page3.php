<?php
namespace App\Control;

class Page3{
	public function __construct(){
		$this->jane = 'jane';
	}
	public function _always(){
		return $this->jane;
	}
}

