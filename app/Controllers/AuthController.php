<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AlertGenerator;
use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
        private AlertGenerator $alertGenerator,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        // TODO: you also have a logger service that you can inject and use anywhere; file is var/app.log
        $this->logger->info('Register page requested');

        return $this->render($response, 'auth/register.twig');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        
        // Validate input data
        try {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $this->authService->register($username, $password);
            $this->logger->info('User registered successfully', ['email' => $data['email'] ?? null]);
            $_SESSION['alert'] = $this->alertGenerator->createAlert(
        'success',
        'Registration successful! You can now log in.'
    );
    unset($_SESSION['alert']);
            return $response->withHeader('Location', '/login')->withStatus(302);
        } catch (\Exception $e) {
             
             if ($e->getMessage() === 'Username already exists.') {
            $_SESSION['alert'] = $this->alertGenerator->createAlert(
                'danger',
                'This username exists.'
            );
            $this->logger->error('User registration failed', ['error' => $e->getMessage()]);
        } else {
            $_SESSION['alert'] = $this->alertGenerator->createAlert(
                'danger',
                'Registration failed: ' . $e->getMessage()
            );
            $this->logger->error('User registration failed', ['error' => $e->getMessage()]);
        }
            
            // Optionally, you could re-render the registration page with an error message
            $response = $this->render($response, 'auth/register.twig', [
                'error' => 'Registration failed: ' . $e->getMessage(),
                'data' => $data,
            ]);
            
            return $response;
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        $response = $this->render($response, 'auth/login.twig');
      
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user login, handle login failures
         $data = (array)$request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        if ($this->authService->attempt($username, $password)) {
            $this->logger->info('User logged in successfully', ['username' => $username]);
            $_SESSION['alert'] = $this->alertGenerator->createAlert(
                'success',
                'Login successful!'
            );
          
        } else {
            $this->logger->warning('Login attempt failed', ['username' => $username]);
            $_SESSION['alert'] = $this->alertGenerator->createAlert(
                'danger',
                'Invalid username or password.'
            );
          
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        // Clear authentication/session data and set alert in service
        $this->authService->logout();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
