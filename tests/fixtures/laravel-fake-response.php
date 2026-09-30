<?php

/* @HINT: fake of new Illuminate\Http\Response('Hello World', 200, []) */
 
use LaravelFaked\Http\Lifecycle\FakeResponse;
 
return new FakeResponse('Hello World', 200, []);

?>
