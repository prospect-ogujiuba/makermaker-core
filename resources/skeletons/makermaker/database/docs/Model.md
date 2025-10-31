# TypeRocket Model.php Masterclass: From Foundation to Mastery

## Table of Contents
1. [Core Foundation & MVC Architecture](#core-foundation--mvc-architecture)
2. [Deep Dive Analysis](#deep-dive-analysis)
3. [Practical Applications](#practical-applications)
4. [Advanced Usage & Best Practices](#advanced-usage--best-practices)
5. [Integration Patterns](#integration-patterns)
6. [Scaling & Performance](#scaling--performance)

---

## Core Foundation & MVC Architecture

### What is TypeRocket's Model?

TypeRocket's `Model.php` is a sophisticated **Active Record** implementation that serves as the **"M" in MVC**. Unlike traditional PHP ORMs, it's specifically designed for WordPress environments, bridging the gap between WordPress's meta-based data storage and modern ORM patterns.

**Key Architectural Position:**
```
Controller ──► Model ──► Database/WordPress
     ▲           │         (wp_posts, wp_postmeta, 
     │           ▼          custom tables, etc.)
    View ◄─── Data/Results
```

### Core Design Philosophy

1. **WordPress-First**: Integrates seamlessly with WordPress's meta system
2. **Flexible Data Storage**: Handles both database columns (`builtin`) and meta fields
3. **Security-Minded**: Built-in fillable/guard protection
4. **Relationship-Rich**: Comprehensive relationship methods
5. **Extensible**: Trait-based architecture for modularity

---

## Deep Dive Analysis

### Class Structure & Key Properties

```php
class Model implements Formable, JsonSerializable, \Stringable
{
    use Searchable, FieldValue, ArrayReplaceRecursiveValues;
```

**Critical Traits:**
- **`Searchable`**: Provides search functionality for admin interfaces
- **`FieldValue`**: Handles WordPress meta and field value parsing
- **`ArrayReplaceRecursiveValues`**: Deep array manipulation utilities

### Essential Property Arrays

#### 1. **Security Layer** (`$fillable` & `$guard`)
```php
protected $fillable = [];           // Mass assignable fields
protected $guard = ['id'];          // Protected fields
```

**How it works:**
- Empty `$fillable` = allow all except `$guard`
- Non-empty `$fillable` = allow only these fields
- `$guard` always takes precedence over `$fillable`

#### 2. **Data Classification** (`$builtin`, `$metaless`, `$private`)
```php
protected $builtin = [];    // WordPress core table columns
protected $metaless = [];   // Fields that bypass meta storage
protected $private = [];    // Hidden from REST API
```

#### 3. **Data Transformation** (`$cast`, `$format`)
```php
protected $cast = [];       // Type casting (int, bool, json, etc.)
protected $format = [];     // Pre-save formatting with callbacks
```

#### 4. **Relationships & Advanced** (`$with`, `$static`)
```php
protected $with = [];       // Eager loading relationships
protected $static = [];     // Static values that override input
```

### Key Methods Architecture

#### **Query Building Chain**
```php
// Method chaining for query building
$results = $model
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->with(['author', 'comments'])
    ->paginate(10);
```

#### **CRUD Operations**
```php
// Intelligent save operation
public function save($fields = [])
{
    if (isset($this->properties[$this->idColumn]) && $this->findById($this->properties[$this->idColumn])) {
        return $this->update($fields);  // Updates existing
    }
    return $this->create($fields);      // Creates new
}
```

### Relationship System Deep Dive

TypeRocket implements **4 core relationship types**:

#### **1. hasOne** - One-to-One
```php
public function hasOne($modelClass, $id_foreign = null, $id_local = null, $scope = null)
```
- **Use Case**: User → Profile, Post → Featured Image
- **Foreign Key Convention**: `{resource}_id`

#### **2. hasMany** - One-to-Many
```php  
public function hasMany($modelClass, $id_foreign = null, $id_local = null, $scope = null)
```
- **Use Case**: User → Posts, Category → Articles
- **Returns**: Collection of models

#### **3. belongsTo** - Inverse One-to-Many
```php
public function belongsTo($modelClass, $id_local = null, $id_foreign = null, $scope = null)
```
- **Use Case**: Post → Author, Comment → Post
- **Foreign Key**: Stored on current model

#### **4. belongsToMany** - Many-to-Many
```php
public function belongsToMany($modelClass, $junction_table, $id_column = null, $id_foreign = null, $scope = null)
```
- **Use Case**: Posts ↔ Tags, Users ↔ Roles
- **Requires**: Junction table for relationships

---

## Practical Applications

### 1. Basic Model Extension

```php
<?php
namespace App\Models;

use TypeRocket\Models\Model;

class Product extends Model
{
    protected $resource = 'product';
    protected $table = 'products';
    
    // Security: Only allow these fields for mass assignment
    protected $fillable = [
        'name', 'description', 'price', 'category_id', 'features'
    ];
    
    // Guard critical fields
    protected $guard = ['id', 'created_at', 'updated_at'];
    
    // WordPress core table columns
    protected $builtin = ['name', 'price', 'category_id'];
    
    // Meta fields (stored in wp_postmeta or custom meta tables)
    // Everything not in $builtin or $metaless becomes meta
    
    // Type casting
    protected $cast = [
        'price' => 'float',
        'features' => 'json',
        'is_featured' => 'bool',
        'launch_date' => 'date'
    ];
    
    // Format data before saving
    protected $format = [
        'price' => 'floatval',          // Simple callback
        'name' => [self::class, 'formatName'],  // Class method
        'description' => function($value) {     // Closure
            return strip_tags($value);
        }
    ];
    
    // Static values that override input
    protected $static = [
        'status' => 'active',
        'created_by' => 1  // Current user ID in real scenario
    ];
    
    // Eager load these relationships by default
    protected $with = ['category', 'reviews'];
    
    // Initialize model
    protected function init()
    {
        // Custom initialization logic
        $this->appendFillableField('custom_field');
    }
    
    public static function formatName($value)
    {
        return ucwords(trim($value));
    }
}
```

### 2. Relationship Definitions

```php
class Product extends Model
{
    // One product belongs to one category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    // One product has many reviews
    public function reviews()
    {
        return $this->hasMany(Review::class)->orderBy('created_at', 'DESC');
    }
    
    // Product belongs to many tags (many-to-many)
    public function tags()
    {
        return $this->belongsToMany(
            Tag::class,
            'product_tags',  // junction table
            'product_id',    // current model's foreign key in junction
            'tag_id'         // related model's foreign key in junction
        );
    }
    
    // One product has one featured image
    public function featuredImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')
                    ->where('is_featured', true);
    }
    
    // Relationship with scope constraints
    public function activeReviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'id', function($query) {
            $query->where('status', 'approved')
                  ->where('rating', '>=', 3);
        });
    }
}
```

### 3. Advanced Query Building

```php
class ProductService 
{
    public function getFeaturedProducts($limit = 10)
    {
        return (new Product())
            ->with(['category', 'featuredImage'])
            ->where('is_featured', true)
            ->where('status', 'active')
            ->where('price', '>', 0)
            ->orderBy('featured_at', 'DESC')
            ->take($limit)
            ->get();
    }
    
    public function getProductsByPriceRange($min, $max, $category = null)
    {
        $query = (new Product())
            ->where('price', '>=', $min)
            ->where('price', '<=', $max)
            ->where('status', 'active');
            
        if ($category) {
            $query->whereRelationship('category', true, function($categoryModel) use ($category) {
                $categoryModel->where('slug', $category);
            });
        }
        
        return $query->orderBy('price', 'ASC')->get();
    }
    
    public function searchProducts($term)
    {
        return (new Product())
            ->where('name', 'LIKE', "%{$term}%")
            ->orWhere('description', 'LIKE', "%{$term}%")
            ->with(['category'])
            ->published()  // Custom scope method
            ->paginate(20);
    }
}
```

### 4. Custom Accessors & Mutators

```php
class Product extends Model 
{
    // Accessor: Modifies value when retrieving
    public function getPriceFormattedProperty($value)
    {
        return '$' . number_format($this->properties['price'], 2);
    }
    
    public function getSlugProperty($value)
    {
        if (!$value && isset($this->properties['name'])) {
            return sanitize_title($this->properties['name']);
        }
        return $value;
    }
    
    // Mutator: Modifies value when setting
    public function setNameProperty($value)
    {
        return ucwords(strtolower(trim($value)));
    }
    
    public function setPriceProperty($value)
    {
        return abs(floatval($value)); // Ensure positive number
    }
    
    // Usage in code:
    // $product->price_formatted  // Returns "$29.99"
    // $product->slug            // Auto-generated from name
    // $product->name = "AWESOME product";  // Stored as "Awesome Product"
}
```

---

## Advanced Usage & Best Practices

### 1. Model Hooks & Events

While TypeRocket doesn't have built-in model hooks like Eloquent, you can implement them:

```php
class Product extends Model 
{
    public function save($fields = [])
    {
        $this->beforeSave($fields);
        
        $result = parent::save($fields);
        
        if ($result) {
            $this->afterSave($fields, $result);
        }
        
        return $result;
    }
    
    protected function beforeSave(&$fields)
    {
        // Generate slug if not provided
        if (empty($fields['slug']) && !empty($fields['name'])) {
            $fields['slug'] = sanitize_title($fields['name']);
        }
        
        // Set timestamps
        if (!$this->getID()) {
            $fields['created_at'] = current_time('mysql');
        }
        $fields['updated_at'] = current_time('mysql');
        
        // Validate business rules
        if (!empty($fields['price']) && $fields['price'] < 0) {
            throw new \Exception('Price cannot be negative');
        }
    }
    
    protected function afterSave($fields, $result)
    {
        // Clear cache
        wp_cache_delete("product_{$this->getID()}", 'products');
        
        // Send notifications
        if (!empty($fields['status']) && $fields['status'] === 'published') {
            do_action('product_published', $this);
        }
        
        // Update search index
        do_action('update_product_search_index', $this);
    }
}
```

### 2. Advanced Relationship Queries

```php
class Product extends Model 
{
    // Load relationship with specific conditions
    public function loadReviewsWithRating($minRating = 4)
    {
        $this->reviews = $this->hasMany(Review::class)
            ->where('rating', '>=', $minRating)
            ->where('status', 'approved')
            ->orderBy('created_at', 'DESC')
            ->get();
            
        return $this;
    }
    
    // Query products with relationship conditions
    public static function withHighRatedReviews($minRating = 4, $minReviewCount = 5)
    {
        return (new self())
            ->whereRelationship('reviews', true, function($review) use ($minRating) {
                $review->where('rating', '>=', $minRating)
                       ->where('status', 'approved');
            })
            ->appendRawWhere('AND', "
                (SELECT COUNT(*) FROM reviews 
                 WHERE reviews.product_id = products.id 
                 AND reviews.status = 'approved') >= {$minReviewCount}
            ");
    }
    
    // Attach/Detach for many-to-many relationships
    public function attachTags($tagIds)
    {
        if (!is_array($tagIds)) {
            $tagIds = [$tagIds];
        }
        
        foreach ($tagIds as $tagId) {
            $this->tags()->attach($tagId);
        }
        
        return $this;
    }
    
    public function syncTags($tagIds)
    {
        // Remove all existing tags
        $this->tags()->detach();
        
        // Attach new tags
        return $this->attachTags($tagIds);
    }
}
```

### 3. Caching Strategies

```php
class Product extends Model 
{
    protected $cache = true; // Enable WordPress object cache
    
    public function findByIdCached($id, $ttl = 3600)
    {
        $cacheKey = "product_{$id}";
        $product = wp_cache_get($cacheKey, 'products');
        
        if (false === $product) {
            $product = $this->findById($id);
            if ($product) {
                wp_cache_set($cacheKey, $product, 'products', $ttl);
            }
        }
        
        return $product;
    }
    
    public function findFeaturedCached($limit = 10, $ttl = 1800)
    {
        $cacheKey = "featured_products_{$limit}";
        $products = wp_cache_get($cacheKey, 'products');
        
        if (false === $products) {
            $products = $this->where('is_featured', true)
                            ->where('status', 'active')
                            ->take($limit)
                            ->get();
            wp_cache_set($cacheKey, $products, 'products', $ttl);
        }
        
        return $products;
    }
    
    // Cache invalidation
    protected function afterSave($fields, $result)
    {
        parent::afterSave($fields, $result);
        
        // Clear individual cache
        wp_cache_delete("product_{$this->getID()}", 'products');
        
        // Clear list caches
        wp_cache_delete('featured_products_10', 'products');
        wp_cache_delete('latest_products_20', 'products');
    }
}
```

### 4. Validation Integration

```php
class Product extends Model 
{
    public function validate($fields)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'string|max:2000',
            'features' => 'array'
        ];
        
        $validator = \TypeRocket\Utility\Validator::new($rules, $fields);
        
        // Custom validation
        $validator->extend('positive_price', function($field, $value, $params) {
            return floatval($value) > 0;
        });
        
        if (!$validator->passed()) {
            throw new \Exception('Validation failed: ' . implode(', ', $validator->getErrors()));
        }
        
        return true;
    }
    
    public function create($fields = [])
    {
        $fields = $this->provisionFields($fields);
        $this->validate($fields);
        
        return parent::create($fields);
    }
    
    public function update($fields = [])
    {
        $fields = $this->provisionFields($fields);
        $this->validate($fields);
        
        return parent::update($fields);
    }
}
```

---

## Integration Patterns

### 1. Controller Integration

```php
class ProductController extends Controller 
{
    use LoadsModel;
    
    protected $modelClass = Product::class;
    
    public function create(Request $request, Response $response)
    {
        /** @var Product $model */
        $model = new $this->modelClass;
        
        try {
            // Validate
            if (!$this->onValidate('save', 'create', $model)) {
                throw new ModelException('Validation failed');
            }
            
            // Create with relationships
            $fields = $this->getFields();
            $product = $model->create($fields);
            
            // Handle many-to-many relationships
            if (!empty($fields['tag_ids'])) {
                $product->syncTags($fields['tag_ids']);
            }
            
            // Handle file uploads
            if (!empty($fields['images'])) {
                $this->handleImageUpload($product, $fields['images']);
            }
            
            $this->onAction('save', 'create', $product);
            
            $response->flashNext('Product created successfully', 'success');
            
        } catch (Exception $e) {
            $response->flashNext($e->getMessage(), 'error');
            $this->onAction('error', 'create', $e, $model);
        }
        
        return $this->returnJsonOrGoBack();
    }
    
    protected function handleImageUpload($product, $images)
    {
        foreach ($images as $index => $image) {
            $productImage = new ProductImage();
            $productImage->create([
                'product_id' => $product->getID(),
                'image_url' => $image['url'],
                'is_featured' => $index === 0,
                'alt_text' => $image['alt'] ?? ''
            ]);
        }
    }
}
```

### 2. View Integration

```php
// In your template/view files
$products = (new Product())
    ->with(['category', 'featuredImage'])
    ->where('status', 'active')
    ->paginate(12);

foreach ($products as $product): ?>
    <div class="product-card">
        <?php if ($product->featuredImage): ?>
            <img src="<?php echo $product->featuredImage->image_url; ?>" 
                 alt="<?php echo esc_attr($product->name); ?>">
        <?php endif; ?>
        
        <h3><?php echo esc_html($product->name); ?></h3>
        <p class="price"><?php echo $product->price_formatted; ?></p>
        
        <?php if ($product->category): ?>
            <span class="category"><?php echo esc_html($product->category->name); ?></span>
        <?php endif; ?>
        
        <p class="description">
            <?php echo wp_trim_words($product->description, 20); ?>
        </p>
    </div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($products->hasPages()): ?>
    <div class="pagination">
        <?php echo $products->pagination(); ?>
    </div>
<?php endif; ?>
```

### 3. REST API Integration

```php
class ProductApiController extends Controller 
{
    public function index(Request $request)
    {
        $query = new Product();
        
        // Apply filters
        if ($category = $request->input('category')) {
            $query->whereRelationship('category', true, function($cat) use ($category) {
                $cat->where('slug', $category);
            });
        }
        
        if ($priceMin = $request->input('price_min')) {
            $query->where('price', '>=', floatval($priceMin));
        }
        
        if ($priceMax = $request->input('price_max')) {
            $query->where('price', '<=', floatval($priceMax));
        }
        
        // Search
        if ($search = $request->input('search')) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
        }
        
        // Sort
        $sortBy = $request->input('sort', 'name');
        $sortDir = $request->input('direction', 'ASC');
        $query->orderBy($sortBy, $sortDir);
        
        // Load relationships
        $query->with(['category', 'tags']);
        
        // Paginate
        $perPage = min($request->input('per_page', 20), 100);
        $products = $query->paginate($perPage);
        
        return response()->json([
            'data' => $products->toArray(),
            'pagination' => [
                'current_page' => $products->getCurrentPage(),
                'total_pages' => $products->getTotalPages(),
                'per_page' => $products->getPerPage(),
                'total_items' => $products->getCount()
            ]
        ]);
    }
}
```

---

## Scaling & Performance

### 1. Query Optimization

```php
class OptimizedProductService 
{
    // Eager loading to prevent N+1 queries
    public function getProductsWithRelations($limit = 20)
    {
        return (new Product())
            ->with([
                'category',
                'tags',
                'reviews' => function($query) {
                    $query->where('status', 'approved')
                          ->orderBy('rating', 'DESC')
                          ->take(5);
                },
                'featuredImage'
            ])
            ->take($limit)
            ->get();
    }
    
    // Selective field loading
    public function getProductSummaries()
    {
        return (new Product())
            ->select(['id', 'name', 'price', 'category_id'])
            ->with(['category' => function($query) {
                $query->select(['id', 'name']);
            }])
            ->get();
    }
    
    // Chunked processing for large datasets
    public function processAllProducts(callable $callback, $chunkSize = 1000)
    {
        (new Product())->chunk($chunkSize, function($products) use ($callback) {
            foreach ($products as $product) {
                $callback($product);
            }
        });
    }
}
```

### 2. Database Indexing Strategy

```sql
-- Indexes for common queries
ALTER TABLE products ADD INDEX idx_status (status);
ALTER TABLE products ADD INDEX idx_price (price);
ALTER TABLE products ADD INDEX idx_category_status (category_id, status);
ALTER TABLE products ADD INDEX idx_featured_status (is_featured, status);

-- Composite index for complex queries
ALTER TABLE products ADD INDEX idx_search (status, is_featured, price);

-- Full-text search index
ALTER TABLE products ADD FULLTEXT idx_search_text (name, description);
```

### 3. Testing Strategies

```php
class ProductTest extends \PHPUnit\Framework\TestCase 
{
    protected function setUp(): void
    {
        // Set up test database
        $this->product = new Product();
    }
    
    public function testCreateProduct()
    {
        $data = [
            'name' => 'Test Product',
            'price' => 29.99,
            'category_id' => 1,
            'description' => 'A test product'
        ];
        
        $product = $this->product->create($data);
        
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(29.99, $product->price);
    }
    
    public function testProductRelationships()
    {
        $product = $this->product->findById(1);
        
        // Test relationship loading
        $category = $product->category;
        $this->assertInstanceOf(Category::class, $category);
        
        $reviews = $product->reviews;
        $this->assertIsIterable($reviews);
    }
    
    public function testQueryBuilder()
    {
        $products = $this->product
            ->where('price', '>', 20)
            ->where('status', 'active')
            ->orderBy('name')
            ->take(10)
            ->get();
            
        $this->assertLessThanOrEqual(10, count($products));
        
        foreach ($products as $product) {
            $this->assertGreaterThan(20, $product->price);
            $this->assertEquals('active', $product->status);
        }
    }
}
```

---

## Comparison with Other ORMs

### TypeRocket vs Eloquent

| Feature | TypeRocket | Eloquent |
|---------|------------|----------|
| **WordPress Integration** | ✅ Native | ❌ Requires plugins |
| **Meta Field Handling** | ✅ Built-in | ❌ Manual |
| **Query Builder** | ✅ Fluent | ✅ Fluent |
| **Relationships** | ✅ 4 types | ✅ 6+ types |
| **Caching** | ✅ WordPress cache | ❌ Manual |
| **Validation** | ⚠️ Manual | ✅ Built-in |
| **Migrations** | ❌ Manual | ✅ Built-in |
| **Events/Hooks** | ⚠️ Manual | ✅ Built-in |

### Unique TypeRocket Advantages

1. **WordPress Meta Integration**: Seamless handling of `wp_postmeta`, `wp_usermeta`, etc.
2. **Fillable/Guard Security**: Built-in mass assignment protection
3. **REST API Integration**: Automatic WordPress REST API field registration
4. **Form Integration**: Direct integration with TypeRocket's form builder
5. **Resource-Based**: Convention over configuration for WordPress post types

---

## Final Best Practices & Recommendations

### 1. **Security First**
```php
// Always define fillable fields
protected $fillable = ['name', 'description', 'price'];

// Guard sensitive fields  
protected $guard = ['id', 'created_at', 'user_id'];
```

### 2. **Performance Optimization**
```php
// Use eager loading
$products = (new Product())->with(['category', 'reviews'])->get();

// Enable caching
protected $cache = true;

// Use specific queries
$products->select(['id', 'name', 'price'])->get();
```

### 3. **Maintainable Code**
```php
// Use scopes for reusable queries
public function published() {
    return $this->where('status', 'published');
}

// Implement validation
public function validate($fields) { /* ... */ }

// Use clear property organization
protected $fillable = [/* ... */];
protected $cast = [/* ... */];
protected $format = [/* ... */];
```

### 4. **Error Handling**
```php
try {
    $product = (new Product())->create($fields);
} catch (ModelException $e) {
    // Handle model-specific errors
} catch (Exception $e) {
    // Handle general errors
}
```

TypeRocket's Model class provides a powerful, WordPress-native ORM that balances ease of use with enterprise-level features. By mastering its security model, relationship system, and performance optimizations, you can build robust, scalable WordPress applications that leverage the best of both WordPress and modern PHP development practices.