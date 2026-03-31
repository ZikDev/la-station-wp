<?php

namespace App\Http\Controllers;

use App\Services\ProfileAccountService;
use App\Services\ProfileRequestService;
use Rareloop\Lumberjack\Http\Controller as BaseController;

/**
 * ========================================
 * CONTRÔLEUR DE GESTION DES PROFILES
 * ========================================
 * Contrôleur principal pour les opérations liées aux profiles
 * Gère les demandes d'adhésion et la création de comptes via API REST et admin-post
 */
class ProfileController extends BaseController
{
  // ========================================
  // API REST - DEMANDES D'ADHÉSION
  // ========================================

  /**
   * Traite une nouvelle demande d'adhésion via API REST
   * 
   * Point d'entrée principal pour les demandes d'adhésion depuis le formulaire web.
   * Gère la validation, le traitement des fichiers et l'envoi d'emails.
   * 
   * @param \WP_REST_Request $request Requête REST avec les données du formulaire
   * @return \WP_REST_Response Réponse JSON avec le statut de l'opération
   */
  public function sendNewProfileRequestLinkToAdmin(\WP_REST_Request $request)
  {
    try {
      $this->logAction("Début traitement requête");

      // Extraction et validation des données
      $profileData = $this->extractAndValidateProfileData($request);

      // Traitement de la demande
      $service = new ProfileRequestService();
      $service->handleInvitation($profileData);

      return $this->createSuccessResponse(
        "Requête envoyée avec succès !"
      );

    } catch (\InvalidArgumentException $e) {
      return $this->handleValidationError($e);

    } catch (\Exception $e) {
      return $this->handleSystemError($e, $profileData['email'] ?? 'unknown');
    }
  }

  /**
   * Extrait et valide les données de la demande depuis la requête
   * 
   * @param \WP_REST_Request $request Requête REST
   * @return array Données nettoyées et structurées
   * @throws \InvalidArgumentException Si les données sont invalides
   */
  private function extractAndValidateProfileData(\WP_REST_Request $request): array
  {
    // Extraction des données avec nettoyage
    $firstName = $this->sanitizeTextInput($request->get_param('firstName'));
    $lastName = $this->sanitizeTextInput($request->get_param('lastName'));
    $email = sanitize_email($request->get_param('email') ?? '');
    $phoneNumber = $this->sanitizeTextInput($request->get_param('phoneNumber'));
    $message = $this->sanitizeMessageInput($request->get_param('message'));

    // Validation des champs obligatoires
    $this->validateRequiredFields($firstName, $lastName, $email, $message);

    // Validations spécialisées
    $this->validateEmail($email);

    return [
      'name' => trim($firstName . ' ' . $lastName),
      'firstName' => $firstName,
      'lastName' => $lastName,
      'email' => $email,
      'phoneNumber' => $phoneNumber,
      'message' => $message,
      'metadata' => $this->collectRequestMetadata()
    ];
  }

  /**
   * Valide les champs obligatoires
   * 
   * @param string $firstName Prénom
   * @param string $lastName Nom
   * @param string $email Email
   * @param string $message Message
   * @throws \InvalidArgumentException Si un champ obligatoire est manquant
   */
  private function validateRequiredFields(string $firstName, string $lastName, string $email, string $message): void
  {
    if (empty($firstName) || empty($lastName)) {
      throw new \InvalidArgumentException("Le nom est requis.");
    }

    if (empty($email)) {
      throw new \InvalidArgumentException("L'e-mail est requis.");
    }

    if (empty($message)) {
      throw new \InvalidArgumentException("Un message est requis.");
    }
  }

  /**
   * Valide une adresse email
   * 
   * @param string $email Email à valider
   * @throws \InvalidArgumentException Si l'email est invalide
   */
  private function validateEmail(string $email): void
  {
    if (!is_email($email)) {
      throw new \InvalidArgumentException("L'e-mail est invalide.");
    }
  }

  // ========================================
  // GESTION DES RÉPONSES ET ERREURS
  // ========================================

  /**
   * Crée une réponse de succès standardisée
   * 
   * @param string $message Message de succès
   * @return \WP_REST_Response Réponse formatée
   */
  private function createSuccessResponse(string $message): \WP_REST_Response
  {
    return new \WP_REST_Response([
      'success' => true,
      'message' => $message,
      'timestamp' => current_time('mysql')
    ], 200);
  }

  /**
   * Gère les erreurs de validation des données
   * 
   * @param \InvalidArgumentException $e Exception de validation
   * @param string $requestId Identifiant de la requête
   * @return \WP_REST_Response Réponse d'erreur formatée
   */
  private function handleValidationError(\InvalidArgumentException $e): \WP_REST_Response
  {
    $this->logAction("Erreur de validation", [
      'error' => $e->getMessage()
    ]);

    return new \WP_REST_Response([
      'success' => false,
      'message' => $e->getMessage(),
      'error_type' => 'validation_error',
      'timestamp' => current_time('mysql')
    ], 400);
  }

  /**
   * Gère les erreurs système générales
   * 
   * @param \Exception $e Exception système
   * @param string $requestId Identifiant de la requête
   * @param string $context Contexte de l'erreur
   * @return \WP_REST_Response Réponse d'erreur formatée
   */
  private function handleSystemError(\Exception $e, string $context = ''): \WP_REST_Response
  {
    // Log détaillé de l'erreur
    $this->logAction("Erreur système", [
      'error' => $e->getMessage(),
      'context' => $context,
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ]);

    // Notification des administrateurs
    $this->notifyAdminsOfError($e, $context);

    return new \WP_REST_Response([
      'success' => false,
      'message' => $e->getMessage(),
      'timestamp' => current_time('mysql')
    ], 500);
  }

  // ========================================
  // ADMIN-POST - CRÉATION DE COMPTES
  // ========================================

  /**
   * Crée un compte profile à partir d'un token d'invitation valide
   * 
   * Point d'entrée pour les liens d'invitation envoyés par email.
   * Valide le token et crée le compte utilisateur et CPT profile.
   * 
   * @return void Effectue une redirection vers une page de confirmation
   */
  public function createProfile(): void
  {

    try {
      $this->logAction("Début création compte");

      // Extraction et validation des paramètres
      $requestData = $this->extractTokenRequestData();
      $this->validateTokenRequestData($requestData);

      // Création du compte
      $service = new ProfileAccountService();
      $service->createProfileFromToken(
        $requestData['name'],
        $requestData['email'],
        $requestData['token']
      );

      // Redirection de succès
      $this->logAction("Compte créé avec succès", [
        'email' => $requestData['email']
      ]);

      $this->redirectToSuccessPage();


      wp_redirect(home_url('/?profile_invite=success'));
    } catch (\Exception $e) {
      $this->handleAccountCreationError($e, $requestData ?? []);

      error_log('Erreur lors de la création du membre : ' . $e->getMessage());

      $this->redirectToErrorPage($e->getMessage());
    }
  }

  /**
   * Extrait les données de la requête de création de compte
   * 
   * @return array Données de la requête nettoyées
   */
  private function extractTokenRequestData(): array
  {
    return [
      'email' => sanitize_email($_GET['email'] ?? ''),
      'name' => sanitize_text_field($_GET['name'] ?? ''),
      'token' => sanitize_text_field($_GET['token'] ?? ''),
    ];
  }

  /**
   * Valide les données de la requête de création de compte
   * 
   * @param array $data Données à valider
   * @throws \InvalidArgumentException Si les données sont invalides
   */
  private function validateTokenRequestData(array $data): void
  {
    if (empty($data['email'])) {
      throw new \InvalidArgumentException('Email manquant dans la requête');
    }

    if (empty($data['name'])) {
      throw new \InvalidArgumentException('Nom manquant dans la requête');
    }

    if (empty($data['token'])) {
      throw new \InvalidArgumentException('Token manquant dans la requête');
    }

    if (!is_email($data['email'])) {
      throw new \InvalidArgumentException('Email invalide dans la requête');
    }
  }

  /**
   * Gère les erreurs lors de la création de compte
   * 
   * @param \Exception $e Exception survenue
   * @param string $requestId Identifiant de la requête
   * @param array $requestData Données de la requête
   */
  private function handleAccountCreationError(\Exception $e, array $requestData): void
  {
    // Log de l'erreur
    $this->logAction("Erreur création compte", [
      'error' => $e->getMessage(),
      'email' => $requestData['email'] ?? 'unknown'
    ]);

    // Notification des administrateurs
    $this->notifyAdminsOfError($e, $requestData['email'] ?? 'unknown');

    // Redirection vers la page d'erreur
    $this->redirectToErrorPage($e->getMessage());
  }

  // ========================================
  // REDIRECTIONS
  // ========================================

  /**
   * Redirige vers la page de succès après création de compte
   */
  private function redirectToSuccessPage(): void
  {
    $params = [
      'profile_invite' => 'success',
      'timestamp' => time()
    ];

    $redirectUrl = home_url('/?' . http_build_query($params));
    wp_redirect($redirectUrl);
    exit;
  }

  /**
   * Redirige vers la page d'erreur
   * 
   * @param string $errorMessage Message d'erreur
   * @param string $requestId Identifiant de la requête
   */
  private function redirectToErrorPage(string $errorMessage): void
  {
    $encodedMessage = urlencode($errorMessage);
    $redirectUrl = home_url("/?profile_invite=error&message={$encodedMessage}");
    wp_redirect($redirectUrl);
    exit;
  }

  // ========================================
  // UTILITAIRES
  // ========================================

  /**
   * Nettoie une entrée de texte
   * 
   * @param mixed $input Données d'entrée
   * @return string Texte nettoyé
   */
  private function sanitizeTextInput($input): string
  {
    return sanitize_text_field($input ?? '');
  }

  /**
   * Nettoie une entrée de message
   * 
   * @param mixed $input Données d'entrée
   * @return string Message nettoyé
   */
  private function sanitizeMessageInput($input): string
  {
    return sanitize_textarea_field($input ?? '');
  }

  /**
   * Collecte les métadonnées de la requête
   * 
   * @return array Métadonnées de la requête
   */
  private function collectRequestMetadata(): array
  {
    return [
      'ip_address' => $this->getClientIpAddress(),
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
      'referer' => $_SERVER['HTTP_REFERER'] ?? '',
      'timestamp' => current_time('mysql'),
      'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    ];
  }

  /**
   * Récupère l'adresse IP réelle du client
   * 
   * @return string Adresse IP du client
   */
  private function getClientIpAddress(): string
  {
    $ipHeaders = [
      'HTTP_CF_CONNECTING_IP',     // Cloudflare
      'HTTP_X_FORWARDED_FOR',      // Proxy standard
      'HTTP_X_REAL_IP',           // Nginx
      'HTTP_CLIENT_IP',           // Proxy
      'REMOTE_ADDR'               // IP directe
    ];

    foreach ($ipHeaders as $header) {
      if (!empty($_SERVER[$header])) {
        $ip = $_SERVER[$header];

        // Pour X-Forwarded-For, prendre la première IP
        if (strpos($ip, ',') !== false) {
          $ip = trim(explode(',', $ip)[0]);
        }

        // Valider l'IP publique
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
          return $ip;
        }
      }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  }

  /**
   * Notifie les administrateurs d'une erreur critique
   * 
   * @param \Exception $e Exception survenue
   * @param string $context Contexte de l'erreur
   */
  private function notifyAdminsOfError(\Exception $e, string $context): void
  {
    $subject = '[ERREUR MCF] Problème dans le système de membres';

    $message = "Une erreur s'est produite dans le système de gestion des membres :\n\n";
    $message .= "Message : " . $e->getMessage() . "\n";
    $message .= "Contexte : {$context}\n";
    $message .= "Fichier : " . $e->getFile() . " (ligne " . $e->getLine() . ")\n";
    $message .= "Date : " . current_time('mysql') . "\n";
    $message .= "IP : " . $this->getClientIpAddress() . "\n";
    $message .= "User Agent : " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n\n";

    if (WP_DEBUG) {
      $message .= "Stack trace :\n" . $e->getTraceAsString();
    }

    wp_mail(get_option('admin_email'), $subject, $message);
  }

  /**
   * Log une action du contrôleur avec contexte
   * 
   * @param string $action Action effectuée
   * @param array $context Contexte de l'action
   */
  private function logAction(string $action, array $context = []): void
  {
    $logMessage = "ProfileController: {$action}";

    if (!empty($context)) {
      $logMessage .= " - " . json_encode($context);
    }

    error_log($logMessage);
  }


}


