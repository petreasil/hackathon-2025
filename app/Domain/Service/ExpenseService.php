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
        $imported = 0;
        $stream = $csvFile->getStream()->detach(); // get the native resource
        $handle = fopen('php://temp', 'r+');
        stream_copy_to_stream($stream, $handle);
        rewind($handle);

        
        if (method_exists($this->expenses, 'beginTransaction')) {
            $this->expenses->beginTransaction();
        }

        try {
            // Assume first row is header
            $header = fgetcsv($handle);
            if (!$header) {
            throw new \RuntimeException('CSV file is empty or invalid.');
            }

            while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue; // skip invalid rows
            }
            [$date, $amount, $description, $category] = $row;

            try {
                $expense = new Expense(
                null,
                $user->id,
                new DateTimeImmutable($date),
                $category,
                (int)$amount,
                $description
                );
                $this->expenses->save($expense);
                $imported++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to import expense row', [
                'row' => $row,
                'error' => $e->getMessage(),
                ]);
                // Optionally skip or stop on error
            }
            }

            if (method_exists($this->expenses, 'commit')) {
            $this->expenses->commit();
            }
        } catch (\Throwable $e) {
            if (method_exists($this->expenses, 'rollback')) {
            $this->expenses->rollback();
            }
            throw $e;
        } finally {
            fclose($handle);
        }

        return $imported; 

       
    }

}
