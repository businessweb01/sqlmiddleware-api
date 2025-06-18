<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
// Route::get('/check-openssl', function () {
//     return function_exists('openssl_decrypt') ? 'OpenSSL is enabled ✅' : 'OpenSSL is NOT enabled ❌';
// });
