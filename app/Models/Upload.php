<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory;
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
    public function prescription(){
        return $this->hasOne(Prescription::class, 'prescription_image_id');
    }
    
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'attachment_id');
    }
    
    public function url(){
        return asset('/storage/'.$this->file_path);
    }
    
}
