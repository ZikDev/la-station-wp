<?php

use App\Http\Controllers\ProfileController;

add_action('rest_api_init', function () {
  register_rest_route('mcf/v1', '/profile', [
    'methods' => 'POST',
    'callback' => function (\WP_REST_Request $request) {
      $controller = new ProfileController();
      return $controller->sendNewProfileRequestLinkToAdmin($request);
    },
    'permission_callback' => '__return_true',
    'args' => [
      'firstName' => [
        'required' => true,
        'validate_callback' => function ($param) {
          return !empty($param);
        }
      ],
      'lastName' => [
        'required' => true,
        'validate_callback' => function ($param) {
          return !empty($param);
        }
      ],
      'email' => [
        'required' => true,
        'validate_callback' => function ($param) {
          return is_email($param);
        }
      ],
      'message' => [
        'required' => true,
        'validate_callback' => function ($param) {
          return !empty($param);
        }
      ]
    ]
  ]);
});