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
		$pluralSnakeCase  = pluralize($snakeCase);
		$variable         = lcfirst($pascalCase);
		$pluralVariable   = pluralize($variable);
		$pluralClass      = $this->toPascalCase($pluralSnakeCase);
		$pluralTitle      = ucwords(str_replace('_', ' ', $pluralSnakeCase));
		$titleCase        = toTitleCase($pascalCase);
		$pluralTitleCase  = toTitleCase($pluralClass);

		$this->info("Generating CRUD files for: {$pascalCase}");
		$this->info("Template variant: {$template}");
		$this->info("Table name: {$pluralSnakeCase}");

		// Get paths from Boot
		$paths = \MakerMaker\Boot::getPaths();
		$plugin_dir = $paths['plugin_dir'] ?? getcwd();

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

	protected function ensureModuleStructure($path, $module)
	{
		$dirs = ['Controllers', 'Models', 'Auth', 'Http/Fields', 'resources'];
		foreach ($dirs as $dir) {
			$full_path = "{$path}/{$dir}";
			if (!is_dir($full_path)) {
				mkdir($full_path, 0755, true);
				$this->info("Created directory: modules/{$module}/{$dir}");
			}
		}
	}

	protected function updateModuleMetadata($module, $pascalCase, $snakeCase, $plugin_dir, array $results = [])
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
				'author' => get_bloginfo('name') ?: 'Your Name',
				'active' => false,
				'dependencies' => [],
				'conflicts' => [],
				'files' => [
					'controllers' => [],
					'models' => [],
					'policies' => [],
					'fields' => [],
					'resources' => [],
					'migrations' => [],
					'views' => []
				],
				'autoload' => [
					'psr-4' => [
						$this->getGalaxyMakeNamespace() . '\\Modules\\' . $this->toPascalCase($module) . '\\' => '.'
					]
				],
				'capabilities' => []
			];
		}

		// Add files from generation results
		foreach ($results as $type => $files) {
			if (!isset($metadata['files'][$type])) {
				continue;
			}

			if (is_array($files)) {
				foreach ($files as $file) {
					$filename = basename($file);
					if (!in_array($filename, $metadata['files'][$type])) {
						$metadata['files'][$type][] = $filename;
					}
				}
			} else {
				$filename = basename($files);
				if (!in_array($filename, $metadata['files'][$type])) {
					$metadata['files'][$type][] = $filename;
				}
			}
		}

		// Add controller
		$controllerFile = "{$pascalCase}Controller.php";
		if (!in_array($controllerFile, $metadata['files']['controllers'])) {
			$metadata['files']['controllers'][] = $controllerFile;
		}

		// Add model
		$modelFile = "{$pascalCase}.php";
		if (!in_array($modelFile, $metadata['files']['models'])) {
			$metadata['files']['models'][] = $modelFile;
		}

		// Add policy
		$policyFile = "{$pascalCase}Policy.php";
		if (!in_array($policyFile, $metadata['files']['policies'])) {
			$metadata['files']['policies'][] = $policyFile;
		}

		// Add fields
		$fieldsFile = "{$pascalCase}Fields.php";
		if (!in_array($fieldsFile, $metadata['files']['fields'])) {
			$metadata['files']['fields'][] = $fieldsFile;
		}

		// Add resource
		$resourceFile = "{$snakeCase}.php";
		if (!in_array($resourceFile, $metadata['files']['resources'])) {
			$metadata['files']['resources'][] = $resourceFile;
		}

		// Add capability
		$capability = 'manage_' . pluralize($snakeCase);
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
			return $module
				? "modules/{$module}/resources/{$snakeCase}.php"
				: "inc/resources/{$snakeCase}.php";
		}

		$pluralSnakeCase = pluralize($snakeCase);

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

		$root = \TypeRocket\Core\Config::get('paths.migrations');
		if (!file_exists($root)) {
			mkdir($root, 0755, true);
		}

		$migrationFile = $root . '/' . $fileName;

		if ($this->skipIfExists($migrationFile, $force, 'Migration')) {
			return "database/migrations/" . basename($migrationFile);
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
			return basename(dirname(dirname($modelFile))) . '/' . basename(dirname($modelFile)) . '/' . basename($modelFile);
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

		return basename(dirname(dirname($modelFile))) . '/' . basename(dirname($modelFile)) . '/' . basename($modelFile);
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
			return basename(dirname(dirname($policyFile))) . '/' . basename(dirname($policyFile)) . '/' . basename($policyFile);
		}

		$capability = pluralize($snakeCase);

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

		return basename(dirname(dirname($policyFile))) . '/' . basename(dirname($policyFile)) . '/' . basename($policyFile);
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
			return basename(dirname(dirname(dirname($fieldsFile)))) . '/' . basename(dirname(dirname($fieldsFile))) . '/' . basename(dirname($fieldsFile)) . '/' . basename($fieldsFile);
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

		return basename(dirname(dirname(dirname($fieldsFile)))) . '/' . basename(dirname(dirname($fieldsFile))) . '/' . basename(dirname($fieldsFile)) . '/' . basename($fieldsFile);
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
			return basename(dirname(dirname($controllerFile))) . '/' . basename(dirname($controllerFile)) . '/' . basename($controllerFile);
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

		return basename(dirname(dirname($controllerFile))) . '/' . basename(dirname($controllerFile)) . '/' . basename($controllerFile);
	}

	protected function generateViews($viewPath, $className, $pluralClass, $variable, $titleCase, $pluralTitleCase, $appNamespace, $module = null, $force = false, $template = 'standard', $which = null, $plugin_dir = null)
	{
		if ($module) {
			$viewsDir = $plugin_dir . "/modules/{$module}/resources/views/{$viewPath}";
		} else {
			$paths = \MakerMaker\Boot::getPaths();
			$viewsPath = $paths['views_path'] ?? $plugin_dir . '/resources/views';
			$viewsDir = "{$viewsPath}/{$viewPath}";
		}

		if (!is_dir($viewsDir)) {
			mkdir($viewsDir, 0755, true);
		}

		$indexFile = "{$viewsDir}/index.php";
		$formFile  = "{$viewsDir}/form.php";
		$doIndex = ($which === null || $which === 'index');
		$doForm  = ($which === null || $which === 'form');

		$generatedFiles = [];

		// index
		if ($doIndex && !$this->skipIfExists($indexFile, $force, 'View (index)')) {
			$tags = ['{{class}}', '{{app_namespace}}', '{{title_class}}'];
			$replacements = [$className, $appNamespace, $titleCase];

			$templatePath = $this->getTemplatePath('ViewIndex.txt', $template);
			$file = new File($templatePath);
			$result = $file->copyTemplateFile($indexFile, $tags, $replacements);
			if (!$result) {
				throw new \Exception("Failed to generate Index view");
			}

			$generatedFiles[] = 'views/' . $viewPath . '/index.php';
		}

		// form
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

			$generatedFiles[] = 'views/' . $viewPath . '/form.php';
		}

		return $generatedFiles;
	}

	protected function getTemplatePath($templateName, $variant = 'standard')
	{
		// Try core library templates first
		$config = defined('MAKERMAKER_CONFIG') ? MAKERMAKER_CONFIG : [];
		$corePath = $config['paths']['templates'] ?? __DIR__ . '/../../resources/templates';
		
		$template_path = $corePath . "/{$variant}/{$templateName}";

		if (!file_exists($template_path)) {
			// Fallback to standard
			$template_path = $corePath . "/standard/{$templateName}";
		}

		if (!file_exists($template_path)) {
			throw new \Exception("Template not found: {$templateName}");
		}

		return $template_path;
	}

	protected function toPascalCase($string)
	{
		return str_replace(' ', '', ucwords(str_replace(array('_', '-'), ' ', $string)));
	}

	protected function toSnakeCase($string)
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
	}

	protected function skipIfExists(string $path, $force, string $label): bool
	{
		$force = (bool) $force;
		if (file_exists($path) && !$force) {
			$this->warning("{$label} already exists, skipping: " . basename($path));
			return true;
		}
		return false;
	}

	protected function parseListOption($value): array
	{
		if ($value === null || $value === '') {
			return [];
		}
		$parts = preg_split('/[\s,;]+/', strtolower(trim($value)));
		return array_values(array_filter(array_map('trim', $parts)));
	}

	protected function normalizeTargets(array $keys): array
	{
		$expanded = [];
		foreach ($keys as $k) {
			switch ($k) {
				case 'views':
					$expanded[] = 'views:index';
					$expanded[] = 'views:form';
					break;
				case 'except':
					break;
				default:
					$expanded[] = $k;
			}
		}
		return array_values(array_unique($expanded));
	}

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
