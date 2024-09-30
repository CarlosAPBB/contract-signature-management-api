<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = ['user_id', 'name', 'file_path', 'dynamic_fields', 'status'];
    protected $casts = [
        'dynamic_fields' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signature()
    {
        return $this->hasOne(Signature::class);
    }

    public function isSigned()
    {
        return $this->status === 'signed';
    }
}
