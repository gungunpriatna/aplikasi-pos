<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order_detail extends Model
{
    protected $guarded = [];

    //model relationship ke order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
