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

            // Récupérer les paramètres de filtrage
            $filters = $this->getFilters();
            $context['filters'] = $filters;

            $context['domains_organization'] = get_terms([
                'taxonomy' => 'domain_organization',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            $context['domains_technician'] = get_terms([
                'taxonomy' => 'domain_technician',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',

            ]);

            $context['instruments'] = get_terms([
                'taxonomy' => 'instrument',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            $context['styles'] = get_terms([
                'taxonomy' => 'style',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',

            ]);

            $context['location_types'] = get_terms([
                'taxonomy' => 'location_type',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);

            $context['districts'] = get_terms([
                'taxonomy' => 'district',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',

            ]);


            return new TimberResponse('templates/home.twig', $context);
        }


        return new TimberResponse('templates/generic-page.twig', $context);
    }

    private function getFilters(): array
    {
        return [
            'search' => sanitize_text_field($_GET['f_search'] ?? ''),
            'category' => sanitize_text_field($_GET['f_category'] ?? ''),
            'domain_organization' => array_map('sanitize_text_field', $_GET['f_domain_organization'] ?? []),
            'domain_technician' => array_map('sanitize_text_field', $_GET['f_domain_technician'] ?? []),
            'instrument' => array_map('sanitize_text_field', $_GET['f_instrument'] ?? []),
            'style' => array_map('sanitize_text_field', $_GET['f_style'] ?? []),
            'location_type' => array_map('sanitize_text_field', $_GET['f_location_type'] ?? []),
            'district' => array_map('sanitize_text_field', $_GET['f_district'] ?? []),
        ];
    }
}
 