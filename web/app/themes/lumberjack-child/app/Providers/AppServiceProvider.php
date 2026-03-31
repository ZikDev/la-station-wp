<?php

namespace App\Providers;

use Rareloop\Lumberjack\Providers\ServiceProvider;
use App\Http\Controllers\ProfileController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any app specific items into the container
     */
    public function register()
    {
        add_action('admin_post_nopriv_create_profile', function () {
            (new ProfileController())->createProfile();
        });
        add_action('admin_post_create_profile', function () {
            (new ProfileController())->createProfile();
        });
    }

    /**
     * Perform any additional boot required for this application
     */
    public function boot()
    {
    }
}
