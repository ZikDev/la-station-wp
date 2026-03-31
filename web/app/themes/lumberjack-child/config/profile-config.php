<?php

/* ========================================
   CONSTANTES DE CONFIGURATION
======================================== */
define('PROFILE_POST_TYPE', 'profile');
define('PROFILE_ROLE', 'profile');

/* ========================================
   FONCTIONS UTILITAIRES SÉCURISÉES
======================================== */

/**
 * Vérifie de manière sécurisée si un utilisateur a un rôle spécifique
 */
function safely_user_has_role($user_id, $role)
{
  $user = get_userdata($user_id);
  if (!$user || !isset($user->roles) || !is_array($user->roles)) {
    return false;
  }
  return in_array($role, $user->roles);
}

/**
 * Vérifie de manière sécurisée si l'utilisateur actuel a un rôle spécifique
 */
function safely_current_user_has_role($role)
{
  $user = wp_get_current_user();
  if (!$user || !$user->exists() || !isset($user->roles) || !is_array($user->roles)) {
    return false;
  }
  return in_array($role, $user->roles);
}

/**
 * Vérifie si l'utilisateur actuel est un profile (avec cache)
 */
function is_profile_user()
{
  static $is_profile = null;
  if ($is_profile === null) {
    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
      $is_profile = false;
    } else {
      $is_profile = safely_current_user_has_role(PROFILE_ROLE) && !current_user_can('administrator');
    }
  }
  return $is_profile;
}

/* ========================================
   GESTION DES CAPACITÉS ET RÔLES
======================================== */

/**
 * Configure les capacités du rôle profile
 */
add_action('init', 'setup_profile_role_capabilities');
function setup_profile_role_capabilities()
{
  // Créer le rôle s'il n'existe pas encore
  if (!get_role(PROFILE_ROLE)) {
    add_role(PROFILE_ROLE, 'Profile', ['read' => true]);
  }

  $profile_role = get_role(PROFILE_ROLE);
  if ($profile_role) {
    // Capacités de base pour accéder à l'admin
    $profile_role->add_cap('read');
    $profile_role->add_cap('access_admin');

    // Capacités pour le CPT Members
    $profile_role->add_cap('edit_profiles');
    $profile_role->add_cap('read_profiles');
    $profile_role->add_cap('edit_profile');
    $profile_role->add_cap('read_profile');
    $profile_role->add_cap('publish_profiles');
    $profile_role->add_cap('edit_published_profiles');

    // Capacités générales d'édition (sera filtrée par nos fonctions)
    $profile_role->add_cap('edit_posts');
    $profile_role->add_cap('edit_published_posts');
    $profile_role->add_cap('publish_posts');

    // Retirer explicitement les capacités de suppression
    $profile_role->remove_cap('delete_profiles');
    $profile_role->remove_cap('delete_published_profiles');
    $profile_role->remove_cap('delete_posts');
    $profile_role->remove_cap('delete_published_posts');
  }
}

/* ========================================
   CONTRÔLES D'ACCÈS ET SÉCURITÉ
======================================== */

/**
 * Filtre les posts dans l'admin pour les profiles
 */
add_action('pre_get_posts', 'restrict_profile_cpt_access');
function restrict_profile_cpt_access($query)
{
  // Vérifier si on est dans l'admin et si c'est la requête principale
  if (!is_admin() || !$query->is_main_query()) {
    return;
  }

  // Vérification sécurisée du rôle utilisateur
  if (!is_profile_user()) {
    return;
  }

  // Vérifier si on est sur la page de liste du CPT Members
  global $pagenow;
  if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == PROFILE_POST_TYPE) {
    // Ne pas filtrer - laisser voir tous les members dans la liste
    return;
  }
}

/**
 * Empêche l'édition des posts qui ne leur appartiennent pas
 */
add_action('admin_init', 'prevent_profile_edit_others');
function prevent_profile_edit_others()
{
  // Vérifier si on est sur la page d'édition d'un post
  global $pagenow;
  if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
    return;
  }

  // Vérification sécurisée du rôle utilisateur
  if (!is_profile_user()) {
    return;
  }



  // Si on édite un post existant
  if (isset($_GET['post']) && $_GET['post']) {
    $post_id = intval($_GET['post']);
    $post = get_post($post_id);

    // Vérifier si c'est un CPT Profile et si l'utilisateur n'est pas l'auteur
    if ($post && $post->post_type === PROFILE_POST_TYPE && $post->post_author != get_current_user_id()) {
      wp_die('Vous n\'avez pas l\'autorisation d\'éditer ce profil.', 'Accès refusé', array('response' => 403));
    }
  }
}

/**
 * Filtre les capacités pour empêcher la modification des posts d'autres utilisateurs
 */
add_filter('map_meta_cap', 'restrict_profile_edit_capabilities', 10, 4);
function restrict_profile_edit_capabilities($caps, $cap, $user_id, $args)
{
  // Vérifications de sécurité renforcées
  if (!$user_id || !is_numeric($user_id)) {
    return $caps;
  }

  // Vérifier si l'utilisateur a le rôle member de manière sécurisée
  if (!safely_user_has_role($user_id, PROFILE_ROLE)) {
    return $caps;
  }

  // Vérifier les capacités d'édition
  if ($cap === 'edit_post' || $cap === 'delete_post') {
    if (isset($args[0]) && is_numeric($args[0])) {
      $post_id = $args[0];
      $post = get_post($post_id);

      // Si c'est un CPT Members et que l'utilisateur n'est pas l'auteur
      if ($post && $post->post_type === PROFILE_POST_TYPE && $post->post_author != $user_id) {
        return array('do_not_allow');
      }
    }
  }

  return $caps;
}

/* ========================================
   INTERFACE UTILISATEUR - LISTE DES POSTS
======================================== */

/**
 * Masque les actions d'édition pour les posts qui ne leur appartiennent pas
 */
add_filter('post_row_actions', 'remove_profile_row_actions', 10, 2);
function remove_profile_row_actions($actions, $post)
{
  // Vérification sécurisée de l'objet post
  if (!$post || !isset($post->post_type) || !isset($post->post_author)) {
    return $actions;
  }

  // Vérifier si c'est un CPT Members et si l'utilisateur est un member
  if ($post->post_type === PROFILE_POST_TYPE && is_profile_user()) {
    // Si l'utilisateur n'est pas l'auteur du post
    if ($post->post_author != get_current_user_id()) {
      // Supprimer les actions d'édition
      unset($actions['edit']);
      unset($actions['inline hide-if-no-js']);
      unset($actions['trash']);
      unset($actions['delete']);
    }
  }

  return $actions;
}

/* ========================================
   INTERFACE UTILISATEUR - MENUS ADMIN
======================================== */

/**
 * Masque les menus d'admin inutiles pour les members
 */
add_action('admin_menu', 'remove_admin_menus_for_profiles');
function remove_admin_menus_for_profiles()
{
  if (is_profile_user()) {
    // Supprimer les menus non nécessaires
    remove_menu_page('edit.php'); // Articles
    remove_menu_page('edit.php?post_type=page'); // Pages
    remove_menu_page('upload.php'); // Médias
    remove_menu_page('edit-comments.php'); // Commentaires
    remove_menu_page('themes.php'); // Apparence
    remove_menu_page('plugins.php'); // Extensions
    remove_menu_page('users.php'); // Utilisateurs
    remove_menu_page('tools.php'); // Outils
    remove_menu_page('options-general.php'); // Réglages
    remove_menu_page('index.php'); // Tableau de bord

    // Supprimer le sous-menu "Ajouter nouveau membre"
    remove_submenu_page('edit.php?post_type=' . PROFILE_POST_TYPE, 'post-new.php?post_type=' . PROFILE_POST_TYPE);
  }
}

/* ========================================
   INTERFACE UTILISATEUR - BARRE D'ADMIN
======================================== */

/**
 * Masque les éléments indésirables de la barre d'administration
 */
add_action('wp_before_admin_bar_render', 'remove_admin_bar_items_for_profiles');
function remove_admin_bar_items_for_profiles()
{
  if (!is_profile_user()) {
    return;
  }

  global $wp_admin_bar;

  // Vérification de sécurité pour l'objet admin bar
  if (!$wp_admin_bar || !method_exists($wp_admin_bar, 'remove_node')) {
    return;
  }

  // Supprimer les boutons de création de contenu
  $wp_admin_bar->remove_node('new-content'); // Groupe entier "Nouveau"
  $wp_admin_bar->remove_node('new-post'); // Nouvel article
  $wp_admin_bar->remove_node('new-page'); // Nouvelle page
  $wp_admin_bar->remove_node('new-media'); // Nouveau média
  $wp_admin_bar->remove_node('new-user'); // Nouvel utilisateur

  // Supprimer les boutons d'édition
  $wp_admin_bar->remove_node('edit'); // Bouton "Modifier"
  $wp_admin_bar->remove_node('edit-comments'); // Modifier les commentaires
  $wp_admin_bar->remove_node('comments'); // Commentaires

  // Supprimer d'autres éléments indésirables
  $wp_admin_bar->remove_node('wp-logo'); // Logo WordPress
  $wp_admin_bar->remove_node('updates'); // Mises à jour
  $wp_admin_bar->remove_node('themes'); // Thèmes
  $wp_admin_bar->remove_node('customize'); // Personnaliser
  $wp_admin_bar->remove_node('widgets'); // Widgets
  $wp_admin_bar->remove_node('menus'); // Menus
  $wp_admin_bar->remove_node('background'); // Arrière-plan
  $wp_admin_bar->remove_node('header'); // En-tête

  // Masquer la barre d'admin sur le front-end
  if (!is_admin()) {
    show_admin_bar(false);
  }
}

/**
 * CSS supplémentaire pour masquer les éléments restants dans l'admin bar
 */
add_action('admin_head', 'hide_admin_bar_elements_css');
add_action('wp_head', 'hide_admin_bar_elements_css');
function hide_admin_bar_elements_css()
{
  if (!is_profile_user()) {
    return;
  }

  echo '<style>
        /* Masquer les éléments restants de l\'admin bar */
        #wp-admin-bar-new-content,
        #wp-admin-bar-edit,
        #wp-admin-bar-comments,
        #wp-admin-bar-new-post,
        #wp-admin-bar-new-page,
        #wp-admin-bar-new-media,
        #wp-admin-bar-new-user,
        #wp-admin-bar-edit-comments,
        #wp-admin-bar-wp-logo,
        #wp-admin-bar-updates,
        #wp-admin-bar-customize,
        #wp-admin-bar-themes,
        #wp-admin-bar-widgets,
        #authordiv,
        #wp-admin-bar-menus {
            display: none !important;
        }
    </style>';
}

/* ========================================
   REDIRECTIONS
======================================== */

/**
 * Redirige vers la liste des profiles après connexion
 */
add_filter('login_redirect', 'redirect_profile_after_login', 10, 3);
function redirect_profile_after_login($redirect_to, $request, $user)
{
  // Vérifications de sécurité pour l'objet utilisateur
  if (!$user || !isset($user->roles) || !is_array($user->roles)) {
    return $redirect_to;
  }

  if (safely_current_user_has_role(PROFILE_ROLE)) {
    return admin_url('edit.php?post_type=' . PROFILE_POST_TYPE);
  }
  return $redirect_to;
}

/* ========================================
   NETTOYAGE À LA DÉSACTIVATION
======================================== */

/**
 * Nettoie les capacités ajoutées lors de la désactivation
 */
register_deactivation_hook(__FILE__, 'cleanup_profile_capabilities');
function cleanup_profile_capabilities()
{
  $profile_role = get_role(PROFILE_ROLE);
  if ($profile_role) {
    // Retirer les capacités ajoutées
    $profile_role->remove_cap('access_admin');
    $profile_role->remove_cap('edit_profiles');
    $profile_role->remove_cap('read_profiles');
    $profile_role->remove_cap('edit_profile');
    $profile_role->remove_cap('read_profile');
    $profile_role->remove_cap('publish_profiles');
    $profile_role->remove_cap('edit_published_profiles');
    $profile_role->remove_cap('edit_posts');
    $profile_role->remove_cap('edit_published_posts');
    $profile_role->remove_cap('publish_posts');
  }
}