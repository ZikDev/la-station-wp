<?php

namespace App\Services;

use App\Services\ProfileService;

/**
 * ========================================
 * SERVICE DE GESTION DES DEMANDES D'ADHÉSION
 * ========================================
 * Service responsable du traitement des demandes d'adhésion
 * Gère la génération de tokens et l'envoi d'emails
 */
class ProfileRequestService extends ProfileService
{

  // ========================================
  // CONSTRUCTEUR ET INITIALISATION
  // ========================================

  /**
   * Initialise le service de demandes d'adhésion
   * 
   * @param array $config Configuration optionnelle
   */
  function __construct(array $config = [])
  {
    parent::__construct($config);
  }

  // ========================================
  // MÉTHODE PRINCIPALE DE TRAITEMENT
  // ========================================

  /**
   * Gère toute la logique de demande d'adhésion
   * 
   * Traite une demande complète : validation,
   * génération de token et envoi d'email de demande.
   * 
   * @param array $data Données de la demande
   * @throws \InvalidArgumentException En cas d'erreur de validation
   */
  public function handleInvitation(array $data): void
  {
    $this->logAction("Début traitement demande d'adhésion");

    try {
      // Extraction et nettoyage des données
      $profileData = $this->extractProfileData($data);

      // Validation des données obligatoires
      $this->validateInputs($profileData['name'], $profileData['email'], $profileData['message']);
      $this->validateEmail($profileData['email']);

      // Génération du token d'invitation
      $token = $this->generateToken($profileData['name'], $profileData['email']);
      $invitationLink = $this->generateInvitationLink(
        $profileData['email'],
        $profileData['name'],
        $token
      );

      // Préparation des données pour l'email
      $emailData = array_merge($profileData, [
        'create_account_url' => $invitationLink
      ]);

      // Envoi de l'email de demande
      $this->sendRequestEmail($emailData);

      $this->logAction("Demande d'adhésion traitée avec succès", [
        'email' => $profileData['email'],
      ]);

    } catch (\InvalidArgumentException $e) {
      // Les erreurs de validation doivent remonter telles quelles
      $this->logAction("Erreur validation demande", [
        'error' => $e->getMessage()
      ]);
      throw $e; // ← Relancer tel quel

    } catch (\Exception $e) {
      // Autres erreurs système
      $this->logAction("Erreur traitement demande", [
        'error' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  // ========================================
  // EXTRACTION ET VALIDATION DES DONNÉES
  // ========================================

  /**
   * Extrait et nettoie les données de la demande
   * 
   * @param array $data Données brutes de la demande
   * @return array Données nettoyées et structurées
   */
  private function extractProfileData(array $data): array
  {
    return [
      'name' => trim($data['name'] ?? ''),
      'email' => trim($data['email'] ?? ''),
      'message' => trim($data['message'] ?? ''),
      'phoneNumber' => trim($data['phoneNumber'] ?? ''),
    ];
  }

  // ========================================
  // GESTION DES TOKENS ET LIENS D'INVITATION
  // ========================================

  /**
   * Génère un token sécurisé pour un utilisateur
   * 
   * Le token est basé sur un hash HMAC incluant les données utilisateur,
   * un timestamp et un nonce pour garantir l'unicité et la sécurité.
   * 
   * @param string $name Nom du membre
   * @param string $email Email du membre
   * @return string Token encodé en base64
   */
  public function generateToken(string $name, string $email): string
  {
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));

    // Construction des données pour le hash
    $hashData = $this->buildHashData($name, $email, $timestamp, $nonce);

    // Génération du hash HMAC
    $hash = hash_hmac('sha256', $hashData, $this->secretKey, false);

    // Assemblage et encodage du token
    return base64_encode($timestamp . '.' . $nonce . '.' . $hash);
  }

  /**
   * Crée le lien d'invitation pour la création de compte
   * 
   * @param string $email Email du membre
   * @param string $name Nom du membre
   * @param string $token Token de sécurité
   * @return string URL complète d'invitation
   */
  private function generateInvitationLink(string $email, string $name, string $token): string
  {
    $params = [
      'action' => 'create_profile',
      'email' => $email,
      'name' => $name,
      'token' => $token
    ];

    return admin_url('admin-post.php?' . http_build_query($params));
  }

  // ========================================
  // ENVOI D'EMAILS
  // ========================================

  /**
   * Envoie l'email de demande d'adhésion aux administrateurs
   * 
   * @param array $data Données de la demande pour le template
   * @throws \Exception En cas d'erreur d'envoi
   */
  private function sendRequestEmail(array $data): void
  {
    try {
      // Préparation des données pour le template
      $templateData = $this->prepareEmailTemplateData($data);

      // Configuration de l'email
      $subject = sprintf(
        '[%s] Nouvelle demande d\'adhésion - %s',
        $templateData['site_name'],
        $templateData['name']
      );

      $message = $this->getEmailTemplate('request-membership', $templateData);
      $headers = $this->getEmailHeaders();

      // Envoi de l'email
      $sent = wp_mail($this->contactEmail, $subject, $message, $headers);

      if (!$sent) {
        throw new \Exception("Échec de l'envoi de l'email");
      }

      $this->logAction("Email de demande envoyé", [
        'recipient' => $this->contactEmail,
        'profile_email' => $data['email']
      ]);

    } catch (\Exception $e) {
      $this->handleEmailError($e, $data);
      throw new \Exception("Le mail n'a pas pu être envoyé.");
    }
  }

  /**
   * Prépare les données pour le template d'email
   * 
   * @param array $data Données de la demande
   * @return array Données enrichies pour le template
   */
  private function prepareEmailTemplateData(array $data): array
  {
    return array_merge($data, [
      'site_name' => get_bloginfo('name'),
      'site_url' => get_site_url(),
      'admin_url' => admin_url(),
      'submission_date' => current_time('mysql'),
    ]);
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
   * Gère les erreurs d'envoi d'email
   * 
   * @param \Exception $e Exception d'origine
   * @param array $data Données de la demande pour les logs
   */
  private function handleEmailError(\Exception $e, array $data): void
  {
    $errorContext = [
      'error' => $e->getMessage(),
      'profile_email' => $data['email'] ?? 'unknown',
      'contact_email' => $this->contactEmail
    ];

    $this->logAction("Erreur envoi email de demande", $errorContext);
    $this->notifyAdmins(
      'Erreur envoi email de demande d\'adhésion',
      $data['email'] ?? 'email_unknown'
    );
  }
}