<?php

/**
 * ========================================
 * CONFIGURATION DU THÈME
 * ========================================
 * Fichier principal pour la configuration du thème WordPress
 * Gestion des assets, des hooks, et des fonctionnalités personnalisées
 */

// ========================================
// CHARGEMENT DES ASSETS (CSS/JS)
// ========================================

/**
 * Charge les feuilles de style et scripts JavaScript du thème
 * 
 * @return void
 */
add_action('wp_enqueue_scripts', function () {
  $theme_uri = get_template_directory_uri();
  $theme_path = get_template_directory();

  // CSS compilé depuis SCSS
  if (file_exists($theme_path . '/dist/css/style.css')) {
    wp_enqueue_style(
      'theme-style',
      $theme_uri . '/dist/css/style.css',
      [],
      filemtime($theme_path . '/dist/css/style.css')
    );
  }

  // JS
  if (file_exists($theme_path . '/dist/js/main.js')) {
    wp_enqueue_script(
      'theme-script',
      $theme_uri . '/dist/js/main.js',
      [],
      filemtime($theme_path . '/dist/js/main.js'),
      true
    );
  }

  // Configuration des données JavaScript pour les formulaires de membres
  wp_localize_script('theme-script', 'profileFormData', [
    'nonce' => wp_create_nonce('wp_rest'),
    'apiUrl' => home_url('/wp-json/mcf/v1/profile'),
    'messages' => [
      'success' => "Requête envoyée avec succès !",
      'error' => "Erreur",
      'unexpected' => "Une erreur est survenue. Veuillez reéssayer plus tard.",
    ],
  ]);
});

// ========================================
// CONFIGURATION SMTP
// ========================================

add_action('phpmailer_init', function ($phpmailer) {
  $phpmailer->isSMTP();
  $phpmailer->Host       = getenv('SMTP_HOST');
  $phpmailer->Port       = (int) getenv('SMTP_PORT');
  $phpmailer->SMTPAuth   = filter_var(getenv('SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN);
  $phpmailer->Username   = getenv('SMTP_USER');
  $phpmailer->Password   = getenv('SMTP_PASS');
  $phpmailer->SMTPSecure = getenv('SMTP_SECURE') ?: '';
  $phpmailer->From       = getenv('SMTP_FROM') ?: get_option('admin_email');
  $phpmailer->FromName   = getenv('SMTP_FROM_NAME') ?: get_bloginfo('name');
});

// ========================================
// GESTION DES TITRES ET MÉTADONNÉES
// ========================================

/**
 * Personnalise les titres des pages selon le contexte
 * 
 * @return string Titre formaté pour la page courante
 */
function custom_theme_custom_page_title()
{
  $site_name = get_bloginfo('name');

  // Page d'erreur 404
  if (is_404()) {
    return '404 | ' . $site_name;
  }

  // Pages standards
  return get_the_title() . ' | ' . $site_name;
}
add_filter('pre_get_document_title', 'custom_theme_custom_page_title', 10);
add_filter('wp_title', 'custom_theme_custom_page_title', 10);

// ========================================
// CONFIGURATION TWIG (TIMBER)
// ========================================

/**
 * Étend Twig avec des fonctions et filtres WordPress personnalisés
 * 
 * @param \Twig\Environment $twig Instance Twig
 * @return \Twig\Environment Twig configuré
 */
function custom_theme_extend_twig($twig)
{
  // ========================================
  // FONCTIONS WORDPRESS DANS TWIG
  // ========================================


  // Fonctions d'information du site
  $twig->addFunction(new \Twig\TwigFunction('wp_get_theme', 'wp_get_theme'));
  $twig->addFunction(new \Twig\TwigFunction('get_bloginfo', 'get_bloginfo'));


  // Fonctions d'URLs
  $twig->addFunction(new \Twig\TwigFunction('get_site_url', 'get_site_url'));
  $twig->addFunction(new \Twig\TwigFunction('get_template_directory_uri', 'get_template_directory_uri'));
  $twig->addFunction(new \Twig\TwigFunction('home_url', 'home_url'));
  $twig->addFunction(new \Twig\TwigFunction('admin_url', 'admin_url'));
  $twig->addFunction(new \Twig\TwigFunction('get_permalink', 'get_permalink'));
  $twig->addFunction(new \Twig\TwigFunction('wp_get_attachment_image', 'wp_get_attachment_image'));

  // Fonctions d'authentification
  $twig->addFunction(new \Twig\TwigFunction('wp_login_url', 'wp_login_url'));
  $twig->addFunction(new \Twig\TwigFunction('wp_logout_url', 'wp_logout_url'));

  $twig->addFunction(new \Twig\TwigFunction('get_field', 'get_field'));

  return $twig;
}
add_filter('timber/twig', 'custom_theme_extend_twig');

// ========================================
// CONFIGURATION ACF (ADVANCED CUSTOM FIELDS)
// ========================================

/**
 * Définit le dossier de sauvegarde des fichiers JSON d'ACF
 * 
 * @return string Chemin vers le dossier acf-json
 */
function mcf_acf_json_save_point()
{
  return get_stylesheet_directory() . '/acf-json';
}
add_filter('acf/settings/save_json', 'mcf_acf_json_save_point');

/**
 * Définit le dossier de chargement des fichiers JSON d'ACF
 * 
 * @param array $paths Chemins existants
 * @return array Chemins mis à jour
 */
function mcf_acf_json_load_point($paths)
{
  $paths[] = get_stylesheet_directory() . '/acf-json';
  return $paths;
}
add_filter('acf/settings/load_json', 'mcf_acf_json_load_point');
