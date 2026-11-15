<?php

namespace Ahs12\Setanjo\Tests\Models;

use Ahs12\Setanjo\Traits\HasFeatures;
use Ahs12\Setanjo\Traits\HasSettings;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, HasFeatures, HasSettings;

    protected $fillable = ['name', 'email'];

    protected $table = 'users';

    public $timestamps = false;
}
