<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Database\Database;
use PDO;

/**
 * Class SystemCategorySeeder
 * 
 * Populates default system-level categories for income and expenses.
 */
class SystemCategorySeeder
{
    /**
     * Run the system category database seeder.
     * 
     * @return void
     */
    public function run(): void
    {
        $categories = [
            // Income Categories
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'wallet', 'color' => '#10B981'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => 'laptop', 'color' => '#3B82F6'],
            ['name' => 'Scholarship', 'type' => 'income', 'icon' => 'academic-cap', 'color' => '#F59E0B'],
            ['name' => 'Business', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#8B5CF6'],
            ['name' => 'Investment Income', 'type' => 'income', 'icon' => 'chart-bar', 'color' => '#EF4444'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'cash', 'color' => '#6B7280'],

            // Expense Categories
            ['name' => 'Food', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#EF4444'],
            ['name' => 'Rent', 'type' => 'expense', 'icon' => 'home', 'color' => '#3B82F6'],
            ['name' => 'Fuel', 'type' => 'expense', 'icon' => 'fire', 'color' => '#F59E0B'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#EC4899'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'book-open', 'color' => '#8B5CF6'],
            ['name' => 'Medical', 'type' => 'expense', 'icon' => 'heart', 'color' => '#10B981'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#F43F5E'],
            ['name' => 'Bills', 'type' => 'expense', 'icon' => 'receipt', 'color' => '#6B7280'],
            ['name' => 'Travel', 'type' => 'expense', 'icon' => 'globe', 'color' => '#06B6D4'],
            ['name' => 'Others', 'type' => 'expense', 'icon' => 'dots-horizontal', 'color' => '#9CA3AF'],
        ];

        $db = Database::connection();

        // Check if the system category already exists (idempotency check)
        $stmtCheck = $db->prepare("
            SELECT id FROM categories 
            WHERE LOWER(name) = LOWER(:name) AND type = :type AND user_id IS NULL AND is_system = TRUE
        ");

        // Insert system category
        $stmtInsert = $db->prepare("
            INSERT INTO categories (id, user_id, name, type, icon, color, is_system, is_active, created_at, updated_at)
            VALUES (gen_random_uuid(), NULL, :name, :type, :icon, :color, TRUE, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        foreach ($categories as $cat) {
            $stmtCheck->execute([
                ':name' => $cat['name'],
                ':type' => $cat['type']
            ]);

            if (!$stmtCheck->fetch()) {
                $stmtInsert->execute([
                    ':name' => $cat['name'],
                    ':type' => $cat['type'],
                    ':icon' => $cat['icon'],
                    ':color' => $cat['color']
                ]);
            }
        }
    }
}
