<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InterviewChatController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;



// Route::get('/test', function () {

//     Mail::raw('This is a test email from Laravel ', function ($message) {

//         $message->to('lililampa9@gmail.com')

//                 ->subject('Testing SMTP');

//     });



//     return 'Mail sent!';

// });


Route::get('/', function () {
    return view('welcome');
});

/*
| Email verification deep-link landing page.
| The signed API verify endpoint redirects here with ?token= so the browser
| can hand the session off to the Healix mobile app.
*/
Route::get('/verify-email', [VerifyEmailController::class, 'openApp'])
    ->name('verify-email');

/*
| History-taking interview — simple Blade test page.
| Web middleware provides the session + CSRF protection used by the chat page.
*/
Route::controller(InterviewChatController::class)->prefix('interview')->group(function () {
    Route::get('/', 'show')->name('interview.show');
    Route::post('/message', 'message')->name('interview.message');
    Route::post('/voice', 'voice')->name('interview.voice');
    Route::get('/history', 'history')->name('interview.history');
    Route::get('/conversations', 'conversations')->name('interview.conversations');
    Route::post('/select/{conversation}', 'select')->name('interview.select');
    Route::post('/reset', 'reset')->name('interview.reset');
});

