<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Exception;
use PDO;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private LoggerInterface $logger,
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
           
        } else {
            // Update existing expense
            $query = 'UPDATE expenses SET user_id = :user_id, date = :date, category = :category, amount_cents = :amount_cents, description = :description WHERE id = :id';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'id' => $expense->getId(),
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
        $where = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            $where[] = "$key = :$key";
            $params[$key] = $value;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM expenses $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $statement = $this->pdo->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $from, PDO::PARAM_INT);

        $statement->execute();
        $results = $statement->fetchAll();
        $expenses = [];
        foreach ($results as $data) {
            $expenses[] = $this->createExpenseFromData($data);
        }
        return $expenses;
    
    }
  
    public function listExpenditureYears(User $user): array
    {
        $query = 'SELECT DISTINCT strftime("%Y", date) AS year FROM expenses WHERE user_id = :user_id ORDER BY strftime("%Y", date) DESC';
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':user_id', $user->getId(), PDO::PARAM_INT);
        $statement->execute();
        $years = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $years[] = (int)$row['year'];
        }
        return $years;
    }

    public function countBy(array $criteria): int
    {
        $where = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            if ($key === 'month') {
            // Skip, handled below
            continue;
            }
            if ($key === 'date') {
            // We'll use this as year
            $where[] = 'strftime("%Y", date) = :year';
            $params['year'] = (string)$value;
            } else {
            $where[] = "$key = :$key";
            $params[$key] = $value;
            }
        }
        // Handle month if present in criteria
        if (isset($criteria['month'])) {
            $where[] = 'strftime("%m", date) = :month';
            $params['month'] = str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT * FROM expenses $whereSql";
        $statement = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return (int)($result['amount_cents'] ?? 0);
       
    }
   

    public function sumAmountsByCategory(array $criteria): array
    {
        $where = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            $where[] = "$key = :$key";
            $params[$key] = $value;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT category, SUM(amount_cents) as total_cents FROM expenses $whereSql GROUP BY category";
        $statement = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        $sums = [];
        foreach ($results as $row) {
            $sums[$row['category']] = (int)$row['total_cents'];
        }
        return $sums;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        $this->logger->debug('Calculating averages for last 5 months', ['criteria' => $criteria]);

        // Calculate the start and end date for the last 5 months (including current)
        $year = $criteria['date'] ?? (int)date('Y');
        $month = $criteria['month'] ?? (int)date('n');
        $userId = $criteria['user_id'] ?? null;

        $start = (new \DateTimeImmutable("$year-$month-01"))->modify('-4 months')->setTime(0, 0);
        $end = (new \DateTimeImmutable("$year-$month-01"))->modify('last day of this month')->setTime(23, 59, 59);

        $params = [
            ':user_id' => $userId,
            ':start_date' => $start->format('Y-m-d'),
            ':end_date' => $end->format('Y-m-d'),
        ];

        $query = "SELECT category, amount_cents as total_cents
              FROM expenses
              WHERE user_id = :user_id
                AND date BETWEEN :start_date AND :end_date
              GROUP BY category";

        $statement = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $averages = [];
        $totalSum = 0;
        foreach ($results as $row) {
            $category = $row['category'];
            $sum = (int)$row['total_cents'];
            $averageValue = $sum / 5;
            $averages[$category] = [
            'value' => $averageValue,

            ];
            $totalSum += $sum;
        }
        foreach ($averages as $category => &$data) {
            $data['percentage'] = $totalSum > 0 ? ($data['value'] / ($totalSum / 5)) * 100 : 0;
        }
        unset($data);

        return $averages;
    }

    public function sumAmounts(array $criteria): float
    {
        $where = [];
        $params = [];
        foreach ($criteria as $key => $value) {
            $where[] = "$key = :$key";
            $params[$key] = $value;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT SUM(amount_cents) as total_cents FROM expenses $whereSql";
        $statement = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $statement->bindValue(":$key", $value);
        }
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return isset($result['total_cents']) ? ((float)$result['total_cents']) : 0.0;
    }

    public function getCategoriesForUser(int $userId, int $year, int $month): array
    {
        $query = 'SELECT DISTINCT category FROM expenses WHERE user_id = :user_id AND strftime("%Y", date) = :year AND strftime("%m", date) = :month';
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':year', (string)$year, PDO::PARAM_STR);
        $statement->bindValue(':month', str_pad((string)$month, 2, '0', STR_PAD_LEFT), PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
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
  
        $query = 'SELECT * FROM expenses WHERE user_id = :user_id AND strftime("%Y", date) = :year AND strftime("%m", date) = :month ORDER BY id ASC LIMIT :limit OFFSET :offset';
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
