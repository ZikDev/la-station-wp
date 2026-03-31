<?php

namespace App\Helpers;

class BannerHelper
{
  /**
   * Affiche un banner via les paramètres GET
   */
  public static function handleUrlParameters(): ?array
  {
    // Vérifier les paramètres d'URL courants
    $banners = [];

    // Messages de succès
    if (isset($_GET['profile_invite']) && $_GET['profile_invite'] === 'success') {
      $banners[] = [
        'type' => 'success',
        'title' => 'Compte créé avec succès !',
        'message' => 'Votre compte profile a été créé. Vous allez recevoir un email avec vos informations de connexion.',
        'dismissible' => true
      ];
    }

    // Messages d'erreur
    if (isset($_GET['profile_invite']) && $_GET['profile_invite'] === 'error') {
      $error_message = isset($_GET['message']) ? urldecode($_GET['message']) : 'Une erreur est survenue.';
      $banners[] = [
        'type' => 'error',
        'title' => 'Erreur de création de compte',
        'message' => esc_html($error_message),
        'dismissible' => true
      ];
    }

    // Messages de formulaire
    if (isset($_GET['form_success'])) {
      $banners[] = [
        'type' => 'success',
        'message' => 'Votre demande a été envoyée avec succès !',
        'dismissible' => true
      ];
    }

    if (isset($_GET['form_error'])) {
      $banners[] = [
        'type' => 'error',
        'message' => 'Une erreur est survenue lors de l\'envoi du formulaire.',
        'dismissible' => true
      ];
    }

    return !empty($banners) ? $banners : null;
  }

  /**
   * Créer un banner personnalisé
   */
  public static function create(string $type, string $message, ?string $title = null, bool $dismissible = true): array
  {
    return [
      'type' => $type,
      'message' => $message,
      'title' => $title,
      'dismissible' => $dismissible
    ];
  }

  /**
   * Créer un banner de succès
   */
  public static function success(string $message, ?string $title = null): array
  {
    return self::create('success', $message, $title);
  }

  /**
   * Créer un banner d'erreur
   */
  public static function error(string $message, ?string $title = null): array
  {
    return self::create('error', $message, $title);
  }

  /**
   * Créer un banner d'avertissement
   */
  public static function warning(string $message, ?string $title = null): array
  {
    return self::create('warning', $message, $title);
  }

  /**
   * Créer un banner d'information
   */
  public static function info(string $message, ?string $title = null): array
  {
    return self::create('info', $message, $title);
  }
}