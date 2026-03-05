<?php

/**
 * The Template for displaying all single posts
 */

namespace App;

use App\Http\Controllers\Controller;
use Rareloop\Lumberjack\Http\Responses\TimberResponse;
use Rareloop\Lumberjack\Post;
use Timber\Timber;

class SingleProfileController extends Controller
{
    public function handle()
    {
        $context = Timber::get_context();
        $profile = $context["post"];

        $context['title'] = $profile->title;
        
        $creation_year = get_field("creation_year", $profile);
        $context['creation_year'] = $creation_year;

        // dump(get_field("creation_year", $profile));

       

        return new TimberResponse('templates/profile-page.twig', $context);
    }
}
