<?php

namespace Puneetxp\CompilePhp\Class;

class pythonset {

    private array $routers = [];

    public function __construct(private array $table, private array $json) {}

    public function pythonset(): void {
        index::templatecopy("python", "python");
        $this->bootstrapPackages();

        foreach ($this->table as $table) {
            $this->writeModel($table);
            $this->writeService($table);
            $this->generateRouters($table);
        }

        $this->writeRouterRegistry();
    }

    private function bootstrapPackages(): void {
        $packages = [
            $_ENV['dir'] . '/python',
            $_ENV['dir'] . '/python/app',
            $_ENV['dir'] . '/python/app/api',
            $_ENV['dir'] . '/python/app/api/roles',
            $_ENV['dir'] . '/python/app/models',
            $_ENV['dir'] . '/python/app/services',
        ];

        foreach ($packages as $package) {
            $this->ensurePackage($package);
        }
    }

    private function ensurePackage(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $init = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '__init__.py';
        if (!is_file($init)) {
            file_put_contents($init, "");
        }
    }

    private function writeModel(array $table): void {
        $className = $this->studly($table['name']);
        $filePath = $_ENV['dir'] . '/python/app/models/' . $this->snake($table['name']) . '.py';

        $fields = [];
        $needsOptional = false;
        $needsAny = false;
        $needsDict = false;
        $needsDatetime = false;

        foreach ($table['data'] as $column) {
            $typeInfo = $this->pythonType($column['datatype'] ?? 'string');
            $typeHint = $typeInfo['type'];
            $needsAny = $needsAny || $typeInfo['needsAny'];
            $needsDict = $needsDict || $typeInfo['needsDict'];
            $needsDatetime = $needsDatetime || $typeInfo['needsDatetime'];

            $isOptional = !$this->isColumnRequired($column);
            $needsOptional = $needsOptional || $isOptional;

            if ($isOptional) {
                $typeHint .= ' | None';
            }

            $default = $isOptional ? ' = None' : '';
            $fields[] = '    ' . $this->snake($column['name']) . ': ' . $typeHint . $default;
        }

        $imports = ['from __future__ import annotations', 'from pydantic import BaseModel'];
        $typing = [];
        if ($needsOptional) {
            $typing[] = 'Optional';
        }
        if ($needsAny) {
            $typing[] = 'Any';
        }
        if ($needsDict) {
            $typing[] = 'Dict';
        }
        if (!empty($typing)) {
            $imports[] = 'from typing import ' . implode(', ', array_unique($typing));
        }
        if ($needsDatetime) {
            $imports[] = 'from datetime import datetime';
        }

        $content = implode("\n", $imports) . "\n\n\nclass $className(BaseModel):\n" . ($fields ? implode("\n", $fields) : '    pass') . "\n";

        index::createfile($filePath, $content);
    }

    private function writeService(array $table): void {
        $className = $this->studly($table['name']) . 'Service';
        $filePath = $_ENV['dir'] . '/python/app/services/' . $this->snake($table['name']) . '_service.py';
        $tableName = $table['table'];

        $content = implode("\n", [
            'from __future__ import annotations',
            '',
            'from the_python import ModelService',
            '',
            '',
            "class $className(ModelService):",
            '    def __init__(self) -> None:',
            "        super().__init__(table=\"$tableName\")",
            '',
            '_service = ' . $className . '()',
            '',
            'def get_service() -> ' . $className . ':',
            '    return _service',
            '',
        ]);

        index::createfile($filePath, $content);
    }

    private function generateRouters(array $table): void {
        if (!isset($table['crud'])) {
            return;
        }

        $crud = $table['crud'];

        if (isset($crud['roles']) && is_array($crud['roles'])) {
            foreach ($crud['roles'] as $role => $operations) {
                $this->writeRouter($table, $operations, $role, true);
            }
        }

        $scopes = ['isuper', 'islogin', 'ipublic', 'public'];
        foreach ($scopes as $scope) {
            if (isset($crud[$scope]) && is_array($crud[$scope])) {
                $this->writeRouter($table, $crud[$scope], $scope);
            }
        }
    }

    private function writeRouter(array $table, array $operations, string $scope, bool $isCustomRole = false): void {
        $operations = array_values(array_unique($operations));
        if (empty($operations)) {
            return;
        }

        $normalizedScope = $scope === 'public' ? 'ipublic' : $scope;
        $tableSlug = $this->slug($table['name']);
        $tableSnake = $this->snake($table['name']);
        $scopeSlug = $this->slug($normalizedScope);
        $moduleSegments = $isCustomRole ? ['roles', $scopeSlug, $tableSlug] : [$scopeSlug, $tableSlug];
        $modulePath = implode('.', $moduleSegments);
        $dirPath = $_ENV['dir'] . '/python/app/api/' . implode('/', $moduleSegments);

        $this->ensurePackage($dirPath);

        $filePath = $dirPath . '/__init__.py';
        $modelClass = $this->studly($table['name']);
        $serviceImport = 'from app.services.' . $tableSnake . '_service import get_service';
        $modelImport = 'from app.models.' . $tableSnake . ' import ' . $modelClass;

        $typingImports = [];
        $needsList = in_array('a', $operations) || in_array('w', $operations);
        $needsDict = in_array('w', $operations) || in_array('d', $operations);
        $needsAny = in_array('w', $operations);

        if ($needsList) {
            $typingImports[] = 'List';
        }
        if ($needsDict) {
            $typingImports[] = 'Dict';
        }
        if ($needsAny) {
            $typingImports[] = 'Any';
        }

        $imports = ['from __future__ import annotations', 'from fastapi import APIRouter, HTTPException', $modelImport, $serviceImport];
        if (!empty($typingImports)) {
            $imports[] = 'from typing import ' . implode(', ', array_unique($typingImports));
        }

        $functionSuffix = $scopeSlug . '_' . $tableSlug;
        $prefix = '/' . $scopeSlug . '/' . $tableSlug;

        $methods = $this->routerMethods($table, $operations, $functionSuffix, $modelClass);

        $content = implode("\n", $imports) . "\n\n\nrouter = APIRouter(prefix=\"$prefix\", tags=[\"$scopeSlug-$tableSlug\"])\nservice = get_service()\n\n" . $methods;

        index::createfile($filePath, $content);

        $alias = $scopeSlug . '_' . $tableSlug . '_router';
        $this->routers[] = [
            'import' => 'from app.api.' . $modulePath . ' import router as ' . $alias,
            'alias' => $alias,
        ];
    }

    private function routerMethods(array $table, array $operations, string $suffix, string $modelClass): string {
        $lines = [];
        $target = $table['name'];

        if (in_array('a', $operations)) {
            $lines[] = '@router.get("/", response_model=List[' . $modelClass . '])';
            $lines[] = 'def list_' . $suffix . '():';
            $lines[] = '    return service.all()';
            $lines[] = '';
        }

        if (in_array('w', $operations)) {
            $lines[] = '@router.post("/where", response_model=List[' . $modelClass . '])';
            $lines[] = 'def where_' . $suffix . '(filters: Dict[str, Any]):';
            $lines[] = '    return service.where(filters)';
            $lines[] = '';
        }

        if (in_array('r', $operations)) {
            $lines[] = '@router.get("/{item_id}", response_model=' . $modelClass . ')';
            $lines[] = 'def show_' . $suffix . '(item_id: int):';
            $lines[] = '    record = service.find(item_id)';
            $lines[] = '    if not record:';
            $lines[] = '        raise HTTPException(status_code=404, detail="' . ucfirst($target) . ' not found")';
            $lines[] = '    return record';
            $lines[] = '';
        }

        if (in_array('c', $operations)) {
            $lines[] = '@router.post("/", response_model=' . $modelClass . ', status_code=201)';
            $lines[] = 'def create_' . $suffix . '(payload: ' . $modelClass . '):';
            $lines[] = '    return service.create(payload.dict(exclude_unset=True))';
            $lines[] = '';
        }

        if (in_array('u', $operations)) {
            $lines[] = '@router.put("/{item_id}", response_model=' . $modelClass . ')';
            $lines[] = 'def update_' . $suffix . '(item_id: int, payload: ' . $modelClass . '):';
            $lines[] = '    updated = service.update(item_id, payload.dict(exclude_unset=True))';
            $lines[] = '    if not updated:';
            $lines[] = '        raise HTTPException(status_code=404, detail="' . ucfirst($target) . ' not found")';
            $lines[] = '    return updated';
            $lines[] = '';
        }

        if (in_array('p', $operations)) {
            $lines[] = '@router.post("/upsert", response_model=' . $modelClass . ')';
            $lines[] = 'def upsert_' . $suffix . '(payload: ' . $modelClass . '):';
            $lines[] = '    return service.upsert(payload.dict(exclude_unset=True))';
            $lines[] = '';
        }

        if (in_array('d', $operations)) {
            $lines[] = '@router.delete("/{item_id}", response_model=Dict[str, bool])';
            $lines[] = 'def delete_' . $suffix . '(item_id: int):';
            $lines[] = '    if not service.delete(item_id):';
            $lines[] = '        raise HTTPException(status_code=404, detail="' . ucfirst($target) . ' not found")';
            $lines[] = '    return {"success": True}';
            $lines[] = '';
        }

        return implode("\n", array_map('rtrim', $lines));
    }

    private function writeRouterRegistry(): void {
        $filePath = $_ENV['dir'] . '/python/app/api/__init__.py';
        if (empty($this->routers)) {
            index::createfile($filePath, "\"\"\"Router registry for the generated FastAPI application.\"\"\"\n\nall_routers: list = []\n");
            return;
        }

        $imports = array_map(fn($router) => $router['import'], $this->routers);
        $aliases = array_map(fn($router) => '    ' . $router['alias'] . ',', $this->routers);

        $content = [];
        $content[] = '"""Router registry for the generated FastAPI application."""';
        $content[] = 'from __future__ import annotations';
        $content[] = '';
        $content = [...$content, ...$imports];
        $content[] = '';
        $content[] = 'all_routers = [';
        $content = [...$content, ...$aliases];
        $content[] = ']';
        $content[] = '';

        index::createfile($filePath, implode("\n", $content));
    }

    private function pythonType(string $datatype): array {
        $type = strtolower($datatype);
        return match ($type) {
            'number' => ['type' => 'int', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => false],
            'boolean' => ['type' => 'bool', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => false],
            'date' => ['type' => 'datetime', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => true],
            'json' => ['type' => 'Dict[str, Any]', 'needsAny' => true, 'needsDict' => true, 'needsDatetime' => false],
            default => ['type' => 'str', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => false],
        };
    }

    private function isColumnRequired(array $column): bool {
        if (!isset($column['sql_attribute'])) {
            return false;
        }

        $attr = strtoupper($column['sql_attribute']);
        return str_contains($attr, 'NOT NULL') || str_contains($attr, 'PRIMARY');
    }

    private function slug(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-') ?: 'resource';
    }

    private function snake(string $value): string {
        $value = preg_replace('/[^a-zA-Z0-9]+/', '_', $value);
        $value = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
        $value = preg_replace('/_+/', '_', $value);
        return trim($value, '_');
    }

    private function studly(string $value): string {
        $value = str_replace(['-', '_'], ' ', strtolower($value));
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }
}
