<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConfigurationSocialNetwork extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table='configuration_social_networks';
    protected $dates=['deleted_at'];

}
