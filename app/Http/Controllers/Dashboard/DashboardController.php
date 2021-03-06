<?php

namespace App\Http\Controllers\Dashboard;

use App\AccountDetail;
use App\CardRate;
use App\CardSelling;
use App\Chat;
use App\CoinBuying;
use App\CoinRate;
use App\CoinSelling;
use App\Http\Controllers\Controller;
use App\ImageUpload;
use App\Message;
use App\Review;
use App\User;
use App\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function Dashboard(){
        $coin_sellings = CoinSelling::where('user_id', Auth::user()->id)->get();
        $coin_selling_transactions = CoinSelling::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->take(3)->get();
        $coin_buyings_transactions = CoinBuying::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->take(3)->get();
        $card_selling_transactions = CardSelling::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->take(6)->get();
        $coin_buyings = CoinBuying::where('user_id', Auth::user()->id)->get();
        $cards = CardSelling::where('user_id', Auth::user()->id)->get();
        $coin_rates = CoinRate::get();
        $some_card_rates = CardRate::inRandomOrder()->take(4)->get();
        $chats = Chat::where('user_id', Auth::user()->id)->get();
        return view('actions.dashboard', compact('coin_buyings', 'coin_sellings',
            'cards', 'coin_rates', 'some_card_rates',
            'coin_buyings_transactions', 'coin_selling_transactions', 'card_selling_transactions', 'chats'));
    }

    public function Profile(){
        $account_details = AccountDetail::where('user_id', Auth::user()->id)->first();
        return view('actions.profile', compact('account_details'));
    }


    public function updateBankDetails(Request $request){
        $this->validate($request, [
            'bank'=> 'bail|required',
            'account_no' => 'bail|required',
            'full_name' => 'bail|required',
        ]);
        try {
            $user_details = AccountDetail::where('user_id', Auth::user()->id)->first();
            if ($user_details){
                $user_details->bank = $request->bank;
                $user_details->account_number = $request->account_no;
                $user_details->name = $request->full_name;
                $user_details->token = Str::random(15);
                $user_details->save();
                return redirect()->back()->with('success','Account Details Successfully Updated');
            }
            else{
                $new_account_details = new AccountDetail();
                $new_account_details->bank = $request->bank;
                $new_account_details->account_number = $request->account_no;
                $new_account_details->name = $request->full_name;
                $new_account_details->user_id = Auth::user()->id;
                $new_account_details->token = Str::random(15);
                $new_account_details->save();
                if (session()->get('intended_url')){
                    $link = session()->get('intended_url');
                    session()->forget('intended_url');
                    return redirect(route($link))->with('success','Account Details Successfully Saved');
                }
                else{
                    return redirect()->back()->with('success','Account Details Successfully Saved');
                }
            }
        }
        catch (\Exception $exception){
            return redirect()->back()->with('failure','Account Details Could not be Saved or Updated');
        }

    }

    public function updateProfileDetails(Request $request){
        $this->validate($request, [
            'phone_number' => 'bail|required',
        ]);
        try {
            if($request->file('profile_pic')->getSize() > 1000000 )
            {
                return redirect()->back()->with('failure', "Uploaded File Size is Larger than 1mb");
            }
            $user = User::where('id', Auth::user()->id)->first();
            if ($request->hasFile('profile_pic')){
                $image = $request->file('profile_pic');
                $image_name = User::processImage($image);
                $user->icon = $image_name;
            }
            $user->phone_number = $request->phone_number;
            $user->save();
            return redirect()->back()->with('success','Account Details Successfully Updated');
        }
        catch (\Exception $exception){
            return redirect()->back()->with('failure','Account Details Could not be Saved or Updated');
        }

    }

    public function myMessage(){
        $chats = Chat::where('user_id',Auth::user()->id)->orderBy('id', 'desc')->paginate(3);
        return view('actions.my-message', compact('chats'));
    }

    public function sendChat(Request $request){
        $this->validate($request, [
           'message' => 'bail|required'
        ]);
        try {
            $new_chat = new Chat();
            $new_chat->user_id = Auth::user()->id;
            $new_chat->title = Str::limit($request->message,90, "...");
            $new_chat->body = $request->message;
            $new_chat->sender = 0;
            $new_chat->save();
            return redirect()->back()->with('success','Message Successfully Sent');
        }
        catch (\Exception $exception){
            return redirect()->back()->with('failure','Message Could not be Sent');
        }
    }

    public function userApprovalTransaction($token){
        try {
            $card_sellings = CardSelling::where('token', $token)->first();
            if ($card_sellings && $card_sellings->amount_payable != 0){
                $card_sellings->user_transaction_approval = 1;
                $card_sellings->save();
                return redirect()->back()->with('success', "Transaction Details Successfully Updated");
            }
            else{
                return redirect()->back()->with('failure', "This Transaction Does Not Exist");
            }
        }
        catch(\Exception $exception){
            return redirect()->back()->with('failure', "Error Occur in Processing this Action");
        }
    }

    public function userCancelTransaction($token){
        try {
            $card_sellings = CardSelling::where('token', $token)->first();
            if ($card_sellings && $card_sellings->amount_payable != 0){
                $card_sellings->user_transaction_approval = 2;
                $card_sellings->save();
                return redirect()->back()->with('success', "Transaction Details Successfully Updated");
            }
            else{
                return redirect()->back()->with('failure', "This Transaction Does Not Exist");
            }
        }
        catch(\Exception $exception){
            return redirect()->back()->with('failure', "Error Occur in Processing this Action");
        }
    }

    public function viewUploadedResources($token){
        $upload = CardSelling::where(['token'=>$token, 'user_id' => Auth::user()->id])->first();
        if ($upload){
            $images = ImageUpload::where('card_selling_id', $upload->id)->get();
            return view('actions.view-upload-cards', compact('images'));
        }
        else{
            return redirect()->back()->with('failure', "Card Transaction Does not exist");
        }
    }

    public function myCoinTransactions(){
        $coin_sellings_transactions = collect(CoinSelling::where('user_id', Auth::user()->id)->get());
        $coin_buyings_transactions = collect(CoinBuying::where('user_id', Auth::user()->id)->get());
        $transactions =$coin_buyings_transactions->merge($coin_sellings_transactions)->sortByDesc('created_at');
        return view('actions.coin-transactions', compact('transactions'));
    }

    public function myCardTransactions(){
        $card_selling_transactions = CardSelling::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->get();
        return view('actions.card-transactions', compact('card_selling_transactions'));
    }

    public function withdrawalRequest(){
        $account_details = AccountDetail::where('user_id', Auth::user()->id)->first();
        if ($account_details){
            return view('actions.request-withdrawal', compact('account_details'));
        }
        else{
            return redirect(route('user.profile'))->with('failure', 'You need to update your account details first');
        }

    }

    public function finalizeWithdrawal(Request $request){
        $this->validate($request, [
           'amount' => 'bail|required'
        ]);
        try {
            if ($request->amount > Auth::user()->account_balance){
                return redirect()->back()->with('failure', 'Insufficient Balance');
            }
            else{
                $user = User::where('id', Auth::user()->id)->first();
                $user->account_balance = $user->account_balance - $request->amount;
                $user->save();

                $withdrawal_request = new Withdrawal();
                $withdrawal_request->user_id = Auth::user()->id;
                $withdrawal_request->amount = $request->amount;
                $withdrawal_request->status = 0;
                $withdrawal_request->save();

                return redirect()->back()->with('success', 'Withdrawal Requested Successfully Submitted');
            }
        }
        catch(\Exception $exception){
            return redirect()->back()->with('failure', "Error Occur in Processing your Withdrawal Request");
        }

    }

    public function leaveReview(Request $request){
        $this->validate($request,[
           'message' => 'bail|required'
        ]);
        try {
             $new_review = new Review();
             $new_review->user_id = Auth::user()->id;
             $new_review->message = $request->message;
             $new_review->token = Str::random(15);
             $new_review->save();
             return redirect()->back()->with('success', 'Review Successfully Submitted');
        }
        catch(\Exception $exception){
            return redirect()->back()->with('failure', "Review Could not Be Submitted");
        }
    }

    public function withdrawalHistories($token){
        $user = User::where('token', $token)->first();
        if ($user){
            $histories = Withdrawal::where('user_id', $user->id)->get();
            return view('actions.withdrawals-history', compact('histories'));
        }
        else{
            return redirect()->back()->with('failure', "Unauthorized Access");
        }
    }
}
