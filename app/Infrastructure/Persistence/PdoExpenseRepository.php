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
    $sql = "SELECT SUM(amount_cents) AS total FROM expenses WHERE 1=1";
    $params = [];

    if (isset($criteria['user_id'])) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $criteria['user_id'];
    }

    if (isset($criteria['date'])) {
        $sql .= " AND strftime('%Y', date) = :year";
        $params[':year'] = (string)$criteria['date'];
    }

    if (isset($criteria['month'])) {
        $sql .= " AND strftime('%m', date) = :month";
        $params[':month'] = str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT);
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    $result = $stmt->fetchColumn();
    return $result !== null ? (int)$result : 0;
       
    }
   

    public function sumAmountsByCategory(array $criteria): array
    {
    $sql = "SELECT category, SUM(amount_cents) AS total FROM expenses WHERE 1=1";
    $params = [];

    // Filter by user_id
    if (isset($criteria['user_id'])) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $criteria['user_id'];
    }

    // Filter by year from DATE column
    if (isset($criteria['date'])) {
        $sql .= " AND strftime('%Y', date) = :year";
        $params[':year'] = (string)$criteria['date'];
    }

    // Filter by month from DATE column
    if (isset($criteria['month'])) {
        $sql .= " AND strftime('%m', date) = :month";
        $params[':month'] = str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT);
    }

    // Filter by category list
    if (!empty($criteria['category']) && is_array($criteria['category'])) {
        $placeholders = [];
        foreach ($criteria['category'] as $index => $cat) {
            $key = ":cat$index";
            $placeholders[] = $key;
            $params[$key] = $cat;
        }
        $sql .= " AND category IN (" . implode(', ', $placeholders) . ")";
    }

    $sql .= " GROUP BY category";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [category => total]

    // Return all categories from criteria with 0 fallback
    $totals = [];
    foreach ($criteria['category'] as $cat) {
        $totals[$cat] = isset($results[$cat]) ? (int)$results[$cat] : 0;
    }
    
    return $totals;
    }

    public function averageAmountsByCategory(array $criteria): array
    {
    $categories = $criteria['categories'] ?? [];

    if (empty($categories)) return [];

    // Ensure totals use the correct key
    $totals = $this->sumAmountsByCategory([
        'user_id' => $criteria['user_id'] ?? null,
        'date' => $criteria['date'] ?? null,
        'month' => $criteria['month'] ?? null,
        'category' => $categories,
    ]);

    // Prepare base query for counts
    $sql = "SELECT category, COUNT(*) AS count FROM expenses WHERE 1=1";
    $params = [];

    if (!empty($criteria['user_id'])) {
        $sql .= " AND user_id = :user_id";
        $params[':user_id'] = $criteria['user_id'];
    }

    if (!empty($criteria['date'])) {
        $sql .= " AND strftime('%Y', date) = :year";
        $params[':year'] = (string) $criteria['date'];
    }

    if (!empty($criteria['month'])) {
        $sql .= " AND strftime('%m', date) = :month";
        $params[':month'] = str_pad((string) $criteria['month'], 2, '0', STR_PAD_LEFT);
    }

    // Handle category list
    $placeholders = [];
    foreach ($categories as $i => $cat) {
        $key = ":cat$i";
        $placeholders[] = $key;
        $params[$key] = $cat;
    }
    $sql .= " AND category IN (" . implode(', ', $placeholders) . ")";
    $sql .= " GROUP BY category";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [category => count]

    // Calculate average = total / count, with fallback to 0
    $averages = [];
    foreach ($categories as $cat) {
        $total = $totals[$cat] ?? 0;
        $count = $counts[$cat] ?? 0;
        $averages[$cat] = $count > 0 ? (int) round($total / $count) : 0;
    }

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
        $query2 = 'SELECT COUNT(*) as count FROM expenses WHERE user_id = :user_id AND strftime("%Y", date) = :year AND strftime("%m", date) = :month';
        $query = 'SELECT * FROM expenses WHERE user_id = :user_id AND strftime("%Y", date) = :year AND strftime("%m", date) = :month ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($query);
        $statement2 = $this->pdo->prepare($query2);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':year', (string)$year, PDO::PARAM_STR);
        $statement->bindValue(':month', str_pad((string)$month, 2, '0', STR_PAD_LEFT), PDO::PARAM_STR);
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $statement2->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement2->bindValue(':year', (string)$year, PDO::PARAM_STR);
        $statement2->bindValue(':month', str_pad((string)$month, 2, '0', STR_PAD_LEFT), PDO::PARAM_STR);
        $statement2->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        $totalRow = $statement2->fetch(PDO::FETCH_ASSOC);
        $totalCount = isset($totalRow['count']) ? (int)$totalRow['count'] : 0;
        $expenses = [];
        foreach ($results as $data) {
            $expenses[] = $this->createExpenseFromData($data);
        }
        return [
            'total' => $totalCount,
            'expenses' => $expenses,
        ];
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
