<?php 

use SenRouter\Http\Response;

class HomeController{
    
    public function home($num1, $num2){
        return Response::withJson([
            'result' => $num1 + $num2
        ]);
    }
}