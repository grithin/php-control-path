<?php
namespace Grithin;

use Grithin\Strings;
use Grithin\FileInclude;

use Grithin\ServiceLocator;

use Grithin\ControlPath\Flow;


class ControlPath {
	public $inject = []; #< inject to inject into control files

	public $extension = '.php'; # extension to use on control files
	public $return_handler;
	public static $class_files = [];



	/**
	< context > the root path on which to apply paths
	< ServiceLocator >
	< options >
		inject: < variables to inject into files, __construct, and methods >
		namespace: < prefix for Controller classes >
	*/
	public function __construct($context, ServiceLocator $ServiceLocator = null, $options=[]){
		#+ set up DI and SL {
		if(!$ServiceLocator){
			$ServiceLocator = new ServiceLocator;
		}
		# add self into SL in case controls type hint ControlPath
		if(!$ServiceLocator->has(__CLASS__)){
			$ServiceLocator->singleton(__CLASS__, $this);
		}
		$this->di = $ServiceLocator->injector_get();
		#+ }


		$this->context_set($context);

		$defaults = ['namespace' => 'App\\Control'];
		$options = array_merge($defaults, $options);


		$this->namespace = $options['namespace'];

		if(!empty($options['inject'])){
			$this->inject = $options['inject'];
		}

		# provide access to this instacne from within control files
		$this->inject['ControlPath'] = $this;

		$this->options = $options;
	}
	public function context_set($context){
		if(substr($context, -1) != '/'){ # ensure ending "/"
			$context = $context.'/';
		}
		$this->context = $context;
	}
	/** get the path of the currently parsed tokens (will always be a directory)*/
	public function current_path_build($tokens_parsed){
		$path = implode('/', array_filter($tokens_parsed));
		if($path){
			return $this->context.implode('/', $tokens_parsed).'/';
		}else{
			return $this->context;
		}
	}
	function file_load($file, $inject){
		/*
		Class files can not be included more than once or they will error
		on PHP attempting to overwrite the existing class.  Consequently, if
		this file had a class in a previous run, don't include it, just return
		success
		*/
		if(isset(self::$class_files[$file])){
			return 1;
		}
		return FileInclude::include($file, $inject);
	}


	protected $loading = false; # whether current in a load flow
	public function is_loading(){
		return $this->loading;
	}

	/** Being control flow
	@return	Grithin\ControlPath\Load
	*/
	/** params
	< path > < control path to load >
	< options >
		return_handler: < function($return, Flow $flow, ControlPath $ControlPath) > < useful for interpretting special return types for stopping the control >
	 */
	function start($path, $options=[]){
		return new Flow($this, $path, $options);
	}
	/** Load a control path, accumulating the returns */
	/** params see ::start */

	function load($path, $options=[]){
		$flow = $this->start($path, $options);
		$returns = [];
		while($flow->has_next()){
			$result = $flow->next();
			if($result !== true && $result !== false){
				$returns[] = $result;
			}
		}
		return $returns;
	}



	public static function token_to_class($token){
		$cleared = preg_replace('/^[0-9]+|[^a-z0-9_\-\.]/', '', $token);
		return Strings::to_camel($cleared, false, '-.');
	}
	public function path_class_prefix($tokens_parsed=[]){
		$tokens = array_map(function($v){ return self::token_to_class($v);}, array_filter($tokens_parsed));
		if($tokens){
			return $this->namespace.'\\'.implode('\\', $tokens).'\\';
		}
		return $this->namespace.'\\';
	}


	/** ignore calls to non-visible methods on Controllers */
	public function di_call_with($thing, $options){
		try{
			return $this->di->call_with($thing, $options);
		}catch(\Grithin\IoC\MethodVisibility $e){}
	}

}