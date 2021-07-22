<?php
namespace Grithin\ControlPath;


class Flow{
	const STAGE_SECTION = 1;
	const STAGE_PAGE = 2;
	const STAGE_END = 4;
	private $stage; #< the current stage

	public $return_handler;

	public $inject = []; #< inject to inject into control files
	public $returns = []; # the returns from the control file loads and method calls (when they don't evaluate to false)

	public $tokens_unparsed = [];
	public $tokens_parsed = [];
	public $current_token;
	public $current_path;
	public $path_class_prefix;
	public $stop = false;

	public $controllers = []; #< controller classes that have been instantiated during load, reset each time
	/** params
	< options >
		return_handler: < function($return, ControlPath $ControlPath) > < useful for interpretting special return types for stopping the control >
	 */
	function __construct($ControlPath, $path, $options=[]){
		$this->cp = $ControlPath;
		$this->stage = self::STAGE_SECTION;


		#+ handle injection options {
		# provide a object for control data
		if(!empty($options['inject'])){
			if(!is_array($options['inject'])){
				throw new \Exception('inject is not array');
			}
			$this->inject = $options['inject'];
		}
		$this->inject['share'] = new \ArrayObject;
		$this->inject['Flow'] = $this;
		# merge ControlPath injections
		$this->inject = array_merge($this->cp->inject, $this->inject);
		#+ }

		if(!empty($options['return_handler'])){
			$this->return_handler = $options['return_handler'];
		}


		if(substr($path, 0, 1) == '/'){ # clear leading "/"
			$path = substr($path, 1);
		}

		$this->tokens_unparsed = explode('/', $path);
		$this->forward();

	}

	/** return current token */
	public function current_token(){
		return $this->current_token;
	}
	public function current_path(){
		return $this->current_path;
	}
	public function stop(){
		$this->stage = self::STAGE_END;
	}
	public function has_next(){
		return $this->stage !== self::STAGE_END;
	}
	/** move on to the next token */
	public function forward(){
		if(!$this->has_next()){
			return false;
		}
		if(!$this->tokens_unparsed){
			if($this->stage === self::STAGE_SECTION){
				$this->stage = self::STAGE_PAGE;
				return $this->current_path;
			}else{
				$this->stage = self::STAGE_END;
			}
			return false;
		}
		if($this->current_token){
			$this->tokens_parsed[] = $this->current_token;
		}

		$this->current_token = array_shift($this->tokens_unparsed);
		$this->current_token = $this->current_token ?: 'index';
		$this->current_path = $this->cp->current_path_build($this->tokens_parsed);
		$this->current_relative_path = implode('/',$this->tokens_parsed);
		$this->path_class_prefix = $this->cp->path_class_prefix($this->tokens_parsed);
		return $this->current_path;
	}

	public function handle_return($return){
		/*
		If control returned false, this is stop indication
		*/
		if($return === false){
			$this->stop();
			return false;
		}
		/*
		if control returned null, function had no return
		if control returned 1, this is php return value for
		successful file load
		*/
		if($return !== null && $return !== 1){
			if($this->return_handler){
				($this->return_handler)($return, $this, $this->cp);
			}
			return $return;
		}
		return true;
	}

	/** step through either section or page control */
	public function next(){
		if($this->stage === self::STAGE_SECTION){
			return $this->section();
		}elseif($this->stage === self::STAGE_PAGE){
			return $this->page();
		}else{
			return false;
		}
	}

	/** return
	<= true ><?: success, but no control return >
	||
	<= false ><?: flow has stopped >
	||
	< return value of control >
	*/
	public function section(){
		if($this->stage !== self::STAGE_SECTION){
			return false;
		}
		$return = $this->section_load();
		$this->forward();
		return $return;
	}
	protected function section_load(){
		#+ load Controller.php if present {
		$Controller = false;
		$file = $this->current_path.'Controller.php';
		if(is_file($file)){
			$class = $this->path_class_prefix.'Controller';
			$result = $this->cp->file_load($file, $this->inject);
			#+ ensure it is not re-loaded if it is a class {
			if(class_exists($class, false)){
				$this->cp::$class_files[$file] = true;
			}
			#+ }
			if($result instanceof \Closure){
				$result = $this->cp->di_call_with($result, $this->inject);
			}

			if(class_exists($class, false)){
				$Controller = $this->cp->di_call_with($class, $this->inject);

				$this->controllers[$this->current_relative_path] = $Controller;# provide access for deeper controllers

				if(method_exists($Controller, '_always')){
					$result = $this->cp->di_call_with([$Controller, '_always'], $this->inject);
					return $this->handle_return($result);
				}
			}else{
				return $this->handle_return($result);
			}
		}
		return true; # there may be no section controller, but this doesn't mean stop the flow
		#+ }
	}
	public function page(){
		if($this->stage !== self::STAGE_PAGE){
			return false;
		}
		$return = $this->page_load();
		$this->forward();
		return $return;
	}
	protected function page_load(){
		$conformed_token = $this->cp->token_to_class($this->current_token);
		if(!$conformed_token){
			return false;
		}
		$page_class = $this->path_class_prefix.$conformed_token;


		#+ load page method if present {
		if(isset($this->controllers[$this->current_relative_path])){
			$Controller = $this->controllers[$this->current_relative_path];
			if(method_exists($Controller, $conformed_token)){
				/**
				avoid the case of someone attempting to go to a path ending in _always to
				re-call the _always method of the section, or to a path that should be hidden.
				*/
				if($conformed_token == '_always' || substr($conformed_token, 0,2) == '__'){
					throw new ControlPath\NotFound($this->current_path.$conformed_token);
				}
				$result = $this->cp->di_call_with([$Controller, $conformed_token], $this->inject);
				return $this->handle_return($result);
			}

		}
		#+ }
		#+ load page Controller if present {
		$file = $this->current_token.$this->cp->extension;
		$file = $this->current_path.$file;
		if(is_file($file)){
			$result = $this->cp->file_load($file, $this->inject);

			#+ ensure it is not re-loaded if it is a class {
			if(class_exists($page_class, false)){
				$this->cp::$class_files[$file] = true;
			}
			#+ }

			if($result instanceof \Closure){
				$result = $this->cp->di_call_with($result, $this->inject);
			}
			if(!$this->stop && class_exists($page_class, false)){
				$Controller = $this->cp->di_call_with($page_class, $this->inject);
				if(method_exists($Controller, '_always')){
					$result = $this->cp->di_call_with([$Controller, '_always'], $this->inject);
					return $this->handle_return($result);
				}
			}else{
				return $this->handle_return($result);
			}
		}
	#+ }
		throw new NotFound($this->current_path.$conformed_token);
	}
}