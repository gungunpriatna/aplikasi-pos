<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect(route('login'));
});

Auth::routes();

Route::group(['middleware' => 'auth'], function() {
    Route::resource('/kategori', 'CategoryController')->except([
        'create', 'show'
    ]);

    Route::resource('produk', 'ProductController');


    Route::get('/home', 'HomeController@index')->name('home');

});


Route::resource('/role', 'RoleController')->except([
    'create', 'show', 'edit', 'update'
]);

Route::resource('/users', 'UserController')->except([
    'show'
]);
Route::get('/users/roles/{id}', 'UserController@roles')->name('users.roles');