<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'file',
        'file_path',
        'mime'
    ];
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function medicaRecorde(){
        return $this->belongsTo(MedicalRecord::class);
    }
    public function url(){
        return asset('/storage/'.$this->path);
    }
}
