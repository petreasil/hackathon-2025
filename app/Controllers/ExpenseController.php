<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AlertGenerator;
use App\Domain\Service\ExpenseService;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;



class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 5;

    public function __construct(
        Twig $view,
        private readonly ExpenseService $expenseService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly AlertGenerator $alertGenerator,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            // Redirect to login or return 401 Unauthorized
            return $response->withStatus(401);
        }
        $user = $this->userRepository->find($userId);
        $options = $this->expenseService->listExpenditureYears($user);
        $year = $request->getQueryParams()['year'] ?? null;
        $month = $request->getQueryParams()['month'] ?? null;
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        $pageSize = (int)($request->getQueryParams()['pageSize'] ?? self::PAGE_SIZE);

        // Fetch the User entity using the UserRepository
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $response->withStatus(401);
        }

        // If year and month are provided, use them as filters; otherwise, use current year/month
        $filterYear = $year ? (int)$year : (int)date('Y');
        $filterMonth = $month ? (int)$month : (int)date('n');

        $result = $this->expenseService->list($user, $filterYear, $filterMonth, $page, $pageSize);
    
        ['total' => $total, 'expenses' => $expenses] = $result;
      
        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page'     => $page,
            'pageSize' => $pageSize,
            'year'     => $filterYear,
            'total'    => $total,
            'month'    => $filterMonth,
            'options'  => $options,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categories = require __DIR__ . '/../../config/categories.php';
        return $this->render($response, 'expenses/create.twig', ['categories' => $categories]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            // Redirect to login or return 401 Unauthorized
            return $response->withStatus(401);
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $response->withStatus(401);
        }

        $data = (array)$request->getParsedBody();
        $categories = require __DIR__ . '/../../config/categories.php';

        // Log the submitted data for debugging
        $this->logger->info('Expense form submitted', ['data' => $data, 'user_id' => $userId]);


        try {
            $this->expenseService->create(
                $user,
                (int)$data['amount'],
                $data['description'],
                new \DateTimeImmutable($data['date']),
                $data['category']
            );
        } catch (\Exception $e) {
            $this->logger->error('error adding expense', ['error' => $e->getMessage()]);
            $errors['general'] = 'Failed to create expense: ' . $e->getMessage();
            return $this->render($response, 'expenses/create.twig', [
                'categories' => $categories,
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        // Redirect to expenses index on success
        return $response
            ->withHeader('Location', '/expenses')
            ->withStatus(302);

    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $categories = require __DIR__ . '/../../config/categories.php';

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withStatus(401);
        }

        $expenseId = $routeParams['id'] ?? null;
        if (!$expenseId) {
            return $response->withStatus(404);
        }

        $expense = $this->expenseService->findById((int)$expenseId);
        $this->logger->info('Editing expense', [
            'expense_id' => $expenseId,
            'user_id' => $userId,
            "expense" => $expense,
        ]);
        if (!$expense) {
            return $response->withStatus(404);
        }

        // if ($expense->getUser()->getId() !== $userId) {
        //     return $response->withStatus(403);
        // }

        return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
        ]);

      
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withStatus(401);
        }

        $expenseId = $routeParams['id'] ?? null;
        if (!$expenseId) {
            return $response->withStatus(404);
        }

        $expense = $this->expenseService->findById((int)$expenseId);
        if (!$expense) {
            return $response->withStatus(404);
        }

        // Optional: Uncomment if you want to enforce ownership
        // if ($expense->getUser()->getId() !== $userId) {
        //     return $response->withStatus(403);
        // }

        $data = (array)$request->getParsedBody();
        $categories = require __DIR__ . '/../../config/categories.php';
        $this->logger->info('Expense update form submitted', [
            'data' => $data,
            'expense_id' => $expenseId,
            'user_id' => $userId,
        ]);
        try {
            $this->expenseService->update(
            $expense,
            (int)$data['amount'],
            $data['description'],
            new \DateTimeImmutable($data['date']),
            $data['category']
            );
        } catch (\Exception $e) {
            $this->logger->error('error updating expense', ['error' => $e->getMessage()]);
            $_SESSION["alert"] = $this->alertGenerator->createAlert(
                'danger',
                'Failed to update expense: ' . $e->getMessage()
            );
          
            return $this->render($response, 'expenses/edit.twig', [
            'expense' => $expense,
            'categories' => $categories,
            'errors' => $errors,
            'old' => $data,
            ]);
        }

        // Redirect to expenses index on success
        return $response
            ->withHeader('Location', '/expenses')
            ->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $response->withStatus(401);
        }

        $expenseId = $routeParams['id'] ?? null;
        if (!$expenseId) {
            return $response->withStatus(404);
        }

        $expense = $this->expenseService->findById((int)$expenseId);
        if (!$expense) {
            return $response->withStatus(404);
        }

        // // Check ownership
        // if ($expense->getUser()->getId() !== $userId) {
        //     return $response->withStatus(403);
        // }

        try {
            $this->expenseService->deleteEntry($expense);
            $_SESSION["alert"] = $this->alertGenerator->createAlert(
            'success',
            'Expense deleted successfully.'
            );
        } catch (\Exception $e) {
            $this->logger->error('error deleting expense', ['error' => $e->getMessage()]);
            $_SESSION["alert"] = $this->alertGenerator->createAlert(
            'danger',
            'Failed to delete expense: ' . $e->getMessage()
            );
        }

        return $response
            ->withHeader('Location', '/expenses')
            ->withStatus(302);
  
    }
}
