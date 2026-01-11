<?php

namespace Puneetxp\CompilePhp\Class;

class dotnetset {

    public $table;
    public $json;

    public function __construct($table, $json) {
        $this->table = $table;
        $this->json = $json;
    }

    public function dotnetset() {
        index::templatecopy("dotnet", "dotnet");
        
        foreach ($this->table as $item) {
            // Model
            $model_content = $this->dotnetModel($item);
            index::createfile($_ENV['dir'] . "/dotnet/Models/" . ucfirst($item['name']) . ".cs", $model_content);

            // Controller
            $controller_content = $this->dotnetController($item);
            index::createfile($_ENV['dir'] . "/dotnet/Controllers/" . ucfirst($item['name']) . "Controller.cs", $controller_content);
        }
        echo "DotNet Build\n";
    }

    public function getCSharpType($type) {
        switch ($type) {
            case 'number': return 'long'; // Defaulting number to long for ID/relations
            case 'string': return 'string';
            case 'Date': return 'DateTime';
            case 'boolean': return 'bool';
            default: return 'string';
        }
    }

    public function dotnetModel($table) {
        $name = ucfirst($table['name']);
        $properties = "";
        
        foreach ($table['data'] as $col) {
            $type = $this->getCSharpType($col['datatype']);
            $propName = ucfirst($col['name']);
            // Check for nullable
            $nullable = "?"; 
            if (isset($col['sql_attribute']) && str_contains($col['sql_attribute'], 'NOT NULL')) {
                $nullable = "";
            }
            if ($type == 'string') $nullable = "?"; // Strings are nullable by default in some contexts, but let's be explicit
            
            $properties .= "    public $type$nullable $propName { get; set; }\n";
        }

        return "using System;
using System.Collections.Generic;

namespace DotNetApp.Models
{
    public class $name
    {
$properties
    }
}";
    }

    public function dotnetController($table) {
        $name = ucfirst($table['name']);
        return "using Microsoft.AspNetCore.Mvc;
using System.Collections.Generic;
using DotNetApp.Models;
using System.Linq;

namespace DotNetApp.Controllers
{
    [ApiController]
    [Route(\"api/[controller]\")]
    public class ${name}Controller : ControllerBase
    {
        // CRUD operations stub
        // Actual implementation would need a DbContext
        
        [HttpGet]
        public IEnumerable<$name> Get()
        {
            return new List<$name>();
        }

        [HttpGet(\"{id}\")]
        public ActionResult<$name> Get(long id)
        {
            return Ok();
        }

        [HttpPost]
        public ActionResult Post($name item)
        {
            return Ok();
        }

        [HttpPut(\"{id}\")]
        public ActionResult Put(long id, $name item)
        {
            return Ok();
        }

        [HttpDelete(\"{id}\")]
        public ActionResult Delete(long id)
        {
            return Ok();
        }
    }
}";
    }
}
