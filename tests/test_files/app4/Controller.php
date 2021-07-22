<?php
namespace App4;

class Controller{
	public function _always($share){
		$share['name'] = 'bob';
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
	public function page11($share){
		return $share['name'];
	}
}

