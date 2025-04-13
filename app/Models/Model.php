<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Str;

abstract class Model extends EloquentModel
{
    /**
     * @inheritdoc
     */
    public function getTable() : string
    {
        if (!isset($this->table)) {
            return str_replace('\\', '', Str::snake(class_basename($this)));
        }
        
        return $this->table;
    }
}
