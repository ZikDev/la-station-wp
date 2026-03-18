<?php

/**
 * Contrôleur pour l'affichage des pages WordPress.
 * Gère à la fois la page d'accueil (avec filtres de profils) et les pages génériques.
 */

namespace App;

use App\Http\Controllers\Controller;
use Rareloop\Lumberjack\Http\Responses\TimberResponse;
use Timber\Timber;

class PageController extends Controller
{
    public function handle()
    {
        $context = Timber::get_context();

        $page = $context['post'];
        $context['title'] = $page->title;
        $context['content'] = $page->content;

        // La page d'accueil a une logique spécifique : filtres de profils
        if (is_front_page()) {
            // Champs ACF spécifiques à la page d'accueil
            $context['catch_phrase'] = get_field("catch_phrase", $page);
            $context['introduction'] = get_field("introduction", $page);

            // Récupérer et transmettre les filtres actifs au template
            $filters = $this->getFilters();
            $context['filters'] = $filters;

            // Construire et exécuter la requête de profils filtrés
            $query_args = $this->buildQuery($filters);
            $profiles = new \WP_Query($query_args);
            $context['profiles'] = $profiles->have_posts() ? $profiles->get_posts() : null;

            // Termes des taxonomies pour alimenter les filtres du formulaire
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

        // Toutes les autres pages utilisent le template générique
        return new TimberResponse('templates/generic-page.twig', $context);
    }

    /**
     * Récupère et assainit les paramètres GET du formulaire de filtrage.
     * Les paramètres sont préfixés "f_" pour éviter les conflits avec les query vars WordPress.
     * Les champs multi-valeurs (checkboxes) retournent un tableau.
     */
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

    /**
     * Construit les arguments WP_Query à partir des filtres actifs.
     * Les champs ACF de type taxonomie sont stockés en meta (sérialisés par ACF),
     * d'où l'utilisation de meta_query avec LIKE plutôt que tax_query.
     */
    private function buildQuery(array $filters): array
    {
        $query_args = [
            'post_type' => 'profile',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'nopaging' => true,
            'orderby' => 'post_title',
            'order' => 'ASC',
            'meta_query' => [],
            'tax_query' => [],
        ];

        // Filtre par catégorie de profil (champ ACF select, valeur unique)
        if (!empty($filters['category'])) {
            $query_args['meta_query'][] = [
                'key' => 'category',
                'value' => $filters['category'],
                'compare' => '=',
            ];
        }

        // Filtre par domaine(s) d'organisation (champ ACF taxonomie, plusieurs valeurs possibles).
        // ACF stocke les IDs des termes sérialisés en meta → on convertit les slugs en IDs
        // et on cherche avec LIKE. La relation OR retourne les profils ayant au moins un des domaines cochés.
        if (!empty($filters['domain_organization'])) {
            $conditions = ['relation' => 'OR'];
            foreach ($filters['domain_organization'] as $slug) {
                $term = get_term_by('slug', $slug, 'domain_organization');
                if ($term) {
                    $conditions[] = [
                        'key' => 'domain_organization',
                        'value' => "\"$term->term_id\"",
                        'compare' => 'LIKE',
                    ];
                }
            }
            if (\count($conditions) > 1) {
                $query_args['meta_query'][] = $conditions;
            }
        }


        // ----------------
        // REMPLIR ICI LA SUITE DES CONDITIONS POUR LES AUTRES INPUTS (domain_technician, instrument, style, location_type, district)
        // c'est exactement le même principe que le code pour "domain_organization" à la ligne 138.
        // il faut simplement remplacer "domain_organization" par le nom du filtre (par ex. "instrument")

        if (!empty($filters['domain_technician'])) {
            $conditions = ['relation' => 'OR'];
            foreach ($filters['domain_technician'] as $slug) {
                $term = get_term_by('slug', $slug, 'domain_technician');
                if ($term) {
                    $conditions[] = [
                        'key' => 'domain_technician',
                        'value' => "\"$term->term_id\"",
                        'compare' => 'LIKE',
                    ];
                }
            }
            if (\count($conditions) > 1) {
                $query_args['meta_query'][] = $conditions;
            }
        }

        if (!empty($filters['style'])) {
            $conditions = ['relation' => 'OR'];
            foreach ($filters['style'] as $slug) {
                $term = get_term_by('slug', $slug, 'style');
                if ($term) {
                    $conditions[] = [
                        'key' => 'style',
                        'value' => "\"$term->term_id\"",
                        'compare' => 'LIKE',
                    ];
                }
            }
            if (\count($conditions) > 1) {
                $query_args['meta_query'][] = $conditions;
            }
        }

        if (!empty($filters['instrument'])) {
            $conditions = ['relation' => 'OR'];
            foreach ($filters['instrument'] as $slug) {
                $term = get_term_by('slug', $slug, 'instrument');
                if ($term) {
                    $conditions[] = [
                        'key' => 'instrument',
                        'value' => "\"$term->term_id\"",
                        'compare' => 'LIKE',
                    ];
                }
            }
            if (\count($conditions) > 1) {
                $query_args['meta_query'][] = $conditions;
            }
        }

        if (!empty($filters['location_type'])) {
            $conditions = ['relation' => 'OR'];
            foreach ($filters['location_type'] as $slug) {
                $term = get_term_by('slug', $slug, 'location_type');
                if ($term) {
                    $conditions[] = [
                        'key' => 'location_type',
                        'value' => "\"$term->term_id\"",
                        'compare' => 'LIKE',
                    ];
                }
            }
            if (\count($conditions) > 1) {
                $query_args['meta_query'][] = $conditions;
            }
        }

        // ----------------

        // Si plusieurs conditions meta, elles doivent toutes être satisfaites (AND)
        if (\count($query_args['meta_query']) > 1) {
            $query_args['meta_query']['relation'] = 'AND';
        }

        return $query_args;
    }

}
 