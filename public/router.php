<?php

/**
 * PHP 內建 server 的 router script。
 *
 * 為什麼需要這個檔案？
 *   PHP 內建 server (php -S) 在使用 router 時，**不會**自動辨識靜態檔案，
 *   所有請求（包含 .js、.css、圖片）都會被丟給 router 執行。
 *   這會讓 Vue 的 .js 檔被當成 PHP 跑，回傳 HTML，Vue 載入失敗。
 *
 * 這個 router 做兩件事：
 *   1. 如果請求對應到實體靜態檔（public/build/xxx.js 等）→ 直接吐檔（return false）
 *   2. 否則 → 交給 Laravel 的 index.php 處理
 *
 * 對 production 環境也夠用，但若要跑大流量請改用 nginx 或 apache。
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 對應到 public 目錄裡的實體檔案
$file = __DIR__ . $uri;

// 是檔案而不是 router 自己 → 讓 PHP server 直接服務
if ($uri !== '/' && is_file($file) && realpath($file) !== __FILE__) {
    return false;
}

// 否則交給 Laravel
require __DIR__ . '/index.php';
