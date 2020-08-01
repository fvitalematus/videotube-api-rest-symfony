<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;

class UserController extends AbstractController {

    private function resjson($data) {
        // Serializar datos con servicio de serializer
        $json = $this->get('serializer')->serialize($data, 'json');

        // Response con httpfoundation
        $response = new Response();

        // Asignar contenido a la respuesta
        $response->setContent($json);

        // Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        // Devolver la respuesta
        return $response;
    }

    public function index() {
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);

        $users = $user_repo->findAll();
        $user = $user_repo->find(1);

        $videos = $video_repo->findAll();

        $data = [
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php'
        ];

        /*
          foreach($users as $user){
          echo "<h1>{$user->getName()} {$user->getSurname()}</h1>";

          foreach($user->getVideos() as $video){
          echo "<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
          }
          }
         */
        //die();
        return $this->resjson($data);
    }

    public function create(Request $request) {
        // Recoger los datos por POST.
        $json = $request->get('json', null);

        // Decodificar el JSON.
        $params = json_decode($json);

        // Respuesta por defecto.
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado.'
        ];

        // Comprobar y validar datos.
        if ($json != null) {

            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;

            $validator = Validation:: createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if (!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)) {
                // Si la validacion es correcta, crear el objeto del usuario.   

                $user = new User();
                $user->SetName($name);
                $user->SetSurname($surname);
                $user->SetEmail($email);
                $user->SetRole('ROLE_USER');
                $user->setCreatedAt(new \Datetime('now'));

                // Cifrar la contraseña.
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);

                $data = $user;

                // Comprobar si el usuario existe (duplicados).
                $doctrine = $this->getDoctrine();
                $em = $this->getDoctrine()->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

                // Si no existe, guardarlo en la BD.
                if (count($isset_user) == 0) {
                    // Guardo el usuario
                    $em->persist($user);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'El usuario se ha creado correctamente.',
                        'user' => $user
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'El usuario ya existe.'
                    ];
                }
            }
        }

        // Hacer respuesta en JSON.
        return new resjson($data);
    }

    public function login(Request $request, JwtAuth $jwt_auth) {
        // Recibir los datos por post
        $json = $request->get('json', null);
        $params = json_decode($json);

        // Array por defecto para devolver
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha podido identificar.'
        ];

        // Comprobar y validar datos
        if ($json != null) {

            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;

            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);

            if (!empty($email) && !empty($password) && count($validate_email) == 0) {
                // Cifrar la contraseña
                $pwd = hash('sha256', $password);

                // Si todo es valido, llamaremos a un servicio para identificar al usuario (JWT,TOKEN,OBJETO)

                if ($gettoken) {
                    $signup = $jwt_auth->signup($email, $pwd, $gettoken);
                } else {
                    $signup = $jwt_auth->signup($email, $pwd);
                }

                return new JsonResponse($signup);
            }
        }

        // Si no devuelvo bien los datos, respuesta http
        return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwt_auth) {

        // Recoger la cabecera de autenticación
        $token = $request->headers->get('Authorization');

        // Crear un metodo para comprobar si el token es correcto
        $authCheck = $jwt_auth->checkToken($token);

        // Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuario NO ACTUALIZADO'
        ];

        // Si es correcto, hacer la actualización del usuario 
        if ($authCheck) {
            // Actualizar al usuario
            // Conseguir Entity Manager
            $em = $this->getDoctrine()->getManager();

            // Conseguir los datos del usuario identificado
            $identity = $jwt_auth->checkToken($token, true);

            // Conseguir el usuario a actualizar completo
            $user_repo = $this->getDoctrine()->getRepository(User::class);
            $user = $user_repo->findOneBy([
                'id' => $identity->sub
            ]);

            // Recoger datos por post
            $json = $request->get('json', null);
            $params = json_decode($json);

            // Comprobar y validar los datos
            if (!empty($json)) {

                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;

                $validator = Validation:: createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);

                if (!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)) {

                    // Asignar nuevos datos al objeto del usuario 
                    $user->setEmail($email);
                    $user->setName($name);
                    $user->setSurname($surname);

                    // Comprobar duplicadors
                    $isset_user = $user_repo->findBy([
                        'email' => $email
                    ]);

                    if (count($isset_user) == 0 || $identity->email == $email) {
                        // Guardar cambios en la base de datos
                        $em->persist($user);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Usuario ACTUALIZADO',
                            'user' => $user
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Ese email ya existe, prueba otro'
                        ];
                    }
                }
            }
        }

        return $this->resjson($data);
    }

}
