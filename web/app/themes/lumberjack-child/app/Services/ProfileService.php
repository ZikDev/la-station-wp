<?php

namespace App\Services;

/**
 * ========================================
 * SERVICE DE BASE POUR LA GESTION DES PROFILES
 * ========================================
 * Classe abstraite contenant les fonctionnalités communes
 * pour la gestion des demandes d'adhésion et des comptes profiles
 */
class ProfileService
{
  // ========================================
  // CONSTANTES DE CONFIGURATION
  // ========================================

  private const TOKEN_EXPIRY_SECONDS = 2592000; // 30 jours

  // ========================================
  // PROPRIÉTÉS PROTÉGÉES
  // ========================================

  /** @var string Clé secrète pour la génération des tokens */
  protected $secretKey;

  /** @var int Durée de validité des tokens en secondes */
  protected $tokenExpiry;

  /** @var TwigEmailRenderer Instance du moteur de templates email */
  protected $twigRenderer;

  /** @var string Email de l'administrateur principal */
  protected $adminEmail;

  /** @var string Email de contact pour les envois */
  protected $contactEmail;

  /** @var string Email de contact pour les envois */
  protected $noReplyEmail;

  // ========================================
  // CONSTRUCTEUR ET INITIALISATION
  // ========================================

  /**
   * Initialise le service avec la configuration requise
   * 
   * @param array $config Configuration optionnelle
   * @throws \Exception Si la configuration est invalide
   */
  function __construct(array $config = [])
  {
    $this->initializeConfiguration($config);
    $this->validateConfiguration();
  }

  /**
   * Configure les paramètres du service
   * 
   * @param array $config Configuration personnalisée
   */
  private function initializeConfiguration(array $config): void
  {
    $this->secretKey = $config['secret_key'] ?? getenv("TOKEN_SECRET_KEY");
    $this->tokenExpiry = $config['token_expiry'] ?? self::TOKEN_EXPIRY_SECONDS;
    $this->adminEmail = get_option('admin_email');
    $this->contactEmail = getenv('CONTACT_EMAIL') ?: $this->adminEmail;
    $this->noReplyEmail = getenv('SMTP_FROM') ?: $this->adminEmail;
  }


  /**
   * Valide que la configuration est complète
   * 
   * @throws \Exception Si des paramètres critiques sont manquants
   */
  private function validateConfiguration(): void
  {
    if (empty($this->secretKey)) {
      throw new \Exception(
        'Clé secrète manquante pour les services Profile Request et Account. ' .
        'Vérifiez la variable TOKEN_SECRET_KEY.'
      );
    }

    if (empty($this->contactEmail)) {
      error_log('ProfileService: Email de contact non configuré, utilisation de l\'email admin');
    }
  }

  // ========================================
  // VALIDATION DES DONNÉES D'ENTRÉE
  // ========================================

  /**
   * Valide les données de base d'un membre
   * 
   * @param string $name Nom du membre
   * @param string $email Email du membre
   * @param string|null $message Message optionnel
   * @throws \InvalidArgumentException Si les données sont invalides
   */
  protected function validateInputs(string $name, string $email, ?string $message = NULL): void
  {
    // Validation du nom
    if (empty(trim($name))) {
      throw new \InvalidArgumentException(
        'Le nom est requis.'
      );
    }

    // Validation de l'email
    if (empty(trim($email))) {
      throw new \InvalidArgumentException(
        "L'e-mail est requis."
      );
    }

    // Validation du message si fourni
    if ($message !== null && empty(trim($message))) {
      throw new \InvalidArgumentException(
        "Un message est requis."
      );
    }
  }

  /**
   * Valide l'adresse email et vérifie qu'elle n'existe pas déjà
   * 
   * @param string $email Adresse email à valider
   * @throws \InvalidArgumentException Si l'email est invalide
   * @throws \Exception Si l'email existe déjà
   */
  protected function validateEmail(string $email): void
  {
    // Validation du format
    if (!is_email($email)) {
      throw new \InvalidArgumentException(
        "L'e-mail est invalide."
      );
    }

    // Vérification de l'unicité
    if (email_exists($email)) {
      throw new \InvalidArgumentException(
        "Un utilisateur avec cet email existe déjà."
      );
    }
  }

  // ========================================
  // GESTION DES TEMPLATES EMAIL
  // ========================================

  /**
   * Génère le contenu d'un email à partir d'un template Twig
   * 
   * @param string $type Type de template (welcome, request-membership, etc.)
   * @param array $data Données à injecter dans le template
   * @return string Contenu HTML de l'email
   * @throws \Exception En cas d'erreur de template
   */
  protected function getEmailTemplate(string $type, array $data): string
  {
    try {
      // Initialiser le moteur de templates si nécessaire
      if (!isset($this->twigRenderer)) {
        $this->twigRenderer = new TwigEmailRenderer('emails/');
      }

      return $this->twigRenderer->render($type, $data);

    } catch (\Exception $e) {
      error_log("ProfileService: Erreur template Twig ({$type}): " . $e->getMessage());
      throw new \Exception("Le mail n'a pas pu être envoyé.");
    }
  }

  // ========================================
  // SYSTÈME DE NOTIFICATIONS D'ERREUR
  // ========================================

  /**
   * Notifie les administrateurs en cas d'erreur critique
   * 
   * @param string $subject Sujet de la notification
   * @param string $details Détails de l'erreur
   */
  protected function notifyAdmins(string $subject, string $details): void
  {
    $message = $this->formatErrorNotification($details);

    // Envoi de la notification d'erreur
    $sent = wp_mail(
      $this->adminEmail,
      '[ERREUR LA STATION] ' . $subject,
      $message
    );

    if (!$sent) {
      error_log("ProfileService: Impossible d'envoyer la notification d'erreur");
    }
  }

  /**
   * Formate le message de notification d'erreur
   * 
   * @param string $details Détails de l'erreur
   * @return string Message formaté
   */
  private function formatErrorNotification(string $details): string
  {
    $message = "Une erreur s'est produite dans le système de gestion des membres:\n\n";
    $message .= "Détails: " . $details . "\n";
    $message .= "Date: " . current_time('mysql') . "\n";
    $message .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
    $message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
    $message .= "URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n\n";
    $message .= "Veuillez vérifier les logs pour plus d'informations.";

    return $message;
  }

  // ========================================
  // MÉTHODES UTILITAIRES
  // ========================================

  /**
   * Crée un hash à partir des données utilisateur
   * 
   * @param string $name
   * @param string $email
   * @param string $timestamp
   * @param string $nonce
   * @return string
   */
  protected function buildHashData(string $name, string $email, string $timestamp, string $nonce): string
  {
    $emailHash = hash('sha256', $email);
    $nameHash = hash('sha256', $name);

    return $emailHash . $nameHash . $timestamp . $nonce;
  }

  /**
   * Log une action avec contexte
   * 
   * @param string $action Action effectuée
   * @param array $context Contexte de l'action
   */
  protected function logAction(string $action, array $context = []): void
  {
    $message = "ProfileService: {$action}";

    if (!empty($context)) {
      $message .= " - Context: " . json_encode($context);
    }

    error_log($message);
  }
}