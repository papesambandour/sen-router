<?php

namespace SenRouter\Http\Dispatcher;

class Route{

    /**
     * @var array
     * les methode du requette passe en paramettre, enregistre les ou le methode get ou post ...
     */
    private $allowedMethod = [];

    /**
     * @var string
     */
    private $pathPattern;

    /**
     * @var string
     */
    private $currentRoute;

    /**
     * @var bool
     */
    private $caseSensitive;

    /**
     * @var array
     * Recupere les paramettre passe dans le pattern
     */
    private $routeParams = [];

    /**
     * @var string
     */
    private $routeSeparator = "/";

    /**
     * @var string
     */
    private $routeWithRegex;

    /**
     * @var string|callable
     * le controller et la methode en chaine de caractere separee par un @ ou une function
     */
    private $mixes;

    /**
     * @var array
     */
    private $paramsKeys = [];

    /**
     * @var array
     */
    private $paramsKeysRegex = [];

    /**
     * @var array
     */
    private $paramsValues = [];

    /**
     * @var bool
     */
    private $haveSetedParams = false;

    /**
     * @var
     */
    private $router;

    /**
     * @var
     */
    private $output;

    /**
     * @var array
     */
    public $middlewares = [];


    
    public function __construct($router, $method, $pathPattern, $mixes, $caseSensitive = false){
        $this->router = $router;
        $this->currentRoute = rtrim($_SERVER['REQUEST_URI'], "/")."/";
        $this->pathPattern = rtrim($pathPattern, "/")."/";
        $this->setAllowedMethod($method);
        $this->mixes = $mixes;
        $this->caseSensitive = $caseSensitive;
        
    }

    /**
     * @return bool
     */
    public function matchUrl()
    {
        if(!$this->haveSetedParams)
        {
            $this->setRouteParams($this->paramsKeysRegex);
        }
        
        return preg_match($this->routeWithRegex, $this->currentRoute)
            && in_array(\SenRouter\Http\Request::getRequestMethod(), $this->allowedMethod);
    }

    /**
     * @return string
     */
    public function run(){
        
        if(is_callable($this->mixes))
        {
            $this->output = $this->callCosure($this->mixes, $this->paramsValues);
        }
        else{
            $this->output = $this->callController($this->mixes, $this->paramsValues, $this->router->controllerNamespace);
        }
        
        return $this->output;
    }

    /**
     * @return bool|mixed
     */
    public function processMiddleware(){
        foreach ($this->middlewares as $middleware) {
            
            if(is_callable($middleware))
            {
                $return = $this->callCosure($middleware, $this->paramsValues);
            }
            else{
                $return = $this->callController($middleware, $this->paramsValues, $this->router->middlewareNamespace);;
            }
            
            
            if(is_string($return) || $return === false)
            {
                return $return;
            }
        }
        
        return true;
    }

    /**
     *
     */
    public function prepareRunning(){
       $this->setRouteParamsValues();
    }

    /**
     * @param $mixes callable
     * @param $paramsValues array
     * @return mixed
     */
    public function callCosure($mixes, $paramsValues){
        return call_user_func_array($mixes, $paramsValues);
    }

    /**
     * @param $mixes string
     * @param $paramsValues
     * @param $namespace
     * @return mixed
     */
    public function callController($mixes, $paramsValues, $namespace){
        
        $len = strpos($this->mixes, '@');
        $controller = $namespace.''.substr($mixes, 0, $len);
        $action = substr($mixes, $len + 1);

        $class = new $controller();
        $return = call_user_func_array([$class, $action],  $paramsValues);
        

        return $return;

        
    }

    /**
     *
     */
    public function setRouteParamsKeys(){
        $match = [];
        $start = "[a-z-A-Z_]{1}";
        $follow = "[a-z-A-Z_0-9]+";
        
        preg_match_all('#\\{'.$start.$follow.'\\}#', $this->pathPattern, $match);
        array_walk($match[0], function(&$value, $key){
            $value = str_replace('{', '', $value);
            $value = str_replace('}', '', $value);
        });
        
        $this->paramsKeys = $match[0];
    }

    /**
     *
     */
    public function setRouteParamsValues(){
        $match = [];
        preg_match($this->routeWithRegex,$this->currentRoute, $match);
        $paramsValues = array_slice(array_unique($match), 1);
        $i = 0;
        foreach($this->paramsKeys as $key => $val)
        {
            $this->paramsValues[$val] = $paramsValues[$i++];
        }
    }

    /**
     *
     */
    public function setRouteParamsKeysRegex(){
        
        $this->setRouteParamsKeys();
        foreach ($this->paramsKeys as $key => $value) {
            $value = str_replace('{', '', $value);
            $value = str_replace('}', '', $value);
            $this->paramsKeysRegex[$value] = '.*';
        }
        
        
    }

    /**
     * @param $routeParams
     */
    public function setRouteParams($routeParams)
    {
        $this->setRouteParamsKeysRegex();
        $this->routeWithRegex = preg_quote($this->pathPattern);
        
        $this->routeParams = array_merge($this->paramsKeysRegex, $routeParams);


        foreach ($this->routeParams as $name => $regex) {
            $this->routeWithRegex =  str_replace('\\{'.$name.'\\}', '('.$regex.')', $this->routeWithRegex);
        }
        
        $this->routeWithRegex = "#^".$this->routeWithRegex.'(\?.*)?$#';
    

        $this->haveSetedParams = true;
    }


    /**
     * @param $method
     */
    private function setAllowedMethod($method){
        $methods = [];
        
        if(is_string($method))
        {
            $methods[] = $method;
        }
        else if(is_array($method))
        {
            $methods = $method;
        }
        //getAllHttpMethod donne l'ensemble des methode de requette autorisee par le systeme
        foreach ($methods as $value) {
            if(in_array($value, \SenRouter\Http\Request::getAllHttpMethod()))
            {
                $this->allowedMethod[] = strtolower($value);
            }
        }
    }
    
    
}