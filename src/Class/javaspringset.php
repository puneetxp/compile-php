<?php

namespace Puneetxp\CompilePhp\Class;

class javaspringset {

    public $table;
    public $json;

    public function __construct($table, $json) {
        $this->table = $table;
        $this->json = $json;
    }

    public function javaspringset() {
        index::templatecopy("spring", "spring");
        
        foreach ($this->table as $item) {
            // Model/Entity
            $model_content = $this->springEntity($item);
            index::createfile($_ENV['dir'] . "/spring/src/main/java/com/example/demo/model/" . ucfirst($item['name']) . ".java", $model_content);

            // Repository
            $repo_content = $this->springRepository($item);
            index::createfile($_ENV['dir'] . "/spring/src/main/java/com/example/demo/repository/" . ucfirst($item['name']) . "Repository.java", $repo_content);

            // Controller
            $controller_content = $this->springController($item);
            index::createfile($_ENV['dir'] . "/spring/src/main/java/com/example/demo/controller/" . ucfirst($item['name']) . "Controller.java", $controller_content);
        }
        echo "Spring Build\n";
    }

    public function getJavaType($type) {
        switch ($type) {
            case 'number': return 'Long';
            case 'string': return 'String';
            case 'Date': return 'LocalDateTime';
            case 'boolean': return 'Boolean';
            default: return 'String';
        }
    }

    public function springEntity($table) {
        $name = ucfirst($table['name']);
        $fields = "";
        
        foreach ($table['data'] as $col) {
            $type = $this->getJavaType($col['datatype']);
            $fieldName = lcfirst($col['name']); // camelCase
            
            $annotations = "";
            if ($col['name'] == 'id') {
                $annotations .= "    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n";
            }
            
            $fields .= "$annotations    private $type $fieldName;\n";
        }
        
        // Getters and Setters omitted for brevity, using Lombok @Data if possible or just plain fields in this simplified generator
        // Attempting to add simple getters/setters or Lombok annotation
        return "package com.example.demo.model;

import javax.persistence.*;
import java.time.LocalDateTime;
import lombok.Data;

@Entity
@Data
@Table(name = \"$table[name]\")
public class $name {
$fields
}";
    }

    public function springRepository($table) {
        $name = ucfirst($table['name']);
        return "package com.example.demo.repository;

import com.example.demo.model.$name;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface ${name}Repository extends JpaRepository<$name, Long> {
}";
    }

    public function springController($table) {
        $name = ucfirst($table['name']);
        $repo = lcfirst($name) . "Repository";
        
        return "package com.example.demo.controller;

import com.example.demo.model.$name;
import com.example.demo.repository.${name}Repository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping(\"/api/" . strtolower($name) . "\")
public class ${name}Controller {

    @Autowired
    private ${name}Repository $repo;

    @GetMapping
    public List<$name> getAll() {
        return ${repo}.findAll();
    }

    @PostMapping
    public $name create(@RequestBody $name item) {
        return ${repo}.save(item);
    }

    @GetMapping(\"/{id}\")
    public $name getOne(@PathVariable Long id) {
        return ${repo}.findById(id).orElse(null);
    }

    @PutMapping(\"/{id}\")
    public $name update(@PathVariable Long id, @RequestBody $name item) {
        return ${repo}.save(item);
    }

    @DeleteMapping(\"/{id}\")
    public void delete(@PathVariable Long id) {
        ${repo}.deleteById(id);
    }
}";
    }
}
