<?php

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// Apply Mockery integration in these directories
uses(MockeryPHPUnitIntegration::class)->in('Unit', 'Integration', 'Feature', 'Acceptance');

// Global setup / teardown (Brain Monkey + Mockery)
uses()
    ->beforeEach(function () {
        Monkey\setUp();
    })
    ->afterEach(function () {
        Monkey\tearDown();
        Mockery::close();
    })
    ->in('Unit', 'Integration', 'Feature', 'Acceptance');

// ===== Auto-assign PHPUnit groups based on directory =====
uses()->group('unit')->in('Unit');
uses()->group('integration')->in('Integration');
uses()->group('feature')->in('Feature');
uses()->group('acceptance')->in('Acceptance');

// Custom expectations for WordPress functions
expect()->extend('toCallWordPressFunction', function (string $function) {
    Brain\Monkey\Functions\expect($function);
    return $this;
});

// Helper to mock WordPress actions
expect()->extend('toHaveWordPressAction', function (string $action) {
    Brain\Monkey\Actions\expectAdded($action);
    return $this;
});

// Helper to mock WordPress filters
expect()->extend('toHaveWordPressFilter', function (string $filter) {
    Brain\Monkey\Filters\expectAdded($filter);
    return $this;
});
