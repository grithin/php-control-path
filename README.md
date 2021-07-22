# ControlPath
Load control files in path, using section controllers if they are found

## Why
The router I previously built would load control files parallel to a requested url path.  It included section control files that acted like frontware, and it provided an intuitive location for control files.  But, it had some downsides:
-	variable injection was limited to a preset array
-	could not bundle multiple pages into a single controller class which might have utility methods for the pages and a __construct

I also considered that standard Controller class paradigm had [downsides](#controller-class-downsides).

So, I resolved to have the best of both worlds and made this tool.


## What
ControlPath loads [section](#section-control) and [page controls](#page-control).  The returns from the controls are collected, and they form the array that is returned from the `load` function.

```php
use \Grithin\ServiceLocator;
$sl = new ServiceLocator();
$cp = new ControlPath(__DIR__.'/test_files/app1/', $sl->injector_get());
$returns = $cp->load('/section1/page');
```

How to interpret these returns is up to the application.  For instance, the controls could return a response object, or a string which something else turns into a response object.


Let's look at an example.

With a path of `/section1/page`, the steps a flow goes through `while($Flow->next() !== false)` are:
1.	.
	-	load `/Controller.php`
	-	`$Controller = new Controller()`
	-	`$Controller->_always()`
2.	.
	-	load `/section1/Controller.php`
	-	`$Controller = new section1\Controller()`
	-	`$Controller->_always()`
3.	.
	-	load `page.php`




## Section Control

section1\Controller.php
```php
namespace App\Control\section1;
class Controller{
	# always called, but return is not used
	public function __construct(){

	}
	# always called, return is used
	public function _always(){

	}

}
```


## Page Control
There are four variations that a page control can take:

### Section Class Method
In Controller, within the section Controller.php, a method can correspond to the page name `function page1(){}`

### Page Class
Parellels section controller class, but is named for the page.  `/page1` -> `class page1{}`

### Closure
Can return a closure.  The closure will have parameters injected.

### Page Logic
The page itself can just do stuff.  It can still return something - like the output of a template.



## Injection
The methods, closures, and __constructs have normal dependency injection.  Additionally, some parameters are injected based on the name of the parameter.  The named injections are based on the `inject` option.  Two variables are always injected:
-	`Flow` the current Flow instance
-	`control` and ArrayObject used for sharing data between controls
-	`ControlPath` the current ControlPath instance

You can have these injected just by name:

```php
# within a method/constructor
function __construct($ControlPath){}
# within a closure
return function($ControlPath){}
```


## Sharing
It is intended that section controls and page control may want to share data.  To enable this, the `$share` variable, which is an ArrayObject, is injected.  This can also be used to capture methods within a Controller class.

Controller.php
```php
class Controller{
	function __construct($share){
		$share->do_x = function(){
			$this->do_x();
		};
	}
	protected function do_x(){
		echo 'bob';
	}
}
```
page.php
```php
($share->do_x)();
```


Whereas, normally, a section Controller would have protected methods that it shares amongst the page methods within the class, if a section controller wanted to provide access to those methods to either page controls that weren't methods, or page controls that were deeper, it would have to do something like the above.


If necessary, the previous Controller classes can be access with `$Flow->controllers`.  This allows accessing public data (object properties) on the Controller that might be useful.  However, owing to the fact that public methods with the Controller are accessible via path, only pages (and builtins) should be public methods, and utility methods should be protected.



## Stopping Control Flow
There are a few ways to stop control flow:
1. return false from a control
2.	call $Flow->stop()
3.	throw exception

Where #2 occurs can be in multiple places.  Since `$Flow` is injected:
-	within a section __construct
-	within a section _always
-	within a page control file
-	within a return_handler

`Controller.php`
```php
class Controller{
	public function __construct($Flow){
		$Flow->stop();
	}
}
```
Return handler
```php
$return_handler = function($return, $flow){
	# there can be no "bill"'s, only bob's
	if($return == 'bill'){
		$flow->stop();
	}
}
$ControlPath->load('/section1/page', ['return_handler'=>$return_handler]);
```




## Conforming Path Tokens
Since path characters can diverge from what is allowable as a namespace or class, the token parts of the path are converted using ControlPath::token_to_class.

'.-' are used as separated, non-conforming characters are removed, and the token is turned into camel case:

`029bob.bill-sue1` => `bobBillSue1`



## Exceptions

-	`Grithin\ControlPath\NotFound` : when a page is not found, or when a path is directed to a built in
-	`Grithin\IoC\ContainerException` : when injection can not resolve the service for a typed paramaeter
-	`Grithin\IoC\MissingParam` : when injection can not resolve a param




## Misc Design Notes

### Class Files Vs Logic Files
Multiple `load`'s become a problem when there is a mixture of class files and logic files.  You can not re-include a class file, but a logic file expects to be re-included.  As such, when ControlPath discoveres a class file, it will not try to load it again on subsequent runs.

### ServiceLocator singleton ControlPath
There is a question of whether the ControlPath should clone the ServiceLocator or not.  ControlPath adds itself to the SL.  By having a cloned SL, it ensures if a control file calls SL->get(ControlPath), this will always points to the instance loading the control file.  However, this creates a mismatch of expectation:
-	A control file might add a middleware, and it might add a service for that middleware that will be injected, but because the ServiceLocator the control file is using is a clone, that service will not be registered in the ServiceLocator that loads the middleware.

In the end, I decided not to clone ServiceLocater.  ControlPath can be injected by name instead of by type, which means control files only need to name the param `$ControlPath` to ensure they are getting the current instance.

### Why is Route Middleware Deficient?
Generally, there are complexities that apply to specific paths that would make the abstraction into front/middle ware both unnecessarily disconnected and would result in unnecessarily complex general middleware or route specific frone/middle ware.  One off frontwares, indicated by path, fit perfectly for these path specific complexities, to be loaded as section controls.  And example might be some series of complex permission checks which would not fit in a Auth middleware configuration parameter.

### Controller Class Downsides
-	Giant methods or manied methods lead to giant, unmanageable controller files.
	-	large control pages would have to fit inside a Controller class, making the class large
	-	buried pages
	-	page methods in no definite order
-	Page syntax error causes section failure

### Why Not Controller As Api?
APIs + response handlers are good for handling standards.  But, a controller can output anything, and sometimes a large variance from a standard is necessary.




## Plans
Setting this up into a framework takes some effort.

Having something like laravel with route methods `(Model $model)` would mean injecting `Model(Request $request)`, which would mean Request is a service or is otherwise injectable at that point.

Fitting ControlPath in with other wares, where there is the potential section controls will add more wares is also complicated:

![Wares](about/wares.png?raw=true "Wares")
