<?php

namespace App\Http\Controllers\Api\V1\Payment\Moyasar;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Base\Constants\Auth\Role;
use App\Http\Controllers\ApiController;
use App\Models\Payment\UserWalletHistory;
use App\Models\Payment\DriverWalletHistory;
use App\Transformers\Payment\WalletTransformer;
use App\Transformers\Payment\DriverWalletTransformer;
use App\Http\Requests\Payment\AddMoneyToWalletRequest;
use App\Transformers\Payment\UserWalletHistoryTransformer;
use App\Transformers\Payment\DriverWalletHistoryTransformer;
use App\Models\Payment\UserWallet;
use App\Models\Payment\DriverWallet;
use App\Base\Constants\Masters\WalletRemarks;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Jobs\NotifyViaMqtt;
use App\Base\Constants\Masters\PushEnums;

/**
 * @group Razerpay Payment Gateway
 *
 * Payment-Related Apis
 */
class MoyasarController extends ApiController
{

    /**
    * Add money to wallet
    * @bodyParam amount double required  amount entered by user
    * @bodyParam payment_id string required  payment_id from transaction
    * @response {
    "success": true,
    "message": "money_added_successfully",
    "data": {
        "id": "1195a787-ba13-4a74-b56c-c48ba4ca0ca0",
        "user_id": 15,
        "amount_added": 2500,
        "amount_balance": 2500,
        "amount_spent": 0,
        "currency_code": "INR",
        "created_at": "1st Sep 10:45 PM",
        "updated_at": "1st Sep 10:51 PM"
    }
}
    */
    

    public function addMoneyToWallet(Request $request)
    {
        
        
           $input = $request->all();
       
        
		
    		if(count($input)  && $input['status'] == 'failed') {
    			
    			return response()->json(['result' => false, 'message' =>'Payment failed.', 'payment_details' => '']);
    			
    		}
		
		    $amount = $input['amount']/100;
        
		    $payment_id = $input['id'];
		    
            $transaction_id = $payment_id;
            
            $user = auth()->user();
            
            if (access()->hasRole('user')) {
            $wallet_model = new UserWallet();
            $wallet_add_history_model = new UserWalletHistory();
            $user_id = auth()->user()->id;
        } else {
            $wallet_model = new DriverWallet();
            $wallet_add_history_model = new DriverWalletHistory();
            $user_id = auth()->user()->driver->id;
        }

        $user_wallet = $wallet_model::firstOrCreate([
            'user_id'=>$user_id]);
        $user_wallet->amount_added += $amount;
        $user_wallet->amount_balance += $amount;
        $user_wallet->save();
        $user_wallet->fresh();

        $wallet_add_history_model::create([
            'user_id'=>$user_id,
            'amount'=>$amount,
            'transaction_id'=>$transaction_id,
            'remarks'=>WalletRemarks::MONEY_DEPOSITED_TO_E_WALLET,
            'is_credit'=>true]);


                $pus_request_detail = json_encode($request->all());
        
                $socket_data = new \stdClass();
                $socket_data->success = true;
                $socket_data->success_message  = PushEnums::AMOUNT_CREDITED;
                $socket_data->result = $request->all();

                $title = trans('push_notifications.amount_credited_to_your_wallet_title');
                $body = trans('push_notifications.amount_credited_to_your_wallet_body');

                // dispatch(new NotifyViaMqtt('add_money_to_wallet_status'.$user_id, json_encode($socket_data), $user_id));
                
                $user->notify(new AndroidPushNotification($title, $body));

                if (access()->hasRole(Role::USER)) {
                $result =  fractal($user_wallet, new WalletTransformer);
                } else {
                $result =  fractal($user_wallet, new DriverWalletTransformer);
                }

                return response()->json(['result' => true, 'message' =>'Payment success.', 'payment_details' => $input]);
    }

    
}
