<?php

// Route::get('/', 'WelcomeController@index');

// Route::get('home', 'HomeController@index');
Route::post('api/save', 'MainController@save');
Route::get('api/save', 'MainController@save');

// Route::controllers([
// 	'auth' => 'Auth\AuthController',
// 	'password' => 'Auth\PasswordController',
// ]);
