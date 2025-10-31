<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    return view('welcome');

});

// Route::get('/test', function () {

//     Mail::raw('This is a test email from Laravel ', function ($message) {

//         $message->to('lililampa9@gmail.com')

//                 ->subject('Testing SMTP');

//     });



//     return 'Mail sent!';

// });