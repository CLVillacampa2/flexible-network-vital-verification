<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $fillable = ['household_uuid', 'household_code', 'region', 'province', 'city_municipality', 'barangay', 'purok_sitio', 'street', 'house_number', 'postal_code', 'contact_number', 'address_notes'];

    public function patients() { return $this->hasMany(Patient::class); }
}
