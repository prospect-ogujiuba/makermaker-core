<?php

namespace MakerMaker\Commands;

use TypeRocket\Console\Command;

class MakeClient extends Command
{
    protected $command = [
        'maker:client {name} {?--slug=} {?--org=mxcro} {?--author=} {?--description=} {?--path=}',
        'Scaffold a new MakerMaker client plugin',
        'Creates a thin client plugin that depends on makermaker-core'
    ];

    public function exec()
    {
        $name = $this->getArgument('name');
        $slug = $this->getOption('slug') ?: $this->toSlug($name);
        $org = $this->getOption('org') ?: 'mxcro';
        $author = $this->getOption('author') ?: 'Your Name';
        $description = $this->getOption('description') ?: "{$name} Client Plugin";
        $basePath = $this->getOption('path') ?: getcwd();

        if (!$name) {
            $this->error('Name argument is required');
            return;
        }

        $pluginPath = $basePath . '/' . $slug;

        if (file_exists($pluginPath)) {
            $this->error("Plugin directory already exists: {$pluginPath}");
            return;
        }

        $this->info("Creating client plugin: {$name}");
        $this->info("Slug: {$slug}");
        $this->info("Organization: {$org}");
        $this->info("Path: {$pluginPath}");

        // Create directory structure
        $this->createDirectoryStructure($pluginPath);

        // Generate files from boilerplate
        $namespace = $this->toNamespace($slug);
        $constantPrefix = strtoupper(str_replace('-', '_', $slug));

        $replacements = [
            '{{name}}' => $name,
            '{{slug}}' => $slug,
            '{{org}}' => $org,
            '{{author}}' => $author,
            '{{description}}' => $description,
            '{{namespace}}' => $namespace,
            '{{constant_prefix}}' => $constantPrefix,
        ];

        $this->copyBoilerplateFiles($pluginPath, $replacements);

        $this->line('');
        $this->success("✓ Client plugin created: {$pluginPath}");
        $this->line('');
        $this->info('Next steps:');
        $this->line("1. cd {$slug}");
        $this->line('2. composer install');
        $this->line('3. Activate the plugin in WordPress');
        $this->line('4. Start adding modules and resources');
    }

    protected function createDirectoryStructure($basePath)
    {
        $dirs = [
            'config',
            'modules',
            'src/Providers',
            'inc/resources',
            'resources/views',
        ];

        foreach ($dirs as $dir) {
            $path = $basePath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    protected function copyBoilerplateFiles($pluginPath, $replacements)
    {
        $boilerplatePath = __DIR__ . '/../../resources/boilerplates/makermaker';

        $files = [
            'composer.json',
            'plugin.php',
            'config/makermaker.php',
            'src/Providers/ClientServiceProvider.php',
        ];

        foreach ($files as $file) {
            $source = $boilerplatePath . '/' . $file;
            $dest = $pluginPath . '/' . $file;

            if (!file_exists($source)) {
                $this->warning("Boilerplate file not found: {$file}");
                continue;
            }

            $content = file_get_contents($source);
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);

            file_put_contents($dest, $content);
            $this->line("Created: {$file}");
        }

        // Create .gitignore
        file_put_contents($pluginPath . '/.gitignore', "vendor/\n.env\n");
    }

    protected function toSlug($name)
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
    }

    protected function toNamespace($slug)
    {
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts);
    }
}
