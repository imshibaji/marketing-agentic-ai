<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'DashboardController::index', ['filter' => 'webauth']);

// Install Routes
$routes->get('install', 'InstallController::index');
$routes->get('install.php', 'InstallController::action');
$routes->post('install.php', 'InstallController::action');

// Auth page routes (guest only)
$routes->get('login', 'AuthController::login', ['filter' => 'guest']);
$routes->get('register', 'AuthController::register', ['filter' => 'guest']);
$routes->get('logout', 'AuthController::logout');

// Protected page routes (webauth required)
$routes->group('', ['filter' => 'webauth'], function($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('campaigns', 'CampaignPageController::index');
    $routes->get('leads', 'LeadPageController::index');
    $routes->get('contacts', 'ContactPageController::index');
    $routes->get('outreach', 'OutreachPageController::index');
    $routes->get('users', 'UserPageController::index');
    $routes->get('plans', 'PlanPageController::index');
});

// Serve Plugin Assets
$routes->get('plugin-asset.php', 'PluginAssetsController::serve');
$routes->get('plugins/(:any)/(:any)', 'PluginAssetsController::serveDirect/$1/$2');

// Public API routes
$routes->post('api/login.php', 'Api\AuthController::login');
$routes->post('api/logout.php', 'Api\AuthController::logout');
$routes->get('api/auth-status.php', 'Api\AuthController::check');
$routes->post('api/register.php', 'Api\AuthController::register');
$routes->post('api/otp.php', 'Api\AuthController::otp');

// Authenticated API routes with 'auth' filter
$routes->group('api', ['filter' => 'auth'], function($routes) {
    // Campaigns
    $routes->get('campaigns.php', 'Api\CampaignController::index');
    $routes->post('campaigns.php', 'Api\CampaignController::create');
    $routes->delete('campaigns.php', 'Api\CampaignController::delete');
    $routes->get('run-campaign.php', 'Api\CampaignController::run');

    // Leads
    $routes->get('leads.php', 'Api\LeadController::index');
    $routes->post('leads.php', 'Api\LeadController::create');
    $routes->delete('leads.php', 'Api\LeadController::delete');
    $routes->get('run-leads.php', 'Api\LeadController::runLeads');
    $routes->get('run-scraper.php', 'Api\LeadController::runScraper');

    // Settings
    $routes->get('settings.php', 'Api\SettingController::index');
    $routes->post('settings.php', 'Api\SettingController::create');

    // Plans
    $routes->get('plans.php', 'Api\PlanController::index');
    $routes->post('plans.php', 'Api\PlanController::create');
    $routes->delete('plans.php', 'Api\PlanController::delete');

    // Users & Stats & Usage & Activity & Profile
    $routes->get('users.php', 'Api\UserController::index');
    $routes->post('users.php', 'Api\UserController::create');
    $routes->delete('users.php', 'Api\UserController::delete');
    $routes->get('activity.php', 'Api\UserController::activity');
    $routes->get('stats.php', 'Api\UserController::stats');
    $routes->get('usage.php', 'Api\UserController::usage');
    $routes->get('profile.php', 'Api\UserController::profile');
    $routes->match(['put', 'post'], 'profile.php', 'Api\UserController::profile');

    // Chats
    $routes->get('chats.php', 'Api\ChatController::index');
    $routes->post('chats.php', 'Api\ChatController::create');

    // Notifications
    $routes->get('notifications.php', 'Api\NotificationController::index');
    $routes->post('notifications.php', 'Api\NotificationController::create');
    $routes->delete('notifications.php', 'Api\NotificationController::delete');

    // Outreach
    $routes->post('outreach.php', 'Api\OutreachController::send');
    $routes->post('generate-outreach.php', 'Api\OutreachController::generate');

    // System Operations
    $routes->get('backup.php', 'Api\SystemController::backup');
    $routes->post('restore.php', 'Api\SystemController::restore');
    $routes->post('reset-app.php', 'Api\SystemController::resetApp');
    $routes->post('load-demo.php', 'Api\SystemController::loadDemo');
    $routes->get('plugins.php', 'Api\SystemController::listPlugins');
    $routes->post('plugins.php', 'Api\SystemController::managePlugin');
    $routes->match(['get', 'post'], 'plugin-route.php', 'Api\PluginRouteController::dispatch');
});

