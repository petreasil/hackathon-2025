<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class BaseController
{
    public function __construct(
        protected Twig $view,
    ) {}

    protected function render(Response $response, string $template, array $data = []): Response
    {
        // Flash alert logic: pass alert if set, then clear it
        if (isset($_SESSION['alert'])) {
            $data['alert'] = $_SESSION['alert'];
            unset($_SESSION['alert']);
        }
        return $this->view->render($response, $template, $data);
    }

    // TODO: add here any common controller logic and use in concrete controllers
}
