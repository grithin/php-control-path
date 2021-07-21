<?php
namespace App4\section1;

class Controller{
	public function _always($Flow){
		$Flow->stop();
		return 'moe';
	}
	public function page2(){
		return 'jeebs';
	}
}

