<?php

namespace MakerMaker\Commands;

use TypeRocket\Console\Command;
use TypeRocket\Utility\File;

class Crud extends Command
{
	protected $command = [
		'make:crud {name} {?--module=} {?--template=standard} {?--force} {?-o|--only=} {?-s|--skip=}',
		'Generate a complete CRUD setup with Model, Controller, Policy, Fields, Views, and Resource',
		'Options: --module=shop, --template=simple|standard|api-ready, --only=list, --skip=list',
	];

	public function exec()
	{
		$name      = $this->getArgument('name');
		$module    = $this->getOption('module');
		$template  = $this->getOption('template') ?: 'standard';
		$force     = (bool) $this->getOption('force');
		$onlyOpt  = $this->getOption('only');
		$skipOpt = $this->getOption('skip');

		if (!$name) {
			$this->error('Name argument is required');
			return;
		}

		$validTemplates = ['simple', 'standard', 'api-ready'];
		if (!in_array($template, $validTemplates)) {
			$this->error("Invalid template: {$template}. Valid options: " . implode(', ', $validTemplates));
			return;
		}

		// Case transforms
		$pascalCase       = $this->toPascalCase($name);
		$snakeCase        = $this->toSnakeCase($name);
		$pluralSnakeCase  = $this->pluralize($snakeCase);
		$variable         = lcfirst($pascalCase);
		$pluralVariable   = $this->pluralize($variable);
		$pluralClass      = $this->toPascalCase($pluralSnakeCase);
		$pluralTitle      = ucwords(str_replace('_', ' ', $pluralSnakeCase));
		$titleCase        = $this->toTitleCase($pascalCase);
		$pluralTitleCase  = $this->toTitleCase($pluralClass);

		$this->info("Generating CRUD files for: {$pascalCase}");
		$this->info("Template variant: {$template}");
		$this->info("Table name: {$pluralSnakeCase}");

		// Get plugin directory
		$plugin_dir = $this->getPluginDirectory();

		if ($module) {
			$base_path = $plugin_dir . "/modules/{$module}";
			$this->info("Module: {$module}");
			$this->ensureModuleStructure($base_path, $module);
			$appNamespace = $this->getGalaxyMakeNamespace() . '\\Modules\\' . $this->toPascalCase($module);
		} else {
			$base_path   = $plugin_dir . '/app';
			$appNamespace = $this->getGalaxyMakeNamespace();
		}

		// Parse selection flags
		$only    = $this->parseListOption($onlyOpt);
		$exclude = $this->parseListOption($skipOpt);

		// Define all steps
		$allSteps = [
			'migration'   => fn() => $this->generateMigration($pascalCase, $pluralSnakeCase, $force, $template, $plugin_dir),
			'model'       => fn() => $this->generateModel($pascalCase, $pluralSnakeCase, $appNamespace, $base_path, $force, $template),
			'policy'      => fn() => $this->generatePolicy($pascalCase, $snakeCase, $appNamespace, $base_path, $force, $template),
			'fields'      => fn() => $this->generateFields($pascalCase, $pluralSnakeCase, $appNamespace, $base_path, $force, $template),
			'controller'  => fn() => $this->generateController($pascalCase, $variable, $pluralVariable, $pluralSnakeCase, $appNamespace, $base_path, $force, $template),
			'views:index' => fn() => $this->generateViews($pluralSnakeCase, $pascalCase, $pluralClass, $variable, $titleCase, $pluralTitleCase, $appNamespace, $module, $force, $template, 'index', $plugin_dir),
			'views:form'  => fn() => $this->generateViews($pluralSnakeCase, $pascalCase, $pluralClass, $variable, $titleCase, $pluralTitleCase, $appNamespace, $module, $force, $template, 'form', $plugin_dir),
			'resource'    => fn() => $this->generateResourceFile($pascalCase, $variable, $snakeCase, $pluralTitle, $module, $force, $template, $plugin_dir),
			'register'    => function () use ($module, $pascalCase, $snakeCase, $plugin_dir) {
				if ($module) {
					$this->updateModuleMetadata($module, $pascalCase, $snakeCase, $plugin_dir);
					return "modules/{$module}/module.json";
				}
			},
		];

		// Work out which to run
		$runKeys = $this->resolveStepsToRun($allSteps, $only, $exclude);

		$results = [];
		foreach ($runKeys as $key) {
			try {
				$out = $allSteps[$key]();
				if (is_array($out)) {
					foreach ($out as $f) {
						if ($f) $results[$key][] = $f;
					}
				} elseif ($out) {
					$results[$key] = $out;
				}
			} catch (\Throwable $e) {
				$this->warning("Skipped {$key}: " . $e->getMessage());
			}
		}

		// Summary
		$this->line('');
		$this->success('✓ CRUD generation completed!');
		$this->line('');
		if (!empty($results)) {
			$this->info('Generated/updated:');
			foreach ($results as $type => $files) {
				if (is_array($files)) {
					foreach ($files as $file) {
						$this->line("  - {$type}: {$file}");
					}
				} else {
					$this->line("  - {$type}: {$files}");
				}
			}
		} else {
			$this->warning('No files changed (possibly all skipped or excluded).');
		}

		$this->line('');
		$this->info('Template variant: ' . $template);
		if ($module) {
			$this->info('Module: ' . $module);
		}
		$this->line('');
		$this->info('Next steps:');
		$this->line('1. Run migrations: php galaxy migrate:up');
		$this->line('2. Customize the generated files as needed');
		$this->line('3. Add fields to migration, model fillable, and form view');
		if ($template === 'api-ready') {
			$this->line('4. Configure REST routes in your routes file');
		}
	}

	/**
	 * Get the path to a template file
	 * Looks in vendor directory first, then falls back to local templates
	 */
	protected function getTemplatePath($templateName, $variant = 'standard')
	{
		// Build list of possible template locations in priority order
		$searchPaths = [];
		
		// 1. First check vendor directory (installed via Composer)
		$vendorPaths = $this->getPossibleVendorPaths();
		foreach ($vendorPaths as $vendorPath) {
			if (is_dir($vendorPath . '/mxcro/makermaker-core')) {
				// Try variant-specific template
				$searchPaths[] = $vendorPath . '/mxcro/makermaker-core/resources/templates/' . $variant . '/' . $templateName;
				// Fallback to standard variant in vendor
				if ($variant !== 'standard') {
					$searchPaths[] = $vendorPath . '/mxcro/makermaker-core/resources/templates/standard/' . $templateName;
				}
			}
		}
		
		// 2. Check plugin's local templates (for customization)
		$plugin_dir = $this->getPluginDirectory();
		$searchPaths[] = $plugin_dir . '/resources/templates/' . $variant . '/' . $templateName;
		if ($variant !== 'standard') {
			$searchPaths[] = $plugin_dir . '/resources/templates/standard/' . $templateName;
		}
		
		// 3. Fallback to relative path from this file (development mode)
		$searchPaths[] = __DIR__ . '/../../resources/templates/' . $variant . '/' . $templateName;
		if ($variant !== 'standard') {
			$searchPaths[] = __DIR__ . '/../../resources/templates/standard/' . $templateName;
		}
		
		// Search for the template
		foreach ($searchPaths as $path) {
			if (file_exists($path)) {
				return $path;
			}
		}
		
		// Template not found - provide helpful error message
		throw new \Exception(
			"Template not found: {$templateName} (variant: {$variant})\n" .
			"Ensure makermaker-core is installed via composer or templates exist locally."
		);
	}

	protected function ensureModuleStructure($path, $module)
	{
		$dirs = ['Controllers', 'Models', 'Auth', 'Http/Fields', 'resources/views'];
		foreach ($dirs as $dir) {
			$full_path = "{$path}/{$dir}";
			if (!is_dir($full_path)) {
				mkdir($full_path, 0755, true);
				$this->info("Created directory: modules/{$module}/{$dir}");
			}
		}
	}

	protected function updateModuleMetadata($module, $pascalCase, $snakeCase, $plugin_dir)
	{
		$metadataFile = $plugin_dir . "/modules/{$module}/module.json";

		if (file_exists($metadataFile)) {
			$metadata = json_decode(file_get_contents($metadataFile), true);
		} else {
			$metadata = [
				'name' => ucwords(str_replace(['-', '_'], ' ', $module)),
				'slug' => $module,
				'namespace' => $this->getGalaxyMakeNamespace() . '\\Modules\\' . $this->toPascalCase($module),
				'description' => 'Module description',
				'version' => '1.0.0',
				'author' => 'Your Name',
				'active' => true,
				'dependencies' => [],
				'resources' => [],
				'capabilities' => []
			];
		}

		// Add resource
		$resource = [
			'name' => $pascalCase,
			'type' => 'crud',
			'table' => $this->pluralize($snakeCase)
		];
		
		$metadata['resources'][] = $resource;
		
		// Add capability
		$capability = 'manage_' . $this->pluralize($snakeCase);
		if (!in_array($capability, $metadata['capabilities'])) {
			$metadata['capabilities'][] = $capability;
		}

		file_put_contents(
			$metadataFile,
			json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);

		$this->success("Updated module metadata: modules/{$module}/module.json");
	}

	protected function generateResourceFile($className, $variable, $snakeCase, $pluralTitle, $module = null, $force = false, $template = 'standard', $plugin_dir = null)
	{
		if ($module) {
			$resourceFile = $plugin_dir . "/modules/{$module}/resources/{$snakeCase}.php";
			$resourcesDir = $plugin_dir . "/modules/{$module}/resources";
		} else {
			$resourceFile = $plugin_dir . '/inc/resources/' . $snakeCase . '.php';
			$resourcesDir = $plugin_dir . '/inc/resources';
		}

		if (!is_dir($resourcesDir)) {
			mkdir($resourcesDir, 0755, true);
		}

		if ($this->skipIfExists($resourceFile, $force, 'Resource')) {
			return null;
		}

		$pluralSnakeCase = $this->pluralize($snakeCase);

		$tags = ['{{class}}', '{{singular}}', '{{variable}}', '{{plural_title}}', '{{plural_snake}}'];
		$replacements = [$className, $snakeCase, $variable, $pluralTitle, $pluralSnakeCase];

		$templatePath = $this->getTemplatePath('Resource.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($resourceFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Resource file");
		}

		return $module
			? "modules/{$module}/resources/{$snakeCase}.php"
			: "inc/resources/{$snakeCase}.php";
	}

	protected function generateMigration($className, $tableName, $force = false, $template = 'standard', $plugin_dir = null)
	{
		$migrationName = "create_{$tableName}_table";
		$timestamp = time();
		$fileName = "{$timestamp}.{$migrationName}.sql";

		$root = $plugin_dir . '/database/migrations';
		if (!file_exists($root)) {
			mkdir($root, 0755, true);
		}

		$migrationFile = $root . '/' . $fileName;

		if ($this->skipIfExists($migrationFile, $force, 'Migration')) {
			return null;
		}

		$tags = ['{{table_name}}', '{{description}}', '{{comment}}'];
		$replacements = [
			$tableName,
			"Create {$tableName} table",
			ucfirst($className) . ' table'
		];

		$templatePath = $this->getTemplatePath('Migration.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($migrationFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Migration");
		}

		return "database/migrations/{$fileName}";
	}

	protected function generateModel($className, $tableName, $appNamespace, $basePath, $force = false, $template = 'standard')
	{
		$modelsDir = $basePath . '/Models';
		if (!is_dir($modelsDir)) {
			mkdir($modelsDir, 0755, true);
		}

		$modelFile = $modelsDir . '/' . $className . '.php';

		if ($this->skipIfExists($modelFile, $force, 'Model')) {
			return null;
		}

		$tags = ['{{namespace}}', '{{class}}', '{{table_name}}'];
		$replacements = [
			$appNamespace . '\\Models',
			$className,
			$tableName
		];

		$templatePath = $this->getTemplatePath('Model.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($modelFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Model");
		}

		return str_replace($this->getPluginDirectory() . '/', '', $modelFile);
	}

	protected function generatePolicy($className, $snakeCase, $appNamespace, $basePath, $force = false, $template = 'standard')
	{
		$policyName = $className . 'Policy';
		$authDir = $basePath . '/Auth';

		if (!is_dir($authDir)) {
			mkdir($authDir, 0755, true);
		}

		$policyFile = $authDir . '/' . $policyName . '.php';

		if ($this->skipIfExists($policyFile, $force, 'Policy')) {
			return null;
		}

		$capability = $this->pluralize($snakeCase);

		$tags = ['{{namespace}}', '{{class}}', '{{capability}}'];
		$replacements = [
			$appNamespace . '\\Auth',
			$policyName,
			$capability
		];

		$templatePath = $this->getTemplatePath('Policy.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($policyFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Policy");
		}

		return str_replace($this->getPluginDirectory() . '/', '', $policyFile);
	}

	protected function generateFields($className, $tableName, $appNamespace, $basePath, $force = false, $template = 'standard')
	{
		$fieldsName = $className . 'Fields';
		$fieldsDir = $basePath . '/Http/Fields';

		if (!is_dir($fieldsDir)) {
			mkdir($fieldsDir, 0755, true);
		}

		$fieldsFile = $fieldsDir . '/' . $fieldsName . '.php';

		if ($this->skipIfExists($fieldsFile, $force, 'Fields')) {
			return null;
		}

		$tags = ['{{namespace}}', '{{class}}', '{{table_name}}'];
		$replacements = [
			$appNamespace . '\\Http\\Fields',
			$fieldsName,
			$tableName
		];

		$templatePath = $this->getTemplatePath('Fields.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($fieldsFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Fields");
		}

		return str_replace($this->getPluginDirectory() . '/', '', $fieldsFile);
	}

	protected function generateController($className, $variable, $pluralVariable, $viewPath, $appNamespace, $basePath, $force = false, $template = 'standard')
	{
		$controllerName = $className . 'Controller';
		$controllersDir = $basePath . '/Controllers';

		if (!is_dir($controllersDir)) {
			mkdir($controllersDir, 0755, true);
		}

		$controllerFile = $controllersDir . '/' . $controllerName . '.php';

		if ($this->skipIfExists($controllerFile, $force, 'Controller')) {
			return null;
		}

		$routeName = $this->toSnakeCase($className);

		$tags = [
			'{{namespace}}',
			'{{class}}',
			'{{variable}}',
			'{{plural_variable}}',
			'{{view_path}}',
			'{{route_name}}',
			'{{app_namespace}}'
		];
		$replacements = [
			$appNamespace . '\\Controllers',
			$className,
			$variable,
			$pluralVariable,
			$viewPath,
			$routeName,
			$appNamespace
		];

		$templatePath = $this->getTemplatePath('Controller.txt', $template);
		$file = new File($templatePath);
		$result = $file->copyTemplateFile($controllerFile, $tags, $replacements);

		if (!$result) {
			throw new \Exception("Failed to generate Controller");
		}

		return str_replace($this->getPluginDirectory() . '/', '', $controllerFile);
	}

	protected function generateViews($viewPath, $className, $pluralClass, $variable, $titleCase, $pluralTitleCase, $appNamespace, $module = null, $force = false, $template = 'standard', $which = null, $plugin_dir = null)
	{
		if ($module) {
			$viewsDir = $plugin_dir . "/modules/{$module}/resources/views/{$viewPath}";
		} else {
			$viewsDir = $plugin_dir . "/resources/views/{$viewPath}";
		}

		if (!is_dir($viewsDir)) {
			mkdir($viewsDir, 0755, true);
		}

		$indexFile = "{$viewsDir}/index.php";
		$formFile  = "{$viewsDir}/form.php";
		$doIndex = ($which === null || $which === 'index');
		$doForm  = ($which === null || $which === 'form');

		$generatedFiles = [];

		// Generate index view
		if ($doIndex && !$this->skipIfExists($indexFile, $force, 'View (index)')) {
			$tags = ['{{class}}', '{{app_namespace}}', '{{title_class}}'];
			$replacements = [$className, $appNamespace, $titleCase];

			$templatePath = $this->getTemplatePath('ViewIndex.txt', $template);
			$file = new File($templatePath);
			$result = $file->copyTemplateFile($indexFile, $tags, $replacements);
			if (!$result) {
				throw new \Exception("Failed to generate Index view");
			}

			$generatedFiles[] = str_replace($plugin_dir . '/', '', $indexFile);
		}

		// Generate form view
		if ($doForm && !$this->skipIfExists($formFile, $force, 'View (form)')) {
			$tags = [
				'{{class}}',
				'{{plural_class}}',
				'{{variable}}',
				'{{app_namespace}}',
				'{{title_class}}',
				'{{title_plural}}'
			];
			$replacements = [
				$className,
				$pluralClass,
				$variable,
				$appNamespace,
				$titleCase,
				$pluralTitleCase
			];

			$templatePath = $this->getTemplatePath('ViewForm.txt', $template);
			$file = new File($templatePath);
			$result = $file->copyTemplateFile($formFile, $tags, $replacements);
			if (!$result) {
				throw new \Exception("Failed to generate Form view");
			}

			$generatedFiles[] = str_replace($plugin_dir . '/', '', $formFile);
		}

		return $generatedFiles;
	}

	/**
	 * Get possible vendor directory paths
	 */
	protected function getPossibleVendorPaths()
	{
		$paths = [];
		
		// Check common vendor locations
		$checkPaths = [
			getcwd() . '/vendor',
			dirname(getcwd()) . '/vendor',
			__DIR__ . '/../../../../vendor', // If this command is in vendor
			__DIR__ . '/../../../vendor',    // Alternative vendor location
		];
		
		// Add plugin-specific vendor path
		if (defined('MAKERMAKER_PLUGIN_DIR')) {
			$checkPaths[] = MAKERMAKER_PLUGIN_DIR . '/vendor';
		}
		
		// Add WordPress plugin paths
		if (defined('WP_PLUGIN_DIR')) {
			$checkPaths[] = WP_PLUGIN_DIR . '/makermaker/vendor';
		}
		
		// Filter to only existing directories
		foreach ($checkPaths as $path) {
			if (is_dir($path)) {
				$paths[] = realpath($path);
			}
		}
		
		return array_unique($paths);
	}

	/**
	 * Get the plugin directory
	 */
	protected function getPluginDirectory()
	{
		// Try different ways to get plugin directory
		if (defined('MAKERMAKER_PLUGIN_DIR')) {
			return MAKERMAKER_PLUGIN_DIR;
		}
		
		if (class_exists('\MakerMaker\Boot')) {
			$paths = \MakerMaker\Boot::getPaths();
			if (!empty($paths['plugin_dir'])) {
				return $paths['plugin_dir'];
			}
		}
		
		// Fallback to current working directory
		return getcwd();
	}

	/**
	 * Helper: Convert to PascalCase
	 */
	protected function toPascalCase($string)
	{
		return str_replace(' ', '', ucwords(str_replace(array('_', '-'), ' ', $string)));
	}

	/**
	 * Helper: Convert to snake_case
	 */
	protected function toSnakeCase($string)
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
	}

	/**
	 * Helper: Convert to Title Case
	 */
	protected function toTitleCase($string)
	{
		// Handle PascalCase
		if (strpos($string, '_') === false && strpos($string, ' ') === false) {
			$result = preg_replace('/(?<!^)([A-Z])/', ' $1', $string);
			return trim($result);
		}
		
		return ucwords(str_replace('_', ' ', $string));
	}

	/**
	 * Helper: Pluralize a word
	 */
	protected function pluralize($word)
	{
		// Use TypeRocket's Inflect if available
		if (class_exists('\TypeRocket\Utility\Inflect')) {
			return \TypeRocket\Utility\Inflect::pluralize($word);
		}
		
		// Simple fallback pluralization
		if (substr($word, -1) === 'y') {
			return substr($word, 0, -1) . 'ies';
		} elseif (substr($word, -1) === 's') {
			return $word . 'es';
		} else {
			return $word . 's';
		}
	}

	/**
	 * Skip if file exists and not forcing
	 */
	protected function skipIfExists(string $path, $force, string $label): bool
	{
		if (file_exists($path) && !$force) {
			$this->warning("{$label} already exists, skipping: " . basename($path));
			return true;
		}
		return false;
	}

	/**
	 * Parse list option from command line
	 */
	protected function parseListOption($value): array
	{
		if ($value === null || $value === '') {
			return [];
		}
		$parts = preg_split('/[\s,;]+/', strtolower(trim($value)));
		return array_values(array_filter(array_map('trim', $parts)));
	}

	/**
	 * Normalize target names
	 */
	protected function normalizeTargets(array $keys): array
	{
		$expanded = [];
		foreach ($keys as $k) {
			switch ($k) {
				case 'views':
					$expanded[] = 'views:index';
					$expanded[] = 'views:form';
					break;
				default:
					$expanded[] = $k;
			}
		}
		return array_values(array_unique($expanded));
	}

	/**
	 * Resolve which steps to run based on options
	 */
	protected function resolveStepsToRun(array $allSteps, array $only, array $exclude): array
	{
		$only = $this->normalizeTargets($only);
		$exclude = $this->normalizeTargets($exclude);

		if (!empty($only)) {
			$allow = $only;
		} else {
			$allow = array_keys($allSteps);
		}

		$allow = array_values(array_diff($allow, $exclude));
		$allow = array_values(array_intersect($allow, array_keys($allSteps)));

		return $allow;
	}
}