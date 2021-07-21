<?php

namespace App3;

class Page4{
	public function __construct($ControlPath){
		$this->ControlPath = $ControlPath;
	}
	public function _always(){
		return $this->ControlPath;
	}

}