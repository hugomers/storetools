<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartitionRequisition extends Model
{
    protected $connection = 'vizapi';

    protected $table = 'requisition_partitions';
     public $timestamps = false;


    public function logs(){
        return $this->hasMany('App\Models\PartitionLog','_status');
    }


    public function status(){
        return $this->belongsTo('App\Models\InvoiceStatus', '_status');
    }
    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_required', '_partition', '_product', 'id')
        ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock', 'toDelivered', 'toReceived', 'ipack', 'checkout','_suplier_id');
    }

    public function requisition(){
        return $this->hasOne('App\Models\Invoice','id','_requisition');
    }

    public function log(){
        return $this->belongsToMany('App\Models\InvoiceStatus', 'partition_logs', '_partition', '_status')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }

    public function getOutVerifiedStaff(){
        return \App\Models\Staff::where('id', $this->_out_verified)->first();
    }
}
