<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcheancesTranche extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='echeances_tranches';
    protected $dates=['deleted_at'];
    protected $with=['tranche'];

    public function tranche()
    {
        return $this->belongsTo(Tranche::class,'tranche_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
}
