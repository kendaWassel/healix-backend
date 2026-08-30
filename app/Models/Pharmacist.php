<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Pharmacist extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'pharmacy_name',
        'cr_number',
        'license_file_id',
        'address',
        'latitude',
        'longitude',
        'from',
        'to',
        'rating_avg',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }
    public function orders(){
        return $this->hasMany(Order::class);
    }
    /**
     * Real bug fixed here (found via White Box review, see
     * tests/Unit/PharmacistIsOpenTest.php): Carbon::between() silently
     * swaps its two bounds whenever $to is chronologically before $from,
     * which is exactly the overnight case (e.g. from=22:00, to=06:00) —
     * so an overnight pharmacy was reported open during the day and
     * closed during its own real overnight hours. Handled explicitly now:
     * a same-day window (from <= to) checks current time is within
     * [from, to]; an overnight window (from > to) checks it's on either
     * side of midnight, i.e. >= from OR <= to.
     */
    public function isOpen()
    {
        $currentTime = Carbon::now('Asia/Damascus');
        $from = Carbon::createFromFormat('H:i:s', $this->from, 'Asia/Damascus');
        $to = Carbon::createFromFormat('H:i:s', $this->to, 'Asia/Damascus');

        if ($from->lessThanOrEqualTo($to)) {
            return $currentTime->between($from, $to);
        }

        return $currentTime->greaterThanOrEqualTo($from) || $currentTime->lessThanOrEqualTo($to);
    }
    public function license_file()
    {
        return $this->belongsTo(Upload::class, 'license_file_id');
    }   

}
