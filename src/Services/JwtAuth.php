<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth {

    public $manager;
    public $key;

    public function __construct($manager) {
        $this->manager = $manager;
        $this->key = 'master-fullstack_123456789';
    }

    public function signup($email, $password, $gettoken = null) {
        // Comprobar si el usuario existe
        $user = $this->manager->getRepository(User::class)->findOneBy([
            'email' => $email,
            'password' => $password
        ]);

        $signup = false;
        if (is_object($user)) {
            $signup = true;
        }

        // Si existe, generar el token de jwt
        if ($signup) {

            $token = [
                'sub' => $user->getId(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'email' => $user->getEmail(),
                'iat' => time(),
                'exp' => time() + (7 * 24 * 60 * 60)
            ];

            // Comprobar el flag gettoken, condicion
            $jwt = JWT::encode($token, $this->key, 'HS256');

            if (!empty($gettoken)) {
                $data = $jwt;
            } else {
                $decoded = JWT::decode($jwt, $this->key, ['HS256']);
                $data = $decoded;
            }
        } else {
            $data = [
                'status' => 'error',
                'message' => 'Login Incorrecto'
            ];
        }

        // devolver datos
        return $data;
    }

}
