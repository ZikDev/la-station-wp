<?php

namespace App\Services;

use App\Services\ProfileService;

/**
 * ========================================
 * SERVICE DE CRÉATION DE COMPTES PROFILES
 * ========================================
 * Service responsable de la création des comptes utilisateurs WordPress
 * et des CPT profiles associés à partir d'un token d'invitation valide
 */
class ProfileAccountService extends ProfileService
{
  // ========================================
  // CONSTANTES DE CONFIGURATION
  // ==
  private const PASSWORD_LENGTH = 12;
  private const DEFAULT_PROFILE_STATUS = 'draft';

  // ========================================
  // PROPRIÉTÉS
  // ========================================
  /** @var string|null Mot de passe temporaire généré */
  protected $tempPassword;

  // ========================================
  // CONSTRUCTEUR
  // ========================================

  /**
   * Initialise le service de création de comptes
   * 
   * @param array $config Configuration optionnelle
   */
  public function __construct(array $config = [])
  {
    parent::__construct($config);
    $this->tempPassword = null;
  }

  // ========================================
  // MÉTHODE PRINCIPALE DE CRÉATION
  // ========================================

  /**
   * Valide le token et crée l'utilisateur et le CPT profile
   * 
   * Cette méthode orchestre tout le processus de création d'un compte
   * profile à partir d'un token d'invitation valide.
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @param string $token Token d'invitation
   * @throws \InvalidArgumentException En cas d'erreur de création
   * @throws \Exception En cas d'erreur de création
   */
  public function createProfileFromToken(string $name, string $email, string $token): void
  {

    $this->logAction("Début création compte profile", [
      'email' => $email
    ]);

    try {
      // Validation initiale des données
      $this->validateInputs($name, $email);
      $this->validateEmail($email);

      // Validation du token d'invitation
      if (!$this->isValidToken($name, $email, $token)) {
        throw new \Exception('Token invalide ou expiré.');
      }

      // Création atomique du compte
      $this->createProfileAccount($name, $email);

      $this->logAction("Compte profile créé avec succès", [
        'email' => $email
      ]);
    } catch (\Exception $e) {
      $this->logAction("Erreur création compte", [
        'error' => $e->getMessage(),
        'email' => $email
      ]);
      throw new \Exception('Erreur lors de la création du compte du profile');
    }
  }

  // ========================================
  // VALIDATION DES TOKENS
  // ========================================

  /**
   * Valide l'authenticité et la validité temporelle d'un token
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @param string $token Token à valider
   * @return bool True si le token est valide
   */
  private function isValidToken(string $name, string $email, string $token): bool
  {
    try {
      // Décodage du token
      $decoded = base64_decode($token, true);
      if ($decoded === false) {
        $this->logAction("Token invalide - échec décodage base64");
        return false;
      }

      // Extraction des composants
      $parts = explode('.', $decoded);
      if (count($parts) !== 3) {
        $this->logAction("Token invalide - format incorrect");
        return false;
      }

      [$timestamp, $nonce, $hash] = $parts;

      // Vérification de l'expiration
      if (time() - intval($timestamp) > $this->tokenExpiry) {
        $this->logAction("Token expiré", [
          'timestamp' => $timestamp,
          'current_time' => time(),
          'expiry' => $this->tokenExpiry
        ]);
        return false;
      }

      // Vérification de la signature
      $expectedHash = hash_hmac(
        'sha256',
        $this->buildHashData($name, $email, $timestamp, $nonce),
        $this->secretKey,
        false
      );

      $isValid = hash_equals($expectedHash, $hash);

      if (!$isValid) {
        $this->logAction("Token invalide - signature incorrecte");
        return false;
      }

      return $isValid;

    } catch (\Exception $e) {
      $this->logAction("Erreur validation token", ['error' => $e->getMessage()]);
      return false;
    }
  }

  // ========================================
  // CRÉATION DU COMPTE profile
  // ========================================

  /**
   * Crée le compte utilisateur et le CPT profile de manière atomique
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @throws \Exception En cas d'erreur
   */
  private function createProfileAccount(string $name, string $email): void
  {
    $user_id = null;
    $profile_id = null;

    try {
      // Étape 1: Création de l'utilisateur WordPress
      $user_id = $this->createWordPressUser($name, $email);

      // Étape 2: Création du CPT profile
      $profile_id = $this->createProfilePost($name, $user_id);

      // Étape 3: Envoi de l'email de confirmation
      $this->sendWelcomeEmail($name, $email);

    } catch (\Exception $e) {
      // Rollback en cas d'erreur
      $this->rollbackCreation($user_id, $profile_id);
      throw new \Exception('Erreur lors de la création du compte');
    }
  }

  /**
   * Crée l'utilisateur WordPress
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @return int ID de l'utilisateur créé
   * @throws \Exception En cas d'erreur de création
   */
  private function createWordPressUser(string $name, string $email): int
  {
    // Génération d'un mot de passe temporaire
    $password = wp_generate_password(self::PASSWORD_LENGTH, true, true);

    $user_data = [
      'user_login' => sanitize_user($email),
      'user_email' => sanitize_email($email),
      'user_pass' => $password,
      'display_name' => sanitize_text_field($name),
      'role' => 'profile',
      'meta_input' => [
        'account_created_via' => 'token_invitation',
        'account_created_at' => current_time('mysql'),
        'show_admin_bar_front' => false,
        'force_password_change' => true // Forcer le changement à la première connexion
      ]
    ];

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
      $this->logAction("Erreur création utilisateur WordPress", [
        'error' => $user_id->get_error_message(),
        'email' => $email
      ]);
      throw new \Exception(
        'Erreur création utilisateur WordPress :' . $user_id->get_error_message()
      );
    }

    $this->tempPassword = $password;

    $this->logAction("Utilisateur WordPress créé", [
      'user_id' => $user_id,
      'email' => $email
    ]);

    return $user_id;
  }

  /**
   * Crée le CPT profile et ses traductions
   * 
   * @param string $name Nom du profile
   * @param int $user_id ID de l'utilisateur WordPress
   * @return int ID du profile créé
   * @throws \Exception En cas d'erreur de création
   */
  private function createProfilePost(string $name, int $user_id): int
  {
    $profile_data = [
      'post_type' => 'profile',
      'post_title' => sanitize_text_field($name),
      'post_status' => self::DEFAULT_PROFILE_STATUS,
      'post_author' => $user_id,
      'meta_input' => [
        'profile_created_at' => current_time('mysql'),
        'profile_status' => 'pending_validation'
      ]
    ];

    $profile_id = wp_insert_post($profile_data);

    if (is_wp_error($profile_id)) {
      $this->logAction("Erreur création CPT profile", [
        'error' => $profile_id->get_error_message(),
        'user_id' => $user_id
      ]);
      throw new \Exception(
        'Erreur création CPT profile:' . $profile_id->get_error_message()
      );
    }

    $this->logAction("CPT profile créé", [
      'profile_id' => $profile_id,
      'user_id' => $user_id
    ]);

    return $profile_id;
  }

  // ========================================
  // GESTION DES EMAILS
  // ========================================

  /**
   * Envoie l'email de bienvenue avec les informations de connexion
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @throws \Exception En cas d'erreur d'envoi
   */
  private function sendWelcomeEmail(string $name, string $email): void
  {
    try {
      $templateData = $this->prepareWelcomeEmailData($name, $email);

      $subject = sprintf(
        '[%s] Bienvenue %s ! Votre compte a été créé',
        $templateData['site_name'],
        $name
      );

      $message = $this->getEmailTemplate('welcome', $templateData);
      $headers = $this->getEmailHeaders();
      $file_name = getenv("WP_ENV") == "staging" || getenv("WP_ENV") == "development" ? '/assets/Espace_profile-staging.pdf' : '/assets/Espace_profile.pdf';
      $attachment = [get_template_directory() . $file_name];

      $sent = wp_mail($email, $subject, $message, $headers, $attachment);

      if (!$sent) {
        $this->notifyAdmins("Échec de l'envoi de l'email de bienvenue", $email);
        throw new \Exception("Le mail n'a pas pu être envoyé.");
      }

      $this->logAction("Email de bienvenue envoyé", [
        'recipient' => $email,
        'name' => $name
      ]);

    } catch (\Exception $e) {
      $this->handleWelcomeEmailError($e, $name, $email);
      throw new \Exception("Le mail n'a pas pu être envoyé.");
    }
  }

  /**
   * Prépare les données pour l'email de bienvenue
   * 
   * @param string $name Nom du profile
   * @param string $email Email du profile
   * @return array Données pour le template
   */
  private function prepareWelcomeEmailData(string $name, string $email): array
  {
    return [
      'name' => $name,
      'email' => $email,
      'password' => $this->tempPassword,
      'login_url' => wp_login_url(),
      'site_name' => get_bloginfo('name'),
      'site_url' => get_site_url(),
      'noreply_email' => $this->noReplyEmail,
    ];
  }

  /**
   * Génère les headers pour les emails
   * 
   * @return array Headers formatés
   */
  private function getEmailHeaders(): array
  {
    return [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . get_bloginfo('name') . ' <' . $this->noReplyEmail . '>',
    ];
  }

  /**
   * Gère les erreurs d'envoi d'email de bienvenue
   * 
   * @param \Exception $e Exception d'origine
   * @param string $name Nom du profile
   * @param string $email Email du profile
   */
  private function handleWelcomeEmailError(\Exception $e, string $name, string $email): void
  {
    $errorContext = [
      'error' => $e->getMessage(),
      'name' => $name,
      'email' => $email
    ];

    $this->logAction("Erreur envoi email de bienvenue", $errorContext);
    $this->notifyAdmins(
      'Erreur envoi email de bienvenue',
      "profile: {$name} ({$email})"
    );
  }

  // ========================================
  // ROLLBACK ET NETTOYAGE
  // ========================================

  /**
   * Annule la création en cas d'erreur (rollback)
   * 
   * @param int|null $user_id ID de l'utilisateur à supprimer
   * @param int|null $profile_id ID du profile à supprimer
   * @param string $logId Identifiant de log
   */
  private function rollbackCreation(?int $user_id, ?int $profile_id): void
  {
    $this->logAction("Début rollback création", [
      'user_id' => $user_id,
      'profile_id' => $profile_id
    ]);

    // Supprimer le CPT profile et ses traductions
    if ($profile_id) {
      $this->deleteProfile($profile_id);
    }

    // Supprimer l'utilisateur WordPress
    if ($user_id) {
      $deleted = wp_delete_user($user_id);
      if ($deleted) {
        $this->logAction("Utilisateur supprimé lors du rollback", ['user_id' => $user_id]);
      }
    }

    $this->logAction("Rollback terminé");
  }

  /**
   * Supprime un profile
   * 
   * @param int $profile_id ID du profile principal
   */
  private function deleteProfile(int $profile_id): void
  {
    wp_delete_post($profile_id, true);

    $this->logAction("profile supprimé lors du rollback", ['profile_id' => $profile_id]);
  }

}
