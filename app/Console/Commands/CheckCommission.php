<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;

class CheckCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:commission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '返佣服务';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $order = Order::where('commission_status', 0)
            ->where('status', 3)
            ->get();
        foreach ($order as $item) {
            if ($order->invite_user_id) {
                $inviter = User::find($order->invite_user_id);
                if (!$inviter) continue;
                $inviter->commission_balance = $inviter->commission_balance + $order->commission_balance;
                if ($inviter->save()) {
                    $item->commission_status = 1;
                    $item->save();
                }
            }
        }
    }
    
}
