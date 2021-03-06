<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CoinSelling extends Model
{
    protected $fillable = [
        'platform_id', 'coin_amount', 'coin_rate_id', 'rate_amount', 'payment_proof', 'status', 'platform_payment_proof'
        , 'user_id', 'token', 'coin_id', 'payment_type', 'amount_payable'
    ];

    public function platform(){
        return  $this->belongsTo(Platform::class);
    }
    public function coinRate(){
        return  $this->belongsTo(CoinRate::class);
    }
    public function coin(){
        return  $this->belongsTo(Coin::class);
    }
    public function user(){
        return  $this->belongsTo(User::class);
    }

}
