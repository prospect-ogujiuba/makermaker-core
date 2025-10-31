<?php

// Copy all helper functions from the original plugin
// These are loaded via composer.json "files" autoload

use MakerMaker\Helpers\StringHelper;

if (!function_exists('toTitleCase')) {
    function toTitleCase($string) {
        return StringHelper::toTitleCase($string);
    }
}

if (!function_exists('pluralize')) {
    function pluralize($word) {
        return StringHelper::pluralize($word);
    }
}

if (!function_exists('singularize')) {
    function singularize($word) {
        return StringHelper::singularize($word);
    }
}

// Add other helper functions as needed from original helpers.php
