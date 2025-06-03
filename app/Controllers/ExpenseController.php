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

        $expenses = $this->expenseService->list($user, $filterYear, $filterMonth, $page, $pageSize);
        $this->logger->info('Expenses fetched', [
            'user_id' => $userId,
            'year' => $filterYear,
            'month' => $filterMonth,
            'page' => $page,
            'pageSize' => $pageSize,

        "expenses" => $expenses],);
      
        $total = count($expenses);
      

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page'     => $page,
            'pageSize' => $pageSize,
            'year'     => $filterYear,
            'total'    => $total,
            'month'    => $filterMonth,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        
        // Load categories from config file
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
                (float)$data['amount'],
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
        // TODO: implement this action method to display the edit expense page

        // Hints:
        // - obtain the list of available categories from configuration and pass to the view
        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not

        $expense = ['id' => 1];

        return $this->render($response, 'expenses/edit.twig', ['expense' => $expense, 'categories' => []]);
    }

    public function update(Request $request, Response $response, array $routeParams): Response
    {
        // TODO: implement this action method to update an existing expense

        // Hints:
        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not
        // - get the new values from the request and prepare for update
        // - update the expense entity with the new values
        // - rerender the "expenses.edit" page with included errors in case of failure
        // - redirect to the "expenses.index" page in case of success

        return $response;
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        // TODO: implement this action method to delete an existing expense

        // - load the expense to be edited by its ID (use route params to get it)
        // - check that the logged-in user is the owner of the edited expense, and fail with 403 if not
        // - call the repository method to delete the expense
        // - redirect to the "expenses.index" page

        return $response;
    }
}
