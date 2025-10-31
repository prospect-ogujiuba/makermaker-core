<?php

namespace MakerMaker\Helpers;

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
     * @param string $resource Resource name
     * @return void
     */
    public static function renderAdvancedSearchActions(string $resource): void
    {
        ?>
        <div class="tr-search-actions">
            <div>
                <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>?page=<?= $resource ?>_index" class="button">Reset Filters</a>
                <button type="submit" class="button">Search</button>
            </div>
        </div>

        <input type="checkbox" id="search-toggle" class="search-toggle-input">
        <label for="search-toggle" class="button">Toggle Advanced Search</label>
        <?php
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