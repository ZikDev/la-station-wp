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
        
        $context['category'] = get_field("category", $profile);
        $context['domain_technician'] = get_field("domain_technician", $profile);
        $context['domain_organization'] = get_field("domain_organization", $profile);
        $context['style'] = get_field("style", $profile);
        $context['instrument'] = get_field("instrument", $profile);
        $context['location_type'] = get_field("location_type", $profile);
        $context['name'] = get_field("name", $profile);
        $context['first_name'] = get_field("first_name", $profile);
        $context['creation_year'] = get_field("creation_year", $profile);
        $context['email'] = get_field("email", $profile);
        $context['phone_number'] = get_field("phone_number", $profile);
        $context['district'] = get_field("district", $profile);
        $context['years_of_experience'] = get_field("years_of_experience", $profile);
        $context['profile_picture'] = get_field("profile_picture", $profile);
        $context['description'] = get_field("description", $profile);
        $context['website'] = get_field("website", $profile);
        $context['video_1'] = get_field("video_1", $profile);
        $context['video_2'] = get_field("video_2", $profile);
        $context['video_3'] = get_field("video_3", $profile);
        $context['instagram'] = get_field("instagram", $profile);
        $context['youtube'] = get_field("youtube", $profile);
        $context['tiktok'] = get_field("tiktok", $profile);
        $context['facebook'] = get_field("facebook", $profile);

        // dump($context['test']);

        return new TimberResponse('templates/profile-page.twig', $context);
    }
}
