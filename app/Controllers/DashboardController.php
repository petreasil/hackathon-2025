<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\MonthlySummaryService;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly UserRepositoryInterface $userRepository,
         private LoggerInterface $logger,
        // TODO: add necessary services here and have them injected by the DI container
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        // Parse the request parameters
        $params = $request->getQueryParams();
        $selectedYear = $params['year'] ?? date('Y');
        $selectedMonth = $params['month'] ?? date('m');
        
        // Load the currently logged-in user
        $user = null;
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $user = $this->userRepository->find($userId);
        }
        if (!$user) {
            $session = $request->getAttribute('session');
            $userId = $session ? $session->get('user_id') : null;
            $user = $userId ? $this->userRepository->find($userId) : null;
        }
        if (!$user) {
            // Handle unauthenticated user (redirect or error)
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        // Get the list of available years for the year-month selector
        $availableYears = $this->monthlySummaryService->getAvailableYears($user);

        // Call service to generate the overspending alerts for current month
        $alerts = $this->monthlySummaryService->getOverspendingAlerts($user, (int)$selectedYear, (int)$selectedMonth);

        // Call service to compute total expenditure per selected year/month
        $totalForMonth = $this->monthlySummaryService->computeTotalExpenditure($user, (int)$selectedYear, (int)$selectedMonth);

        // Call service to compute category totals per selected year/month
        $totalsForCategories = $this->monthlySummaryService->computePerCategoryTotals($user, (int)$selectedYear, (int)$selectedMonth);
        
        // Call service to compute category averages per selected year/month
        $averagesForCategories = $this->monthlySummaryService->computePerCategoryAverages($user, (int)$selectedYear, (int)$selectedMonth);
        
        $totalsForCategoriesView = [];
        $grandTotal = array_sum($totalsForCategories);
        foreach ($totalsForCategories as $category => $value) {
            $percentage = $grandTotal > 0 ? round(($value / $grandTotal) * 100, 2) : 0;
            $totalsForCategoriesView[$category] = [
            'value' => $value,
            'percentage' => $percentage,
            ];
        }

        $averagesForCategoriesView = [];
        $grandAverage = array_sum($averagesForCategories);
        foreach ($averagesForCategories as $category => $value) {
            $percentage = $grandAverage > 0 ? round(($value / $grandAverage) * 100, 2) : 0;
            $averagesForCategoriesView[$category] = [
            'value' => $value,
            'percentage' => $percentage,
            ];
        }

        
     
        return $this->render($response, 'dashboard.twig', [
            "selectedYear"=> $selectedYear,
            'selectedMonth'=> $selectedMonth,
            'years'                 => $availableYears,
            'alerts'                => $alerts,
            'totalForMonth'         => $totalForMonth,
            'totalsForCategories'   => $totalsForCategoriesView,
            'averagesForCategories' => $averagesForCategoriesView,
        ]);
    }

   
}
