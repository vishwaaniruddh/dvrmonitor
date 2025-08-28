<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/realtime-dashboard.html');
});

// Auto-redirect to dashboard
Route::get('/dashboard', function () {
    return redirect('/realtime-dashboard.html');
});
