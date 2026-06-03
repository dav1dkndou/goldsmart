<?php declare(strict_types=1);

/**
 * Product Controller
 * Handles product operations
 */
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';

class ProductController extends Controller
{
    private Product $productModel;
    private Category $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
    }

    /**
     * Get all products
     * GET /api/products
     */
    public function index(): void
    {
        // Use filter_input for safer GET parameter access
        $filters = [
            'category' => filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS),
            'search' => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS)
        ];

        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: null;
        $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT) ?: 15;
        $perPage = min(max(1, $perPage), 50); // Enforce strict limit

        $result = $this->productModel->getAllWithCategory($filters, $page, $perPage);
        Response::success($result);
    }

    /**
     * Get single product
     * GET /api/products/{id}
     */
    public function show($id): void
    {
        // Cast to int for type safety
        $product = $this->productModel->getByIdWithCategory((int) $id);

        if (!$product) {
            Response::notFound('Produk tidak ditemukan');
        }

        Response::success($product);
    }

    /**
     * Get featured products
     * GET /api/products/featured
     */
    public function featured(): void
    {
        $products = $this->productModel->getFeatured();
        Response::success($products, 'Success', 200, 300); // Cache for 5 minutes
    }

    /**
     * Get all categories
     * GET /api/categories
     */
    public function categories(): void
    {
        $categories = $this->categoryModel->getActive();
        Response::success($categories, 'Success', 200, 86400); // Cache categories for 1 day
    }
}
