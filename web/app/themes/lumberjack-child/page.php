<?php

/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 */

namespace App;

use App\Http\Controllers\Controller;
use Rareloop\Lumberjack\Http\Responses\TimberResponse;
use Rareloop\Lumberjack\Page;
use Timber\Timber;

class PageController extends Controller
{
    public function handle()
    {
        $context = Timber::get_context();
        
        $page = $context['post'];
        $context['title'] = $page->title;
        $context['content'] = $page->content;

        if (is_front_page()) {
            $context['catch_phrase'] = get_field("catch_phrase", $page);
            $context['introduction'] = get_field("introduction", $page);

            return new TimberResponse('templates/home.twig', $context);
        }


        return new TimberResponse('templates/generic-page.twig', $context);
    }
}
 