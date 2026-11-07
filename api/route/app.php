<?php
use think\facade\Route;

Route::get('api/v1/health', 'api.PublicApi/health');
Route::get('api/v1/home', 'api.PublicApi/home');
Route::get('api/v1/company', 'api.PublicApi/company');
Route::get('api/v1/product/categories', 'api.PublicApi/categories');
Route::get('api/v1/products', 'api.PublicApi/products');
Route::get('api/v1/products/:id', 'api.PublicApi/product')->pattern(['id' => '\\d+']);
Route::get('api/v1/articles', 'api.PublicApi/articles');
Route::get('api/v1/articles/:id', 'api.PublicApi/article')->pattern(['id' => '\\d+']);
Route::get('api/v1/contact', 'api.PublicApi/contact');
Route::post('api/v1/inquiries', 'api.PublicApi/inquiry');

Route::post('api/v1/admin/login', 'api.AdminApi/login');
Route::post('api/v1/admin/logout', 'api.AdminApi/logout');
Route::post('api/v1/admin/security/password', 'api.AdminApi/password');
Route::post('api/v1/admin/security/revoke-sessions', 'api.AdminApi/revokeSessions');
Route::get('api/v1/admin/me', 'api.AdminApi/me');
Route::get('api/v1/admin/dashboard', 'api.AdminApi/dashboard');
Route::get('api/v1/admin/:resource', 'api.AdminApi/index')->pattern(['resource' => '[a-z][a-z0-9-]*']);
Route::get('api/v1/admin/:resource/:id', 'api.AdminApi/show')->pattern(['resource' => '[a-z][a-z0-9-]*', 'id' => '\\d+']);
Route::post('api/v1/admin/:resource', 'api.AdminApi/create')->pattern(['resource' => '[a-z][a-z0-9-]*']);
Route::put('api/v1/admin/:resource/:id', 'api.AdminApi/update')->pattern(['resource' => '[a-z][a-z0-9-]*', 'id' => '\\d+']);
Route::delete('api/v1/admin/:resource/:id', 'api.AdminApi/delete')->pattern(['resource' => '[a-z][a-z0-9-]*', 'id' => '\\d+']);
Route::post('api/v1/admin/upload/image', 'api.AdminApi/upload');
