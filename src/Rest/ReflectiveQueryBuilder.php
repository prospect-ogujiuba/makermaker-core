<?php

namespace MakermakerCore\Rest;

use TypeRocket\Models\Model;
use TypeRocket\Http\Request;

/**
 * Reflective Query Builder
 *
 * Introspects models to build safe, filtered, searchable, sortable queries
 * with zero per-model configuration.
 */
class ReflectiveQueryBuilder
{
    /**
     * Common text field names for auto-search detection
     */
    private const TEXT_FIELD_PATTERNS = [
        'name',
        'title',
        'description',
        'desc',
        'short_desc',
        'long_desc',
        'slug',
        'sku',
        'code',
        'email',
        'content',
        'body',
        'notes',
        'comments',
        'address',
        'city',
        'region',
        'type'
    ];

    /**
     * Fields to exclude from automatic search
     */
    private const EXCLUDED_FROM_SEARCH = [
        '_id',
        '_at',
        '_by',
        'password',
        'token',
        'secret',
        'version',
        'metadata',
        'hash'
    ];

    private Model $model;
    private Request $request;

    public function __construct(Model $model, Request $request)
    {
        $this->model = $model;
        $this->request = $request;
    }

    /**
     * Build query from request parameters
     *
     * @return \TypeRocket\Database\Query
     */
    public function build()
    {
        // Use the model instance directly
        $query = $this->model;

        // Always exclude soft-deleted records
        // Note: ->where() returns $this, so we chain from the model instance
        $query = $query->where('deleted_at', '=', null);

        // Apply search if present
        if ($searchTerm = $_GET['search'] ?? null) {
            $this->applySearch($query, $searchTerm);
        }

        // Apply field filters
        $this->applyFilters($query);

        // Apply sorting
        $this->applySorting($query);

        return $query;
    }

    /**
     * Execute query with pagination using TypeRocket's built-in paginate()
     *
     * @return array [data, meta]
     */
    public function execute(): array
    {
        $query = $this->build();

        $perPage = $this->getPerPage();
        $page = $this->getPage();

        // Use TypeRocket's built-in pagination
        $pager = $query->paginate($perPage, $page);

        // Extract results and metadata - handle null safely
        $results = $pager ? $pager->getResults() : null;
        $data = $results ? (is_array($results) ? $results : $results->toArray()) : [];

        return [
            'data' => $data,
            'meta' => [
                'total' => $pager ? $pager->getCount() : 0,
                'per_page' => $pager ? $pager->getNumberPerPage() : $perPage,
                'current_page' => $pager ? $pager->getCurrentPage() : $page,
                'last_page' => $pager ? $pager->getLastPage() : 1,
                'from' => ($pager && $pager->getCount() > 0) ? (($page - 1) * $perPage + 1) : 0,
                'to' => ($pager && $pager->getCount() > 0) ? min($page * $perPage, $pager->getCount()) : 0,
            ]
        ];
    }

    /**
     * Apply full-text search across searchable fields
     */
    private function applySearch($query, string $searchTerm): void
    {
        $searchable = $this->getSearchableFields();

        if (empty($searchable)) {
            return; // No searchable fields
        }

        // Sanitize search term
        $searchTerm = $this->sanitizeSearchTerm($searchTerm);

        // TypeRocket doesn't support closure-based where, but supports appendRawWhere()
        global $wpdb;
        $escapedTerm = $wpdb->esc_like($searchTerm);
        $escapedTerm = '%' . $escapedTerm . '%';

        $conditions = [];
        foreach ($searchable as $field) {
            $conditions[] = $wpdb->prepare("`{$field}` LIKE %s", $escapedTerm);
        }

        if (!empty($conditions)) {
            $whereClause = '(' . implode(' OR ', $conditions) . ')';
            $query->appendRawWhere('AND', $whereClause);
        }
    }

    /**
     * Apply field filters from query parameters
     */
    private function applyFilters($query): void
    {
        $queryParams = $_GET; // Use $_GET directly for URL parameters
        $filterable = $this->getFilterableFields();

        // Reserved query parameters
        $reserved = ['search', 'orderby', 'order', 'per_page', 'page', 'with'];

        foreach ($queryParams as $key => $value) {
            if (in_array($key, $reserved)) {
                continue;
            }

            if (!in_array($key, $filterable)) {
                throw new \InvalidArgumentException("Invalid filter field: {$key}");
            }

            // Apply filter
            if ($value === 'null' || $value === null) {
                $query->where($key, '=', null);
            } else {
                $query->where($key, $this->sanitizeFilterValue($value));
            }
        }
    }

    /**
     * Apply sorting from query parameters
     */
    private function applySorting($query): void
    {
        $orderby = $_GET['orderby'] ?? 'id';
        $order = strtolower($_GET['order'] ?? 'asc');

        // Validate order direction
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        // Validate sortable field
        if (!$this->isSortableField($orderby)) {
            $orderby = 'id'; // Fallback to safe default
        }

        $query->orderBy($orderby, $order);
    }

    /**
     * Get searchable fields for the model
     *
     * Priority:
     * 1. Model-defined method: getSearchableFields()
     * 2. Auto-detect text fields from fillable
     */
    private function getSearchableFields(): array
    {
        // Check if model defines custom searchable fields
        if (method_exists($this->model, 'getSearchableFields')) {
            return $this->model->getSearchableFields();
        }

        // Auto-detect from fillable fields using reflection
        $fillable = $this->getFillableProperty();
        $searchable = [];

        foreach ($fillable as $field) {
            // Check if field matches text patterns
            if ($this->isTextFieldCandidate($field)) {
                $searchable[] = $field;
            }
        }

        return $searchable;
    }

    /**
     * Get fillable property via reflection
     */
    private function getFillableProperty(): array
    {
        $reflection = new \ReflectionClass($this->model);

        if ($reflection->hasProperty('fillable')) {
            $fillableProp = $reflection->getProperty('fillable');
            $fillableProp->setAccessible(true);
            return $fillableProp->getValue($this->model) ?? [];
        }

        return [];
    }

    /**
     * Check if field is likely a text field suitable for search
     */
    private function isTextFieldCandidate(string $field): bool
    {
        // Exclude fields with certain patterns
        foreach (self::EXCLUDED_FROM_SEARCH as $pattern) {
            if (stripos($field, $pattern) !== false) {
                return false;
            }
        }

        // Include fields matching text patterns
        foreach (self::TEXT_FIELD_PATTERNS as $pattern) {
            if (stripos($field, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get filterable fields (fillable minus guarded)
     */
    private function getFilterableFields(): array
    {
        $fillable = $this->getFillableProperty();
        $guarded = $this->getGuardedProperty();

        return array_diff($fillable, $guarded);
    }

    /**
     * Get guarded property via reflection
     */
    private function getGuardedProperty(): array
    {
        $reflection = new \ReflectionClass($this->model);

        if ($reflection->hasProperty('guard')) {
            $guardProp = $reflection->getProperty('guard');
            $guardProp->setAccessible(true);
            return $guardProp->getValue($this->model) ?? [];
        }

        return [];
    }

    /**
     * Get sortable fields
     */
    private function getSortableFields(): array
    {
        $filterable = $this->getFilterableFields();

        // Always allow sorting by these fields
        $alwaysSortable = ['id', 'created_at', 'updated_at'];

        return array_unique(array_merge($filterable, $alwaysSortable));
    }

    /**
     * Check if field is sortable
     */
    private function isSortableField(string $field): bool
    {
        return in_array($field, $this->getSortableFields());
    }

    /**
     * Get per_page parameter with validation
     * If not specified, returns 100 (effectively all records)
     */
    private function getPerPage(): int
    {
        // Default to 100 if not specified (no limit effectively)
        $perPage = (int) ($_GET['per_page'] ?? 100);

        // Enforce minimum and maximum
        return max(1, min($perPage, 100));
    }

    /**
     * Get page parameter with validation
     */
    private function getPage(): int
    {
        $page = (int) ($_GET['page'] ?? 1);

        // Ensure minimum page 1
        return max(1, $page);
    }

    /**
     * Sanitize search term to prevent SQL injection
     */
    private function sanitizeSearchTerm(string $term): string
    {
        // Remove potentially dangerous characters
        $term = trim($term);
        $term = strip_tags($term);

        // Escape special LIKE characters
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return $term;
    }

    /**
     * Sanitize filter value
     */
    private function sanitizeFilterValue($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeFilterValue'], $value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        return sanitize_text_field($value);
    }

    /**
     * Get model instance
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get request instance
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
