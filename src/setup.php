<?php

namespace Puneetxp\CompilePhp;

use Puneetxp\CompilePhp\Class\{mysql, index, denoset, phpset, solidset, vueset, angularset};

class setup
{
   public $pattern_route = '/\$route.*?;/';
   public $pattern_use_only = '/use.*?\w;/';
   public $pattern_use_multple = "/use (.*?){(.*?)};/";
   public $route_use_single = '';
   public $route_use_array = [];
   public $route_use_multiple = '';
   public $route_app = ' (new Route())';
   public $json_set;
   public $files;
   public $table;
   public $roles = ['isuper'];
   public function __construct($dir = __DIR__  . "/../../")
   {
      $_ENV["dir"] = $dir;
      $this->route_use_array['The\\'] = ["Route"];
      $this->json_set = json_decode(file_get_contents($_ENV["dir"]  . '/config.json'), TRUE);
      foreach (glob($_ENV["dir"] . "/setup/model/*.json") as $file) {
         $filename = preg_replace("/.*.\/(.*).json/", "$1", $file);
         $j = json_decode(file_get_contents($file), TRUE);
         $this->files[$filename] = $j;
      }
   }
   public function config()
   {
      $this->table_set();
      //deno
      if (in_array('deno', $this->json_set['back-end'])) {
         $this->deno_set();
         echo "Deno Build\n";
      }
      //php
      if (in_array('php', $this->json_set['back-end'])) {
         $this->php_set();
         echo "PHP Build\n";
      }
      //angular
      if (in_array('angular', $this->json_set['front-end'])) {
         $this->angular_set();
         echo "Angular Build\n";
      }
      //write mysql
      $this->write();
      return $this;
   }
   public function table_set()
   {
      foreach ($this->files as $key => $item) {
         if (isset($item['crud']['roles'])) {
            if (is_array($item['crud']['roles'])) {
               $this->roles = array_merge(array_keys($item['crud']['roles']), $this->roles);
            }
         }
         $this->table[] = index::table_set($item, array_values($this->files))->table;
      }
      $this->roles = array_filter(array_unique($this->roles), fn ($role) => !($role == "*" || $role == "-"));
      for ($i = 0; $i < count($this->table); ++$i) {
         if (count($this->table[$i]['relations']) > 0) {
            foreach ($this->table[$i]['relations'] as $key => $items) {
               for ($t = 0; $t < count($this->table); ++$t) {
                  if ($this->table[$t]['name'] == $key) {
                     $this->table[$t]['relations'][$this->table[$i]['name']] = ['table' => $this->table[$i]['table'], 'name' => $items['key'], 'key' => $items['name']];
                  }
               }
            }
         }
      }
      $this->table = mysql::addattribute($this->table);
      echo "Tables Build\n";
      foreach ($this->table as $item) {
         isset($this->json_set['table'][$item['name']]) ? '' : $this->json_set['table'][$item['name']] = false;
      }
      return $this;
   }
   public function php_set()
   {
      new phpset($this->table, $this->json_set);
      echo "PHP Build\n";
      return $this;
   }
   public function deno_set()
   {
      (new denoset($this->table, $this->json_set))->denoset();
      echo "Deno Build\n";
      return $this;
   }
   public function angular_set()
   {
      (new angularset($this->table, $this->json_set))->angularset();
      echo "Angular Build\n";
      return $this;
   }
   public function vuejs_set()
   {
      new vueset($this->table, $this->json_set);
      echo "Angular Build\n";
      return $this;
   }
   public function solidjs_set()
   {
      new solidset($this->table, $this->json_set);
      echo "Angular Build\n";
      return $this;
   }
   public function write()
   {
      foreach ($this->route_use_array as $key => $value) {
         $this->route_use_multiple .= "use $key{" . implode(',', array_unique($value)) . "}; ";
      }
      mysql::alltable($this->table);
      $migration_sql = '';
      $migration_relation = '';
      foreach ($this->table as $item) {
         $migration_sql .= file_get_contents($_ENV["dir"]  . "/database/" . ucfirst('mysql/') . ucfirst('structure/') . ucfirst($item['name']) . '.sql', 'TRUE');
         $migration_relation .= file_get_contents($_ENV["dir"]  . "/database/" . ucfirst('mysql/') . ucfirst('relations/') . ucfirst($item['name']) . '_relation.sql', 'TRUE');
      }
      $migration_sql .= 'INSERT INTO roles (name) VALUES ("' . implode('"),("', array_values(array_unique($this->roles))) . '");';
      file_put_contents($_ENV["dir"]  . '/database/structure.sql', ($migration_sql));
      file_put_contents($_ENV["dir"]  . '/database/relation.sql', ($migration_relation));
      file_put_contents($_ENV["dir"]  . '/database/Migration.sql', ($migration_sql . ' ' . $migration_relation));
      file_put_contents($_ENV["dir"]  . '/config.json', json_encode($this->json_set, JSON_PRETTY_PRINT));
      return $this;
   }
   public function migrate()
   {
      (new mysql())->migrate();
      return $this;
   }
}