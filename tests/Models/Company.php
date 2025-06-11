<?php

namespace Ahs12\Setanjo\Tests\Models;

use Ahs12\Setanjo\Traits\HasSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, HasSettings;

    protected $fillable = ['name'];

    protected $table = 'companies';

    public $timestamps = false;
}
