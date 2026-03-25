<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

class PermissionReportController extends Controller
{
    public function index()
    {
        if (!$this->checkRole(['developer'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $roleMap = $this->buildRoleMap();

        return view('permissions-report.index', compact('roleMap'));
    }

    protected function buildRoleMap(): array
    {
        $controllerPath = app_path('Http/Controllers');
        $files = $this->scanPhpFiles($controllerPath);

        $roleMap = [];

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            $controllerName = pathinfo($file, PATHINFO_FILENAME);
            if ($controllerName === 'Controller') {
                continue;
            }

            foreach ($this->extractMethodBlocks($contents) as $methodName => $block) {
                $roles = $this->extractRolesFromBlock($block);
                if (empty($roles)) {
                    continue;
                }

                $actionLabel = $controllerName . '::' . $methodName;

                foreach ($roles as $role) {
                    $roleKey = Str::lower($role);
                    if (!isset($roleMap[$roleKey])) {
                        $roleMap[$roleKey] = [];
                    }
                    $roleMap[$roleKey][] = $actionLabel;
                }
            }
        }

        foreach ($roleMap as $role => $actions) {
            $roleMap[$role] = array_values(array_unique($actions));
            sort($roleMap[$role], SORT_NATURAL | SORT_FLAG_CASE);
        }

        ksort($roleMap, SORT_NATURAL | SORT_FLAG_CASE);

        return $roleMap;
    }

    protected function scanPhpFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    protected function extractMethodBlocks(string $contents): array
    {
        $methods = [];

        $pattern = '/public\\s+function\\s+(\\w+)\\s*\\([^)]*\\)\\s*\\{/m';
        if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return $methods;
        }

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $methodName = $matches[1][$i][0];
            $start = $matches[0][$i][1];
            $end = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($contents);
            $methods[$methodName] = substr($contents, $start, $end - $start);
        }

        return $methods;
    }

    protected function extractRolesFromBlock(string $block): array
    {
        $roles = [];
        $pattern = '/checkRole\\s*\\(\\s*\\[([^\\]]*)\\]\\s*\\)/m';

        if (preg_match_all($pattern, $block, $matches)) {
            foreach ($matches[1] as $roleList) {
                if (preg_match_all('/[\'"]([^\'"]+)[\'"]/', $roleList, $roleMatches)) {
                    $roles = array_merge($roles, $roleMatches[1]);
                }
            }
        }

        $roles = array_map('trim', $roles);
        $roles = array_filter($roles, fn ($role) => $role !== '');

        return array_values(array_unique($roles));
    }
}
