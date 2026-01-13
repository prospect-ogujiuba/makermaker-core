<?php

namespace MakermakerCore\Helpers;

use MakermakerCore\Admin\ReflectiveFieldIntrospector;
use MakermakerCore\Admin\FieldTypeDetector;
use TypeRocket\Models\Model;

/**
 * HTML and UI generation utilities
 * 
 * Provides methods for generating HTML elements and UI components
 */
class HtmlHelper
{
    /**
     * Output HTML options for select dropdowns
     * 
     * @param array $options Array of options
     * @param mixed $currentValue Currently selected value
     * @param string|null $valueKey Key for value in associative array
     * @param string|null $labelKey Key for label in associative array
     * @return void
     */
    public static function outputSelectOptions(
        array $options,
        $currentValue,
        ?string $valueKey = null,
        ?string $labelKey = null
    ): void {
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = $option[$valueKey];
                $label = $option[$labelKey];
            } else {
                $value = is_numeric($key) ? $option : $key;
                $label = $option;
            }
            $selected = ($currentValue === $value) ? 'selected' : '';
            echo "<option value=\"" . htmlspecialchars($value) . "\" {$selected}>" .
                htmlspecialchars($label) . "</option>";
        }
    }

    /**
     * Render search actions for TypeRocket admin pages
     *
     * @param string $resource Resource name for reset URL
     * @param bool $showToggle Whether to show toggle button (default: true)
     * @param string $resetLabel Label for reset button (default: 'Reset Filters')
     * @param string $searchLabel Label for search button (default: 'Search')
     * @return void
     */
    public static function renderAdvancedSearchActions(
        string $resource,
        bool $showToggle = true,
        string $resetLabel = 'Reset Filters',
        string $searchLabel = 'Search'
    ): void {
        $resetUrl = strtok($_SERVER["REQUEST_URI"], '?') . '?page=' . $resource . '_index';
        ?>
        <div class="tr-search-actions">
            <div>
                <a href="<?= htmlspecialchars($resetUrl) ?>" class="button"><?= htmlspecialchars($resetLabel) ?></a>
                <button type="submit" class="button"><?= htmlspecialchars($searchLabel) ?></button>
            </div>
        </div>
        <?php if ($showToggle): ?>
        <input type="checkbox" id="search-toggle" class="search-toggle-input">
        <label for="search-toggle" class="button">Toggle Advanced Search</label>
        <?php endif;
    }

    /**
     * Output select options from a related model
     *
     * @param string $modelClass Full class name of the model
     * @param mixed $currentValue Currently selected value
     * @param string $valueField Field to use as option value (default: 'id')
     * @param string $labelField Field to use as option label (default: 'name')
     * @param string|null $orderField Field to order by (default: same as labelField)
     * @param string $orderDir Order direction (default: 'ASC')
     * @param callable|null $queryModifier Optional callback to modify query
     * @return void
     */
    public static function outputModelSelectOptions(
        string $modelClass,
        $currentValue,
        string $valueField = 'id',
        string $labelField = 'name',
        ?string $orderField = null,
        string $orderDir = 'ASC',
        ?callable $queryModifier = null
    ): void {
        $model = new $modelClass();
        $query = $model->where('deleted_at', '=', null)
            ->orderBy($orderField ?? $labelField, $orderDir);

        if ($queryModifier) {
            $queryModifier($query);
        }

        $records = $query->findAll()->get();

        foreach ($records as $record) {
            $value = $record->$valueField;
            $label = $record->$labelField ?? $record->$valueField;
            $selected = ((string)$currentValue === (string)$value) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' .
                htmlspecialchars($label) . '</option>';
        }
    }

    /**
     * Get select options from a related model as string
     *
     * @param string $modelClass Full class name of the model
     * @param mixed $currentValue Currently selected value
     * @param string $valueField Field to use as option value (default: 'id')
     * @param string $labelField Field to use as option label (default: 'name')
     * @param string|null $orderField Field to order by (default: same as labelField)
     * @param string $orderDir Order direction (default: 'ASC')
     * @param callable|null $queryModifier Optional callback to modify query
     * @return string
     */
    public static function getModelSelectOptions(
        string $modelClass,
        $currentValue,
        string $valueField = 'id',
        string $labelField = 'name',
        ?string $orderField = null,
        string $orderDir = 'ASC',
        ?callable $queryModifier = null
    ): string {
        ob_start();
        self::outputModelSelectOptions($modelClass, $currentValue, $valueField, $labelField, $orderField, $orderDir, $queryModifier);
        return ob_get_clean();
    }

    /**
     * Output select options for a foreign key field
     * Auto-detects label field (name, title, code, or id)
     *
     * @param Model $parentModel The model containing the FK field
     * @param string $fkField The foreign key field name (e.g., 'category_id')
     * @param mixed $currentValue Currently selected value
     * @return void
     */
    public static function outputForeignKeyOptions(
        Model $parentModel,
        string $fkField,
        $currentValue
    ): void {
        $introspector = new ReflectiveFieldIntrospector($parentModel);
        $typeDetector = new FieldTypeDetector($introspector);

        $relationship = $typeDetector->getForeignKeyRelationship($fkField);
        if (!$relationship || !method_exists($parentModel, $relationship)) {
            return;
        }

        // Get related model class
        $relation = $parentModel->$relationship();
        $relatedClass = get_class($relation->getRelatedModel());

        // Auto-detect label field
        $relatedModel = new $relatedClass();
        $relatedIntrospector = new ReflectiveFieldIntrospector($relatedModel);
        $fillable = $relatedIntrospector->getFillable();

        $labelField = 'id';
        foreach (['name', 'title', 'code', 'description'] as $candidate) {
            if (in_array($candidate, $fillable)) {
                $labelField = $candidate;
                break;
            }
        }

        self::outputModelSelectOptions($relatedClass, $currentValue, 'id', $labelField);
    }

    /**
     * Generate a link to a TypeRocket resource page (legacy method)
     * 
     * @deprecated Use SmartResourceHelper::link() instead
     * @param string $resource The resource name
     * @param string $action The action (index, add, edit, show, delete)
     * @param string $text The link text to display
     * @param int|null $id The resource ID
     * @param string $icon The icon class
     * @return string The complete HTML link
     */
    public static function toResourceUrl(
        string $resource,
        string $action = 'index',
        string $text = 'Back',
        ?int $id = null,
        string $icon = 'box-arrow-up-right'
    ): string {
        // Convert resource name to lowercase with underscores for TypeRocket convention
        $resource_slug = strtolower(preg_replace('/([A-Z])/', '$1', lcfirst($resource)));

        // Build the page parameter
        $page = $resource_slug . '_' . $action;

        // Start building the URL
        $url_params = ['page' => $page];

        // Add route_args for actions that need an ID
        if (in_array($action, ['edit', 'show', 'delete']) && $id !== null) {
            $url_params['route_args'] = [$id];
        }

        // Build the admin URL
        $admin_url = admin_url('admin.php?' . http_build_query($url_params));

        // Generate the HTML link
        $icon_html = $icon ? "<i class='bi bi-{$icon}'></i> " : '';

        return "<a href='{$admin_url}'>{$icon_html}{$text}</a>";
    }
}