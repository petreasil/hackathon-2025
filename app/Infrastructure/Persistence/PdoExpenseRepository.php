<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        if ($expense->getId() === null) {
            // Insert new expense
            $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) VALUES (:user_id, :date, :category, :amount_cents, :description)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'user_id' => $expense->getUserId(),
                'date' => $expense->getDate()->format('Y-m-d'),
                'category' => $expense->getCategory(),
                'amount_cents' => $expense->getAmountCents(),
                'description' => $expense->getDescription(),
            ]);
           
        } 
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    public function findBy(array $criteria, int $from, int $limit): array
    {
        // TODO: Implement findBy() method.
        return [];
    }


    public function countBy(array $criteria): int
    {
        // TODO: Implement countBy() method.
        return 0;
    }

    public function listExpenditureYears(User $user): array
    {
        // TODO: Implement listExpenditureYears() method.
        return [];
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        // TODO: Implement sumAmountsByCategory() method.
        return [];
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        // TODO: Implement averageAmountsByCategory() method.
        return [];
    }

    public function sumAmounts(array $criteria): float
    {
        // TODO: Implement sumAmounts() method.
        return 0;
    }

    /**
     * Fetch paginated list of expenses for the user, filtered by year and month
     *
     * @param int $userId
     * @param int $year
     * @param int $month
     * @param int $pageNumber
     * @param int $pageSize
     * @return Expense[]
     */
    public function findByUserAndDate(int $userId, int $year, int $month, int $pageNumber, int $pageSize, ?int &$total = null): array
    {
        $offset = ($pageNumber - 1) * $pageSize;
      
        $query = 'SELECT * FROM expenses WHERE user_id = :user_id AND strftime("%Y", date) = :year AND strftime("%m", date) = :month ORDER BY date DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':year', (string)$year, PDO::PARAM_STR);
        $statement->bindValue(':month', str_pad((string)$month, 2, '0', STR_PAD_LEFT), PDO::PARAM_STR);
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $results = $statement->fetchAll();
        $expenses = [];
        foreach ($results as $data) {
            $expenses[] = $this->createExpenseFromData($data);
        }
        return $expenses;
    }
    

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount_cents'],
            $data['description'],
        );
    }
}
