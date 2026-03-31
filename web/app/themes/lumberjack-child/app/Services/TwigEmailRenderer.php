<?php

namespace App\Services;

use Timber\Timber;

/**
 * ========================================
 * MOTEUR DE RENDU TWIG POUR LES EMAILS
 * ========================================
 * Service spécialisé dans le rendu des templates d'emails
 * Utilise Timber/Twig avec des données globales enrichies
 */
class TwigEmailRenderer
{
  // ========================================
  // PROPRIÉTÉS
  // ========================================

  /** @var string Chemin vers les templates d'emails */
  private $templatePath;

  /** @var array Cache des données globales */
  private static $globalDataCache = null;

  // ========================================
  // CONSTRUCTEUR
  // ========================================

  /**
   * Initialise le moteur de rendu avec un chemin de templates
   * 
   * @param string|null $templatePath Chemin relatif vers les templates (défaut: 'emails/')
   */
  public function __construct(string $templatePath = null)
  {
    $this->templatePath = $templatePath ?? 'emails/';

    // S'assurer que le chemin se termine par un slash
    $this->templatePath = rtrim($this->templatePath, '/') . '/';
  }

  // ========================================
  // RENDU DES TEMPLATES
  // ========================================

  /**
   * Rend un template d'email avec les données fournies
   * 
   * @param string $template Nom du template (sans extension .twig)
   * @param array $data Données à injecter dans le template
   * @return string Contenu HTML de l'email rendu
   * @throws \Exception En cas d'erreur de rendu
   */
  public function render(string $template, array $data = []): string
  {
    try {
      // Préparer les données complètes pour le template
      $templateData = $this->prepareTemplateData($data);

      // Construire le chemin complet du template
      $templatePath = $this->templatePath . $template . '.twig';

      // Vérifier l'existence du template
      if (!$this->templateExists($template)) {
        throw new \Exception("Template email non trouvé: {$templatePath}");
      }

      // Rendu avec Timber
      $rendered = Timber::compile($templatePath, $templateData);

      // Validation du contenu rendu
      if (empty($rendered)) {
        throw new \Exception("Le template {$template} a produit un contenu vide");
      }

      return $rendered;

    } catch (\Exception $e) {
      $this->logRenderError($template, $e, $data);
      throw new \Exception("Erreur de rendu du template twig: " . $e->getMessage());
    }
  }

  /**
   * Prépare les données complètes pour le template
   * 
   * @param array $data Données spécifiques au template
   * @return array Données enrichies avec les données globales
   */
  private function prepareTemplateData(array $data): array
  {
    // Fusionner avec les données globales
    $globalData = $this->getGlobalData();
    $templateData = array_merge($globalData, $data);

    return $templateData;
  }

  // ========================================
  // GESTION DES DONNÉES GLOBALES
  // ========================================

  /**
   * Récupère les données globales disponibles dans tous les templates
   * 
   * @return array Données globales mises en cache
   */
  private function getGlobalData(): array
  {
    // Utiliser le cache pour éviter les appels répétés
    if (self::$globalDataCache === null) {
      self::$globalDataCache = $this->buildGlobalData();
    }

    return self::$globalDataCache;
  }

  /**
   * Construit les données globales pour les templates
   * 
   * @return array Données globales complètes
   */
  private function buildGlobalData(): array
  {
    return [
      // Informations du site
      'site_name' => get_bloginfo('name'),
      'site_url' => get_site_url(),
      'site_description' => get_bloginfo('description'),
      'admin_email' => get_bloginfo('admin_email'),

      // URLs importantes
      'home_url' => home_url(),
      'theme_url' => get_template_directory_uri(),
      'assets_url' => get_template_directory_uri() . '/dist',

      // Informations temporelles
      'current_year' => date('Y'),
      'current_date' => current_time('mysql'),
      'timezone' => wp_timezone_string(),

      // Configuration technique
      'wp_debug' => WP_DEBUG,
      'environment' => wp_get_environment_type(),
    ];
  }

  // ========================================
  // VALIDATION ET UTILITAIRES
  // ========================================

  /**
   * Vérifie si un template existe
   * 
   * @param string $template Nom du template (sans extension)
   * @return bool True si le template existe
   */
  public function templateExists(string $template): bool
  {
    $templatePath = get_template_directory() . '/views/' .
      $this->templatePath . $template . '.twig';

    return file_exists($templatePath);
  }

  // ========================================
  // GESTION DU CACHE
  // ========================================

  /**
   * Vide le cache des données globales
   */
  public function clearCache(): void
  {
    self::$globalDataCache = null;

    // Vider le cache WordPress si disponible
    if (function_exists('wp_cache_flush')) {
      wp_cache_flush();
    }
  }

  // ========================================
  // LOGGING ET DEBUG
  // ========================================

  /**
   * Log une erreur de rendu
   * 
   * @param string $template Nom du template
   * @param \Exception $e Exception survenue
   * @param array $data Données qui ont causé l'erreur
   */
  private function logRenderError(string $template, \Exception $e, array $data): void
  {
    $context = [
      'template' => $template,
      'error' => $e->getMessage(),
      'data_keys' => array_keys($data)
    ];

    error_log("TwigEmailRenderer: Erreur rendu - " . json_encode($context));
  }
}