<?php
namespace Grithin\ControlPath;


class Flow{
	public $return_handler;

	public $inject = []; #< inject to inject into control files
	public $returns = []; # the returns from the control file loads and method calls (when they don't evaluate to false)

	public $tokens_unparsed = [];
	public $tokens_parsed = [];
	public $token_current;
	public $current_path;
	public $stop = false;

	public $controllers = []; #< controller classes that have been instantiated during load, reset each time
	/** params
	< options >
		return_handler: < function($return, ControlPath $ControlPath) > < useful for interpretting special return types for stopping the control >
	 */
	function __construct($ControlPath, $path, $options=[]){
		$this->cp = $ControlPath;


		#+ handle injection options {
		# provide a object for control data
		if(!empty($options['inject'])){
			if(!is_array($options['inject'])){
				throw new \Exception('inject is not array');
			}
			$this->inject = $options['inject'];
		}
		$this->inject['control'] = new \ArrayObject;
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
	public function current(){
		return $this->token_current;
	}
	public function current_path(){
		return $this->current_path;
	}
	public function has_next(){
		return !$this->stop;
	}
	public function is_last(){
		return (bool)$this->tokens_unparsed;
	}
	/** move on to the next token */
	public function forward(){
		if($this->token_current){
			$this->tokens_parsed[] = $this->token_current;
		}
		if($this->stop){
			return false;
		}
		if(!$this->tokens_unparsed){
			$this->stop = true;
			return false;
		}

		$this->token_current = array_shift($this->tokens_unparsed);
		$this->token_current = $this->token_current ?: 'index';
		$this->current_path = $this->cp->current_path_build($this->tokens_parsed);
		return $this->current_path;
	}

	public function stop(){
		$this->stop = true;
	}
	public function handle_return($return){
		if($return && $return !== 1){
			$this->returns[$this->current_path][] = $return;
			if($this->return_handler){
				($this->return_handler)($return, $this, $this->cp);
			}
		}
	}



	public function next(){
		if($this->stop){
			return false;
		}
		$this->returns[$this->current_path] = [];

		$path_class_prefix = $this->cp->path_class_prefix($this->tokens_parsed);
		$conformed_token = $this->cp->token_to_class($this->token_current);
		$page_class = $path_class_prefix.$conformed_token;



		#+ load Controller.php if present {
		$Controller = false;
		$file = $this->current_path.'Controller.php';
		if(is_file($file)){
			$class = $path_class_prefix.'Controller';
			$result = $this->cp->file_load($file, $this->inject);
			#+ ensure it is not re-loaded if it is a class {
			if(class_exists($class, false)){
				$this->cp::$class_files[$file] = true;
			}
			#+ }
			if($result instanceof \Closure){
				$result = $this->cp->di_call_with($result, $this->inject);
			}
			$this->handle_return($result);

			if(!$this->stop){ # possibly within the class file is a stop call
				if(class_exists($class, false)){
					$Controller = $this->cp->di_call_with($class, $this->inject);

					$this->controllers[] = $Controller;# provide access for deeper controllers

					if(method_exists($Controller, '_always')){
						$result = $this->cp->di_call_with([$Controller, '_always'], $this->inject);
						$this->handle_return($result);
					}
				}
			}

		}
		#+ }

		if(!$this->tokens_unparsed){ # this was the last token, resolve page control
			$page_loaded = false;
			#+ load page method if present {
			if(!$this->stop && $Controller){
				if(method_exists($Controller, $conformed_token)){
					/**
					avoid the case of someone attempting to go to a path ending in _always to
					re-call the _always method of the section, or to a path that should be hidden.
					*/
					if($conformed_token == '_always' || substr($conformed_token, 0,2) == '__'){
						throw new ControlPath\NotFound($this->current_path.$conformed_token);
					}
					$result = $this->cp->di_call_with([$Controller, $conformed_token], $this->inject);
					$this->handle_return($result);
					$page_loaded = true;
				}

			}
			#+ }
			#+ load page Controller if present {
			if(!$this->stop){
				$file = $this->token_current.$this->cp->extension;
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
					$this->handle_return($result);
					if(!$this->stop && class_exists($page_class, false)){
						$Controller = $this->cp->di_call_with($page_class, $this->inject);
						if(method_exists($Controller, '_always')){
							$result = $this->cp->di_call_with([$Controller, '_always'], $this->inject);
							$this->handle_return($result);
						}
					}
					$page_loaded = true;
				}
			}
		#+ }
			if(!$page_loaded && !$this->stop){
				throw new NotFound($this->current_path.$conformed_token);
			}
		}

		$return = $this->returns[$this->current_path];
		$this->forward();
		return $return;
	}
}