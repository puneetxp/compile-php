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
            $this->writeOrmModel($table);  // Generate ORM models automatically
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
            $_ENV['dir'] . '/python/app/orm',  // Add ORM directory
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

        // NO __init__.py files - Python 3.3+ uses implicit namespace packages (PEP 420)
        // This follows the NO_INIT_FILES_POLICY for cleaner, more maintainable code
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

    private function writeOrmModel(array $table): void {
        $className = $this->studly($table['name']);
        $filePath = $_ENV['dir'] . '/python/app/orm/' . $this->snake($table['name']) . '.py';
        $tableName = $table['table'];
        
        // Build fillable fields list from table data
        $fillableFields = [];
        foreach ($table['data'] as $column) {
            $fillableFields[] = "        '" . $this->snake($column['name']) . "',";
        }
        
        // Build relations dictionary
        $relationLines = [];
        if (isset($table['relations']) && is_array($table['relations']) && !empty($table['relations'])) {
            foreach ($table['relations'] as $relName => $relConfig) {
                // Get the related table name - handle both 'table' and 'model' keys
                $relatedTable = $relConfig['table'] ?? $relConfig['model'] ?? $relName;
                $relatedClass = $this->studly($relatedTable);
                $relatedModule = $this->snake($relatedTable);
                
                // Use string-based lazy loading to avoid circular imports
                $relationLines[] = "            '$relName': {";
                $relationLines[] = "                'name': '" . $this->snake($relConfig['name']) . "',";
                $relationLines[] = "                'key': '" . $this->snake($relConfig['key']) . "',";
                $relationLines[] = "                'callback': lambda: __import__('app.orm.$relatedModule', fromlist=['$relatedClass']).$relatedClass";
                $relationLines[] = "            },";
            }
        }
        
        $content = [];
        $content[] = '"""';
        $content[] = $className . ' ORM Model';
        $content[] = 'Auto-generated from JSON schema';
        $content[] = '"""';
        $content[] = '';
        $content[] = 'from app.core.model import Model';
        $content[] = '';
        $content[] = '';
        $content[] = 'class ' . $className . '(Model):';
        $content[] = '    """' . $className . ' model for ' . $tableName . ' table"""';
        $content[] = '    ';
        $content[] = "    table = '$tableName'";
        $content[] = '    ';
        $content[] = '    fillable = [';
        $content = [...$content, ...$fillableFields];
        $content[] = '    ]';
        
        if (!empty($relationLines)) {
            $content[] = '    ';
            $content[] = '    relations = {';
            $content = [...$content, ...$relationLines];
            $content[] = '    }';
        }
        
        $content[] = '';
        
        index::createfile($filePath, implode("\n", $content));
    }

    private function writeService(array $table): void {
        $className = $this->studly($table['name']) . 'Service';
        $filePath = $_ENV['dir'] . '/python/app/services/' . $this->snake($table['name']) . '_service.py';
        $tableName = $table['table'];
        $modelSnake = $this->snake($table['name']);
        $modelClass = $this->studly($table['name']);
        $specificGetterName = 'get_' . $modelSnake . '_service';

        $content = implode("\n", [
            'from __future__ import annotations',
            '',
            "from app.orm.$modelSnake import $modelClass",
            '',
            '',
            "class $className:",
            '    def __init__(self) -> None:',
            "        self.model = $modelClass",
            "        self.table = \"$tableName\"",
            '',
            '    def all(self):',
            '        return self.model().get()',
            '',
            '    def find(self, item_id: int):',
            "        return self.model().where('id', item_id).first()",
            '',
            '    def where(self, filters: dict):',
            '        query = self.model()',
            '        for key, value in filters.items():',
            '            query = query.where(key, value)',
            '        return query.get()',
            '',
            '    def create(self, data: dict):',
            '        return self.model().create(data)',
            '',
            '    def update(self, item_id: int, data: dict):',
            "        record = self.model().where('id', item_id).first()",
            '        if not record:',
            '            return None',
            '        for key, value in data.items():',
            '            setattr(record, key, value)',
            '        record.save()',
            '        return record',
            '',
            '    def upsert(self, data: dict):',
            "        if 'id' in data and data['id']:",
            "            return self.update(data['id'], data)",
            '        return self.create(data)',
            '',
            '    def delete(self, item_id: int) -> bool:',
            "        record = self.model().where('id', item_id).first()",
            '        if not record:',
            '            return False',
            '        record.delete()',
            '        return True',
            '',
            '',
            '# Singleton instance',
            '_service = ' . $className . '()',
            '',
            '',
            '# Generic getter (for auto-generated routers)',
            'def get_service() -> ' . $className . ':',
            '    """Get service instance (generic name for auto-generated code)"""',
            '    return _service',
            '',
            '',
            '# Specific getter (for manual/custom code compatibility)',
            'def ' . $specificGetterName . '() -> ' . $className . ':',
            '    """Get service instance (specific name for backward compatibility)"""',
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
        $tableSnake = $this->snake($table['name']);
        $scopeSnake = $this->snake($normalizedScope);
        $moduleSegments = $isCustomRole ? ['roles', $scopeSnake, $tableSnake] : [$scopeSnake, $tableSnake];
        $modulePath = implode('.', $moduleSegments);
        $dirPath = $_ENV['dir'] . '/python/app/api/' . implode('/', $moduleSegments);

        // Create directory structure without __init__.py files
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Use proper module file naming (not __init__.py)
        $filePath = $dirPath . '/' . $tableSnake . '.py';
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

        $functionSuffix = $scopeSnake . '_' . $tableSnake;
        $prefix = '/' . $scopeSnake . '/' . $tableSnake;

        $methods = $this->routerMethods($table, $operations, $functionSuffix, $modelClass);

        $content = implode("\n", $imports) . "\n\n\nrouter = APIRouter(prefix=\"$prefix\", tags=[\"$scopeSnake-$tableSnake\"])\nservice = get_service()\n\n" . $methods;

        index::createfile($filePath, $content);

        $alias = $scopeSnake . '_' . $tableSnake . '_router';
        $this->routers[] = [
            'import' => 'from app.api.' . $modulePath . '.' . $tableSnake . ' import router as ' . $alias,
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
        // Use routers.py instead of __init__.py (PEP 420 - no __init__.py needed)
        $filePath = $_ENV['dir'] . '/python/app/api/routers.py';
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
