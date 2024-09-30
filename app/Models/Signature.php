<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $fillable = [
        'contract_id',
        'user_id',
        'signature_path',
        'final_pdf_path',
        'ip_address',
        'device_info',
        'unique_code'
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
