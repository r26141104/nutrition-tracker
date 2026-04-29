<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Vision（食物拍照辨識用）
    |--------------------------------------------------------------------------
    | 兩種設定方式（FoodVisionService 會自動擇一）：
    |
    | 1. credentials_path（本機開發用）
    |    JSON 檔的絕對路徑。
    |    範例：GOOGLE_CREDENTIALS_PATH=C:/Users/ASUS/Downloads/xxxxx.json
    |
    | 2. credentials_json_base64（雲端部署用）
    |    把整個 JSON 檔內容用 base64 編碼後放在環境變數。
    |    產生指令（Windows PowerShell）：
    |       $b = [Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\xxx.json"))
    |       $b | Set-Clipboard
    */
    'google_vision' => [
        'credentials_path'         => env('GOOGLE_CREDENTIALS_PATH'),
        'credentials_json_base64'  => env('GOOGLE_CREDENTIALS_JSON_BASE64'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini API（食物營養估算用）
    |--------------------------------------------------------------------------
    | 跟 Vision 不同：Gemini 用 API key 認證，去 https://aistudio.google.com/app/apikey
    | 申請即可，免費額度每天 1500 次。
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
