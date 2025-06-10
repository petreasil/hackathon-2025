<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use Psr\Log\LoggerInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private LoggerInterface $logger,
    ) {}

    public function computeTotalExpenditure(User $user, int $year, int $month): float
    {
        // TODO: compute expenses total for year-month for a given user delegate to repository
        $categories = $this->expenses->getCategoriesForUser($user->getId(), $year, $month);
        $criteria = [
            'user_id' => $user->getId(),
            'date' => $year,
            'month' => $month,
            'category' => $categories,
        ];
        $sum = $this->expenses->countBy($criteria);
        
      
        return $sum;
    }

    public function computePerCategoryTotals(User $user, int $year, int $month): array
    {
        // Use countBy to get the number of expenses per category for the user in the given month/year
        $categories = $this->expenses->getCategoriesForUser($user->getId(), $year, $month);
        $criteria = [
            'user_id' => $user->getId(),
            'date' => $year,
            'month' => $month,
            'category' => $categories,
        ];
        $sum = $this->expenses->sumAmountsByCategory($criteria);
       
      
        
        return $sum;
    }

    public function computePerCategoryAverages(User $user, int $year, int $month): array
    {
        $categories = $this->expenses->getCategoriesForUser($user->getId(), $year, $month);
        $criteria = [
            'user_id' => $user->getId(),
            'date' => $year,
            'month' => $month,
            'categories' => $categories,
        ];
       
        $averages = $this->expenses->averageAmountsByCategory($criteria);
        
        return $averages;
    }

    public function getOverspendingAlerts(User $user, int $year, int $month): array
    {
    $limit = 5000;
    $alerts = [];
    $totals = $this->computePerCategoryTotals($user, $year, $month);
    
    foreach ($totals as $category => $total) {
        if (is_numeric($total) && $total > $limit) {
            $alerts[$category] = (int)($total - $limit);
        }
    }

    return $alerts;
    }

    public function getAvailableYears(User $user): array
    {
        // Fetch the list of years for which the user has expenses
        return $this->expenses->listExpenditureYears($user);
    }
}
