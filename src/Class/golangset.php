<?php

namespace Puneetxp\CompilePhp\Class;

class golangset {

    public $table;
    public $json;

    public function __construct($table, $json) {
        $this->table = $table;
        $this->json = $json;
    }

    public function golangset() {
        index::templatecopy("go", "go");
        
        foreach ($this->table as $item) {
            // Model
            $model_content = $this->golangModel($item);
            index::createfile($_ENV['dir'] . "/go/models/" . strtolower($item['name']) . ".go", $model_content);

            // Controller
            $controller_content = $this->golangController($item);
            index::createfile($_ENV['dir'] . "/go/controllers/" . strtolower($item['name']) . "_controller.go", $controller_content);
        }
        echo "Golang Build\n";
    }

    public function getGoType($type) {
        switch ($type) {
            case 'number': return 'int64';
            case 'string': return 'string';
            case 'Date': return 'time.Time';
            case 'boolean': return 'bool';
            default: return 'string';
        }
    }

    public function golangModel($table) {
        $name = ucfirst($table['name']);
        $fields = "";
        
        foreach ($table['data'] as $col) {
            $type = $this->getGoType($col['datatype']);
            $fieldName = ucfirst($col['name']); // Exported field
            $jsonTag = "`json:\"" . $col['name'] . "\"`";
            $fields .= "    $fieldName $type $jsonTag\n";
        }

        return "package models

import (
    \"time\"
)

type $name struct {
$fields
}";
    }

    public function golangController($table) {
        $name = ucfirst($table['name']);
        return "package controllers

import (
    \"net/http\"
    \"github.com/gin-gonic/gin\"
)

type ${name}Controller struct{}

func (${name}Controller) Index(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{\"data\": \"List of $name\"})
}

func (${name}Controller) Show(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{\"data\": \"Show $name\"})
}

func (${name}Controller) Store(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{\"data\": \"Store $name\"})
}

func (${name}Controller) Update(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{\"data\": \"Update $name\"})
}

func (${name}Controller) Delete(c *gin.Context) {
    c.JSON(http.StatusOK, gin.H{\"data\": \"Delete $name\"})
}";
    }
}
