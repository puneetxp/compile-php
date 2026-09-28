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
        $inputFields = [];
        $needsOptional = false;
        $needsAny = false;
        $needsDict = false;
        $needsDatetime = false;
        $needsDate = false;
        $needsDecimal = false;

        foreach ($table['data'] as $column) {
            $typeInfo = $this->pythonType($column['datatype'] ?? 'string', $column['mysql_data'] ?? '');
            $typeHint = $typeInfo['type'];
            $needsAny = $needsAny || $typeInfo['needsAny'];
            $needsDict = $needsDict || $typeInfo['needsDict'];
            $needsDatetime = $needsDatetime || $typeInfo['needsDatetime'];
            $needsDate = $needsDate || ($typeInfo['needsDate'] ?? false);
            $needsDecimal = $needsDecimal || ($typeInfo['needsDecimal'] ?? false);

            $isOptional = !$this->isColumnRequired($column);
            $needsOptional = $needsOptional || $isOptional;

            if ($isOptional) {
                $typeHint .= ' | None';
            }

            $default = $isOptional ? ' = None' : '';
            $fields[] = '    ' . $this->snake($column['name']) . ': ' . $typeHint . $default;
            if (!in_array($column['name'], ['id', 'created_at', 'updated_at'], true)) {
                // Aliased types: a column named `date` would otherwise shadow the type once it defaults to None.
                $inputType = strtr($typeInfo['type'], ['datetime' => '_datetime', 'date' => '_date']);
                $inputType = str_replace('_date_datetime', '_datetime', $inputType);
                $inputFields[] = '    ' . $this->snake($column['name']) . ': ' . $inputType . ' | None = None';
            }
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
        $datetimeNames = array_merge($needsDate ? ['date'] : [], $needsDatetime ? ['datetime'] : []);
        if (!empty($datetimeNames)) {
            $imports[] = 'from datetime import ' . implode(', ', $datetimeNames);
        }
        if ($needsDecimal) {
            $imports[] = 'from decimal import Decimal';
        }
        if (!empty($datetimeNames)) {
            $imports[] = 'from datetime import ' . implode(', ', array_map(fn($n) => "$n as _$n", $datetimeNames));
        }

        // ${className}Input is the request body for create/update: every column optional, server-managed ones left out.
        $content = implode("\n", $imports) . "\n\n\nclass $className(BaseModel):\n" . ($fields ? implode("\n", $fields) : '    pass') . "\n"
            . "\n\nclass {$className}Input(BaseModel):\n" . ($inputFields ? implode("\n", $inputFields) : '    pass') . "\n";

        index::createfile($filePath, $content);
    }

    private function writeOrmModel(array $table): void {
        $className = $this->studly($table['name']);
        $filePath = $_ENV['dir'] . '/python/app/orm/' . $this->snake($table['name']) . '.py';
        $tableName = $table['table'];
        
        // Build fillable fields list from table data
        $fillableFields = [];
        
        // Only add fields that are explicitly fillable (exclude auto-generated fields)
        // Auto-generated fields: id, created_at, updated_at (marked with fillable=false)
        foreach ($table['data'] as $column) {
            // Skip fields marked as non-fillable (auto-generated fields)
            if (isset($column['fillable']) && $column['fillable'] === "false") {
                continue;
            }
            
            $fillableFields[] = "        '" . $this->snake($column['name']) . "',";
        }
        
        // Build relations dictionary
        $relationLines = [];
        if (isset($table['relations']) && is_array($table['relations']) && !empty($table['relations'])) {
            foreach ($table['relations'] as $relName => $relConfig) {
                // ORM files are named after the singular model (orm/active_role.py -> ActiveRole),
                // not the plural table, so resolve the model name before building the import.
                $relatedModel = $relConfig['callback']
                    ?? $relConfig['model']
                    ?? $this->modelForTable($relConfig['table'] ?? '')
                    ?? $relName;
                $relatedClass = $this->studly($relatedModel);
                $relatedModule = $this->snake($relatedModel);
                
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

        // All CRUD (and islogin owner scoping) lives in app/core/crud_service.py; see app/core/ownership.py.
        $content = implode("\n", [
            'from __future__ import annotations',
            '',
            'from app.core.crud_service import CrudService',
            "from app.orm.$modelSnake import $modelClass",
            '',
            '',
            "class $className(CrudService):",
            "    model = $modelClass",
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
        $modelImport = 'from app.models.' . $tableSnake . ' import ' . $modelClass . ', ' . $modelClass . 'Input';

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

        // islogin (and custom role) controllers act for the signed-in user: rows are owner-scoped.
        $scoped = $normalizedScope === 'islogin' || $isCustomRole;
        $imports = ['from __future__ import annotations', 'from fastapi import APIRouter, HTTPException' . ($scoped ? ', Depends' : ''), $modelImport, $serviceImport];
        // isuper controllers are admin-only (also enforced where main.py mounts them).
        $adminOnly = $normalizedScope === 'isuper';
        if ($adminOnly) {
            $imports[1] = 'from fastapi import APIRouter, HTTPException, Depends';
            $imports[] = 'from app.core.auth import get_current_admin';
        }
        if ($scoped) {
            $imports[] = 'from app.core.auth import get_current_active_user';
        }
        if (!empty($typingImports)) {
            $imports[] = 'from typing import ' . implode(', ', array_unique($typingImports));
        }

        $functionSuffix = $scopeSnake . '_' . $tableSnake;
        $prefix = '/' . $scopeSnake . '/' . $tableSnake;

        $methods = $this->routerMethods($table, $operations, $functionSuffix, $modelClass, $scoped);

        $guard = $adminOnly ? ",\n                   dependencies=[Depends(get_current_admin)]" : '';
        $content = implode("\n", $imports) . "\n\n\nrouter = APIRouter(prefix=\"$prefix\", tags=[\"$scopeSnake-$tableSnake\"]$guard)\nservice = get_service()\n\n" . $methods;

        index::createfile($filePath, $content);

        $alias = $scopeSnake . '_' . $tableSnake . '_router';
        $this->routers[] = [
            'import' => 'from app.api.' . $modulePath . '.' . $tableSnake . ' import router as ' . $alias,
            'alias' => $alias,
        ];
    }

    private function routerMethods(array $table, array $operations, string $suffix, string $modelClass, bool $scoped = false): string {
        $lines = [];
        $target = $table['name'];
        $user = $scoped ? 'current_user=Depends(get_current_active_user)' : '';
        $userAfter = $scoped ? ', ' . $user : '';
        $owner = $scoped ? 'owner=current_user' : '';
        $ownerAfter = $scoped ? ', ' . $owner : '';

        if (in_array('a', $operations)) {
            $lines[] = '@router.get("/", response_model=List[' . $modelClass . '])';
            $lines[] = 'def list_' . $suffix . '(' . $user . '):';
            $lines[] = '    return service.all(' . $owner . ')';
            $lines[] = '';
        }

        if (in_array('w', $operations)) {
            $lines[] = '@router.post("/where", response_model=List[' . $modelClass . '])';
            $lines[] = 'def where_' . $suffix . '(filters: Dict[str, Any]' . $userAfter . '):';
            $lines[] = '    return service.where(filters' . $ownerAfter . ')';
            $lines[] = '';
        }

        if (in_array('r', $operations)) {
            $lines[] = '@router.get("/{item_id}", response_model=' . $modelClass . ')';
            $lines[] = 'def show_' . $suffix . '(item_id: int' . $userAfter . '):';
            $lines[] = '    record = service.find(item_id' . $ownerAfter . ')';
            $lines[] = '    if not record:';
            $lines[] = '        raise HTTPException(status_code=404, detail="' . ucfirst($target) . ' not found")';
            $lines[] = '    return record';
            $lines[] = '';
        }

        if (in_array('c', $operations)) {
            $lines[] = '@router.post("/", response_model=' . $modelClass . ', status_code=201)';
            $lines[] = 'def create_' . $suffix . '(payload: ' . $modelClass . 'Input' . $userAfter . '):';
            $lines[] = '    return service.create(payload.dict(exclude_unset=True)' . $ownerAfter . ')';
            $lines[] = '';
        }

        if (in_array('u', $operations)) {
            $lines[] = '@router.put("/{item_id}", response_model=' . $modelClass . ')';
            $lines[] = 'def update_' . $suffix . '(item_id: int, payload: ' . $modelClass . 'Input' . $userAfter . '):';
            $lines[] = '    updated = service.update(item_id, payload.dict(exclude_unset=True)' . $ownerAfter . ')';
            $lines[] = '    if not updated:';
            $lines[] = '        raise HTTPException(status_code=404, detail="' . ucfirst($target) . ' not found")';
            $lines[] = '    return updated';
            $lines[] = '';
        }

        if (in_array('p', $operations)) {
            $lines[] = '@router.post("/upsert", response_model=' . $modelClass . ')';
            $lines[] = 'def upsert_' . $suffix . '(payload: ' . $modelClass . 'Input' . $userAfter . '):';
            $lines[] = '    return service.upsert(payload.dict(exclude_unset=True)' . $ownerAfter . ')';
            $lines[] = '';
        }

        if (in_array('d', $operations)) {
            $lines[] = '@router.delete("/{item_id}", response_model=Dict[str, bool])';
            $lines[] = 'def delete_' . $suffix . '(item_id: int' . $userAfter . '):';
            $lines[] = '    if not service.delete(item_id' . $ownerAfter . '):';
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

    private function modelForTable(string $tableName): ?string {
        foreach ($this->table as $t) {
            if (($t['table'] ?? null) === $tableName) {
                return $t['name'];
            }
        }
        return null;
    }

    private function pythonType(string $datatype, string $sqlType = ''): array {
        $type = strtolower($datatype);
        $sqlType = strtolower(trim($sqlType));

        // "number" alone can't distinguish integers from DECIMAL/NUMERIC/FLOAT; the SQL type can.
        if ($type === 'number' && preg_match('/^(decimal|numeric|float|double|real)/', $sqlType)) {
            // float, not Decimal: pydantic serialises Decimal as a JSON string, which breaks number formatting in the UI.
            return ['type' => 'float', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => false];
        }
        if ($type === 'date' && $sqlType === 'date') {
            return ['type' => 'date', 'needsAny' => false, 'needsDict' => false, 'needsDatetime' => false, 'needsDate' => true];
        }

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
