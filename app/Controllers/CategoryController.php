<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Helpers\ResponseHelper;

/**
 * Class CategoryController
 * 
 * Exposes endpoint to fetch categories.
 */
class CategoryController
{
    private CategoryRepository $categoryRepo;

    public function __construct()
    {
        $this->categoryRepo = new CategoryRepository();
    }

    private function getAuthenticatedUserId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            ResponseHelper::error("Unauthenticated request.", 401);
            exit;
        }
        return $userId;
    }

    /**
     * GET /api/categories
     */
    public function index(): void
    {
        $userId = $this->getAuthenticatedUserId();
        $type = isset($_GET['type']) ? (string)$_GET['type'] : null;
        
        $categories = $this->categoryRepo->listActive($userId, $type);
        ResponseHelper::success("Categories retrieved successfully.", ['categories' => $categories]);
    }
}
