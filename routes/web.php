<?php

use Illuminate\Support\Facades\Route;

// 所有非 API 的請求都丟給 welcome.blade.php，由 Vue Router 處理前端路由
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api|up).*$');
