<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InterviewChatController;



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

