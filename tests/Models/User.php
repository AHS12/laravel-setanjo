<?php

namespace Ahs12\Setanjo\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email'];

    protected $table = 'users';

    public $timestamps = false;
}
