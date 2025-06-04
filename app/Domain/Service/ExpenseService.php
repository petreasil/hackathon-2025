<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\UploadedFileInterface;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private LoggerInterface $logger,
    ) {}

    public function list(User $user, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        // Fetch paginated list of expenses for the user, filtered by year and month

        return $this->expenses->findByUserAndDate(
            $user->id,
            $year,
            $month,
            $pageNumber,
            $pageSize
        );
        return [];
    }
    public function deleteEntry(Expense $expense): void
    {
        // Delete the expense entry
        $this->expenses->delete($expense->id);
    }
   
    public function create(
        User $user,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
       
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        if (empty($description)) {
            throw new \InvalidArgumentException('Description cannot be empty.');
        }
        if (empty($category)) {
            throw new \InvalidArgumentException('Category cannot be empty.');
        }

   
        $expense = new Expense(
            null,
            $user->id,
            $date,
            $category,
            (int)$amount,
            $description
        );
        $this->expenses->save($expense);
   
    }

    public function update(
        Expense $expense,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        if (empty($description)) {
            throw new \InvalidArgumentException('Description cannot be empty.');
        }
        if (empty($category)) {
            throw new \InvalidArgumentException('Category cannot be empty.');
        }

        $expense->amountCents = (int)$amount;
        $expense->description = $description;
        $expense->date = $date;
        $expense->category = $category;

        $this->expenses->save($expense);
    }

    public function findById(int $id): ?Expense
    {
        // Fetch expense by ID
        return $this->expenses->find($id);
    }
    public function findBy(array $criteria, int $from, int $limit): array
    {
        // Delegate to repository, passing criteria and pagination
        return $this->expenses->findBy($criteria, $from, $limit);
    }

    public function listExpenditureYears(User $user): array
    {
        // Fetch years with expenditures for the user
        return $this->expenses->listExpenditureYears($user);
    }
   
    public function importFromCsv(User $user, UploadedFileInterface $csvFile): int
    {
        // TODO: process rows in file stream, create and persist entities
        // TODO: for extra points wrap the whole import in a transaction and rollback only in case writing to DB fails

        return 0; // number of imported rows
    }

}
