<?php
use PHPUnit\Framework\TestCase;

use \Grithin\Debug;
use \Grithin\Time;
use \Grithin\Arrays;
use \Grithin\GlobalFunctions;
use \Grithin\ServiceLocator;
use \Grithin\ControlPath;

class bob{}


# toggle to silence ppe and pp during debugging
# GlobalFunctions::$silence = true;


class Tests extends TestCase{
	use Grithin\Phpunit\TestTrait;
	function __construct(){
		parent::__construct();

		$this->files = [
			__DIR__.'/test_files/include.php',
			__DIR__.'/test_files/include2.php',
			__DIR__.'/test_files/include3.php'
		];

	}
	function test_basic(){

		$cp = new ControlPath(__DIR__.'/test_files/app1/');

		# section + index
		$result = $cp->load('/');
		$this->assertEquals(['bob','section index'], $result);

		# method page
		$result = $cp->load('/page1');
		$this->assertEquals(['bob','App\Control\Controller::page1'], $result);

		# logic page
		$result = $cp->load('/page2');
		$this->assertEquals(['bob','sue'], $result);

		# page controller
		$result = $cp->load('/page3');
		$this->assertEquals(['bob','jane'], $result);


	}
	function test_depth(){
		$cp = new ControlPath(__DIR__.'/test_files/app1/');



		# section + index
		$result = $cp->load('/section1/');
		$this->assertEquals(['bob','bill','section index'], $result);

		$result = $cp->load('/section1/page1');
		$this->assertEquals(['bob','bill','App\Control\section1\Controller::page1'], $result);

		$result = $cp->load('/section1/page2');
		$this->assertEquals(['bob','bill','sue'], $result);

		# no section controller for page
		$result = $cp->load('/section1/section2/page3');
		$this->assertEquals(['bob','bill','moe'], $result);

		# have a section inbetween without a controller
		$result = $cp->load('/section1/section2/section3/');
		$this->assertEquals(['bob','bill','dan', 'section index'], $result);
	}

	function test_namespace(){
		$cp = new ControlPath(__DIR__.'/test_files/app2/', null, ['namespace'=>'Bobsville']);

		# section + index
		$result = $cp->load('/');
		$this->assertEquals(['bane','section index'], $result);
	}
	function test_injection(){
		$cp = new ControlPath(__DIR__.'/test_files/app3/', null, ['namespace'=>'App3', 'inject'=>['bob'=>'bob']]);

		# test section controller
		$result = $cp->load('/');
		$this->assertTrue($result[0] instanceof ControlPath);
		$this->assertTrue($result[1] instanceof bob);

		# test page logic
		$result = $cp->load('/page2');
		$this->assertEquals($result[1], 'bob');

		# test page closure
		$result = $cp->load('/page3');
		$this->assertEquals($result[1], 'bob');

		# test page controller
		$result = $cp->load('/page4');
		$this->assertTrue($result[1] instanceof ControlPath);
	}

	function test_stop(){
		$return_handler = function($return, $flow, $cp){
			if($return == 'bob'){
				$flow->stop();
			}
		};
		# test stop by return_handler
		$cp = new ControlPath(__DIR__.'/test_files/app4/', null, ['namespace'=>'App4']);
		$result = $cp->load('/section1/section2/page', ['return_handler'=>$return_handler]);
		$this->assertEquals(['bob'], $result);

		# test stop by controller
		$cp = new ControlPath(__DIR__.'/test_files/app4/', null, ['namespace'=>'App4']);

		# stop further depth
		$result = $cp->load('/section1/section2/page');
		$this->assertEquals(['bob', 'moe'], $result);

		# prevent page within section
		$result = $cp->load('/section1/page2');
		$this->assertEquals(['bob', 'moe'], $result);

		# stop by false
		$result = $cp->load('/section2/section3/page');
		$this->assertEquals(['bob'], $result);

	}
	function test_visibility(){
		$cp = new ControlPath(__DIR__.'/test_files/app4/', null, ['namespace'=>'App4']);
		$result = $cp->load('/page4');
		$this->assertEquals(['bob'], $result);
	}

	function test_di_exception(){
		$cp = new ControlPath(__DIR__.'/test_files/app4/', null, ['namespace'=>'App4']);
		$closure = function() use($cp) {
			$result = $cp->load('/page5');
		};
		$this->assert_exception($closure, '', 'Grithin\IoC\ContainerException');

		$closure = function() use($cp) {
			$result = $cp->load('/page6');
		};
		$this->assert_exception($closure, '', 'Grithin\IoC\MissingParam');

	}
	function test_not_found_exception(){
		$cp = new ControlPath(__DIR__.'/test_files/app4/', null, ['namespace'=>'App4']);
		$closure = function() use($cp) {
			$result = $cp->load('/page10');
		};
		$this->assert_exception($closure, '', 'Grithin\ControlPath\NotFound');
	}

	function test_service_locator(){
		$sl = new ServiceLocator();
		$cp = new ControlPath(__DIR__.'/test_files/app4/', $sl, ['namespace'=>'App4']);
		$cp->bob = 'bob';
		$sl->singleton(ControlPath::class, $cp);
		$cp2 = new ControlPath(__DIR__.'/test_files/app4/', $sl, ['namespace'=>'App4']);
		$cp2->bob = 'bill';

		$got = $sl->get(ControlPath::class);
		$this->assertEquals('bob', $got->bob);
	}

	function test_share(){
		$sl = new ServiceLocator();
		$cp = new ControlPath(__DIR__.'/test_files/app4/', $sl, ['namespace'=>'App4']);
		$result = $cp->load('/page11');
		$this->assertEquals(['bob','bob'], $result);
	}




}