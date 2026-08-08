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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    ],

    'analytics' => [
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
        'meta_pixel_id' => env('META_PIXEL_ID'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'hubtel_client_id' => env('HUBTEL_CLIENT_ID'),
        'hubtel_client_secret' => env('HUBTEL_CLIENT_SECRET'),
        'hubtel_sender' => env('HUBTEL_SENDER', 'CityShop'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
    ],

    'fcm' => [
        // Legacy FCM server key from Firebase Console → Project settings → Cloud Messaging.
        // Leave blank to skip OS push (in-app notifications still work).
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'webrtc' => [
        // STUN alone cannot traverse the carrier-grade NAT used by most Ghanaian
        // mobile networks, so a TURN relay is required for calls between two
        // phones on mobile data.
        'stun_urls' => env('WEBRTC_STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'),
        'turn_urls' => env('WEBRTC_TURN_URLS'),
        'turn_username' => env('WEBRTC_TURN_USERNAME'),
        'turn_password' => env('WEBRTC_TURN_PASSWORD'),
    ],

];
