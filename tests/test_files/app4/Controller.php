<?php
namespace App4;

class Controller{
	public function _always(){
		return 'bob';
	}
	private function page4(){
		return 'mill';
	}
	public function page5(NotExisting $bob){
		return 'page5';
	}
	public function page6( $bob){
		return 'page6';
	}
}

