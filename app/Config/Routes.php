<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register API routes for your application.
 | Routes are loaded by the RouteCollection object within this group.
 |
 */

// API routes grouped under the Api controllers namespace (trimmed)
$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Health check endpoint
    $routes->get('health', 'Health::index');

    // Public auth routes
    $routes->group('auth', function ($routes) {
        $routes->post('register', 'Auth::register');
        $routes->post('login', 'Auth::login');
        $routes->post('send-otp', 'Auth::sendOtp');
        $routes->post('verify-otp', 'Auth::verifyOtp');
        $routes->post('login-otp', 'Auth::loginWithOtp');
        $routes->post('refresh', 'Auth::refresh');
        $routes->post('forgot-password', 'Auth::forgotPassword');
        $routes->post('reset-password', 'Auth::resetPassword');
    });

    // Protected routes (require JWT authentication) - limited to auth, users, notifications
    $routes->group('', ['filter' => 'token.auth'], function ($routes) {
        // Auth
        $routes->post('auth/logout', 'Auth::logout');

        // Users (profile related endpoints)
        $routes->get('users/profile', 'Users::getProfile');
        $routes->put('users/profile', 'Users::updateProfile');
        $routes->post('users/profile/image', 'Users::uploadProfileImage');
        $routes->delete('users/account', 'Users::deleteAccount');

        // Notification Routes
        $routes->get('notifications', 'NotificationController::index');
        $routes->get('notifications/unread-count', 'NotificationController::unreadCount');
        $routes->get('notifications/(:num)', 'NotificationController::show/$1');
        $routes->patch('notifications/(:num)/read', 'NotificationController::markAsRead/$1');
        $routes->patch('notifications/mark-all-read', 'NotificationController::markAllAsRead');
        $routes->delete('notifications/(:num)', 'NotificationController::delete/$1');
    });
});

// Legacy route
$routes->get('/', 'Home::index');
