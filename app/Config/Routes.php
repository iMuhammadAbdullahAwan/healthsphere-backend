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
        $routes->get('users/assignment', 'Users::getMyAssignment');
        $routes->put('users/assignment', 'Users::assignMyUserAdmin');
        $routes->delete('users/assignment', 'Users::unassignMyUserAdmin');
        $routes->delete('users/account', 'Users::deleteAccount');

        // Notification Routes
        $routes->get('notifications', 'NotificationController::index');
        $routes->get('notifications/unread-count', 'NotificationController::unreadCount');
        $routes->get('notifications/(:num)', 'NotificationController::show/$1');
        $routes->patch('notifications/(:num)/read', 'NotificationController::markAsRead/$1');
        $routes->patch('notifications/mark-all-read', 'NotificationController::markAllAsRead');
        $routes->delete('notifications/(:num)', 'NotificationController::delete/$1');

        // Schedule Routes (use ?filter=today|upcoming|history for date filtering)
        $routes->get('schedules', 'ScheduleController::index');
        $routes->get('schedules/stats', 'ScheduleController::stats'); // use ?type=completion for completion stats
        $routes->get('schedules/(:num)', 'ScheduleController::show/$1');
        $routes->get('schedules/(:num)/logs', 'ScheduleController::getLogs/$1');
        $routes->post('schedules', 'ScheduleController::create');
        $routes->post('schedules/logs/(:num)/complete', 'ScheduleController::completeLog/$1');
        $routes->put('schedules/(:num)', 'ScheduleController::update/$1');
        $routes->patch('schedules/(:num)/status', 'ScheduleController::updateStatus/$1');
        // Cancel / Uncancel endpoints
        $routes->post('schedules/(:num)/cancel', 'ScheduleController::cancel/$1');
        $routes->post('schedules/(:num)/uncancel', 'ScheduleController::uncancel/$1');
        // Done / Undone endpoints
        $routes->post('schedules/(:num)/done', 'ScheduleController::done/$1');
        $routes->post('schedules/(:num)/undone', 'ScheduleController::undone/$1');
        // Undo completed log
        $routes->post('schedules/logs/(:num)/undo', 'ScheduleController::undoLog/$1');
        $routes->delete('schedules/(:num)', 'ScheduleController::delete/$1');

        // Food Routes (AI-powered food analysis and logging)
        $routes->get('food-logs', 'FoodController::index'); // ?meal_type=lunch&start_date=2024-01-01
        $routes->post('food-logs', 'FoodController::create'); // Manual or confirmed food logging
        $routes->get('food-logs/summary', 'FoodController::summary'); // ?start_date=...&end_date=...
        $routes->get('food-logs/daily', 'FoodController::daily'); // ?days=7
        $routes->get('food-logs/recommendations', 'FoodController::recommendations');
        $routes->get('food-logs/(:num)', 'FoodController::show/$1');
        $routes->post('food-logs/analyze', 'FoodController::upload'); // Upload & analyze food image
        $routes->delete('food-logs/(:num)', 'FoodController::delete/$1');

        // Steps Routes
        $routes->group('steps', function ($routes) {
            $routes->get('sessions', 'StepsController::index');
            $routes->post('sessions', 'StepsController::create');
            $routes->get('sessions/(:num)', 'StepsController::show/$1');
            $routes->delete('sessions/(:num)', 'StepsController::delete/$1');
            $routes->get('tracking/status', 'StepsController::getStatus');
            $routes->patch('tracking', 'StepsController::toggleTracking');
        });

        // Exercise Routes
        $routes->group('exercises', function ($routes) {
            $routes->get('', 'ExerciseController::index');
            $routes->post('', 'ExerciseController::create');
            $routes->delete('(:num)', 'ExerciseController::delete/$1');
        });

        // Admin Routes (user_admin scoped operations; super_admin can still access)
        $routes->group('admin', ['filter' => 'admin'], function ($routes) {
            $routes->get('analytics', 'Users::adminAnalytics');
            $routes->get('users', 'Users::adminListUsers');
            $routes->get('users/(:num)/profile', 'Users::adminGetManagedUserProfile/$1');
            $routes->put('users/(:num)/profile', 'Users::adminUpdateManagedUserProfile/$1');
            $routes->post('users/(:num)/profile/image', 'Users::adminUploadManagedUserProfileImage/$1');
            $routes->delete('users/(:num)', 'Users::adminDeleteUser/$1');
        });

        // Super Admin Routes (full platform users data and stats)
        $routes->group('super-admin', ['filter' => 'superadmin'], function ($routes) {
            $routes->get('analytics', 'Users::adminAnalytics');
            $routes->get('users', 'Users::adminListUsers');
            $routes->get('user-admins', 'Users::adminListUserAdmins');
            $routes->get('users/unassigned', 'Users::adminListUnassignedUsers');
            $routes->get('users/(:num)/profile', 'Users::adminGetManagedUserProfile/$1');
            $routes->put('users/(:num)/profile', 'Users::adminUpdateManagedUserProfile/$1');
            $routes->post('users/(:num)/profile/image', 'Users::adminUploadManagedUserProfileImage/$1');
            $routes->put('users/(:num)/role', 'Users::adminUpdateRole/$1');
            $routes->put('users/(:num)/assign', 'Users::adminAssignUser/$1');
            $routes->delete('users/(:num)/assign', 'Users::adminUnassignUser/$1');
            $routes->delete('users/(:num)', 'Users::adminDeleteUser/$1');
        });
    });
});

// Legacy route
$routes->get('/', 'Home::index');
