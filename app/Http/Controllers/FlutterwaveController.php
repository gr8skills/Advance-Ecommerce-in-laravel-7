<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PayBeforeDelivery;
use App\Models\Product;
use App\Models\Shipping;
use App\Notifications\StatusNotification;
use App\User;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Notification;
use PDF;

class FlutterwaveController extends Controller
{

    public function initialize(Request $request)
    {
        $this->validate($request,[
            'first_name'=>'string|required',
            'last_name'=>'string|required',
            'address1'=>'string|required',
            'address2'=>'string|nullable',
            'coupon'=>'nullable|numeric',
            'phone'=>'numeric|required',
            'post_code'=>'string|nullable',
            'email'=>'string|required'
        ]);
        if(empty(Cart::where('user_id',auth()->user()->id)->where('order_id',null)->first())){
            request()->session()->flash('error','Cart is Empty !');
            return back();
        }
        $full_name = $request['last_name'] . ' ' . $request['first_name'];

        $order=new Order();
        $order_data=$request->all();
        $order_data['order_number']='ORD-'.strtoupper(Str::random(10));
        $order_data['user_id']=$request->user()->id;
        $order_data['shipping_id']=$request->shipping;
        $shipping=Shipping::where('id',$order_data['shipping_id'])->pluck('price');
        $order_data['sub_total']=Helper::totalCartPrice();
        $order_data['quantity']=Helper::cartCount();
        if(session('coupon')){
            $order_data['coupon']=session('coupon')['value'];
        }
        $shipping = collect($shipping)->toArray();
        if(isset($request->shipping)){
            if(session('coupon')){
                $order_data['total_amount']=Helper::totalCartPrice()+$shipping[0]-session('coupon')['value'];
            }
            else{
                $order_data['total_amount']=Helper::totalCartPrice()+$shipping[0];
//                dd($order_data['total_amount']);
            }
        }
        else{
            if(session('coupon')){
                $order_data['total_amount']=Helper::totalCartPrice()-session('coupon')['value'];
            }
            else{
                $order_data['total_amount']=Helper::totalCartPrice();
            }
        }
        // return $order_data['total_amount'];
        $order_data['status']="new";
        if(request('payment_method')=='paypal'){
            $order_data['payment_method']='paypal';
            $order_data['payment_status']='paid';
        }elseif (request('payment_method')=='flutterwave'){
            $order_data['payment_method']='paypal';
            $order_data['payment_status']='paid';
        }
        else{
            $order_data['payment_method']='cod';
            $order_data['payment_status']='Unpaid';
        }
        //This generates a payment reference
        $reference = Flutterwave::generateReference();
        $order_data['transaction_ref'] = $reference;
        $order->fill($order_data);
        $status=$order->save();
//        $order = Order::all()->last();
//        $order->update(['transaction_ref'=>$reference]);
//        dd($order);
        if($status)
            $users=User::where('role','admin')->first();
        $details=[
            'title'=>'New order created',
            'actionURL'=>route('order.show',$order->id),
            'fas'=>'fa-file-alt'
        ];
        Notification::send($users, new StatusNotification($details));

        if(request('payment_method')=='flutterwave'){
            $params = ['id'=>$order->id, 'email'=>$request['email'], 'phone'=>$request['phone'], 'address'=>$request['address1'], 'name'=>$full_name];
            if (isset($params) && !is_null($params)){
                $order_id = $params['id'];
                $phone = $params['phone'];
                $address = $params['address'];
                $customer_name = $params['name'];
                $email = $params['email'];
            }else{
                $order_id = null;
                $phone = '08069018574';
                $address = 'Lekki, Lagos';
                $customer_name = 'Customer One';
                $email = 'info@owambenco.com';
            }


            $cart = Cart::where('user_id',auth()->user()->id)->where('order_id',null)->get()->toArray();

            $data = [];

            // return $cart;
            $data['items'] = array_map(function ($item) use($cart) {
                $name=Product::where('id',$item['product_id'])->pluck('title');
                $nam = collect($name)->toArray();
                $name = $nam[0];
                return [
                    'name' =>$name ,
                    'price' => $item['price'],
                    'desc'  => 'Thank you for using flutterwave',
                    'qty' => $item['quantity']
                ];
            }, $cart);
            Order::where('transaction_ref',$reference)->update(['items_detail'=>$data['items']]);
            $data['invoice_id'] ='ORD-'.strtoupper(uniqid());
            $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
            $data['return_url'] = route('callback');
            $data['cancel_url'] = route('flutter.cancel');

            $total = 0;
            foreach($data['items'] as $item) {
                $total += $item['price']*$item['qty'];
            }

            $total_shipping_fee = $shipping[0]*count($data['items']);
            $data['total'] = $total;
            $data['shipping_discount'] = 0;
            if(session('coupon')){
                $data['shipping_discount'] = session('coupon')['value'];
            }
            Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => session()->get('id')]);


            // Enter the details of the payment
            $data = [
                'payment_options' => 'card,banktransfer',
                'amount' => $data['total'] - $data['shipping_discount'] + $total_shipping_fee,
                'email' => $email,
                'tx_ref' => $reference,
                'currency' => "NGN",
                'redirect_url' => $data['return_url'],
                'customer' => [
                    'email' => $email,
                    "phone_number" => $phone,
                    "name" => $customer_name,
                    "address" => $address,
                ],

                "customizations" => [
                    "title" => $data['invoice_id'] .' by ' .$email.' ('.$customer_name.')',
                    "description" => $reference,
                    "items" => $data['items']
                ]
            ];

            $payment = Flutterwave::initializePayment($data);


            if ($payment['status'] !== 'success') {
                // notify something went wrong
                request()->session()->flash('error','Error occurred');
                return;
            }
            session()->forget('cart');
            session()->forget('coupon');
            return redirect($payment['data']['link']);
        }else{
            session()->forget('cart');
            session()->forget('coupon');

            $cart = Cart::where('user_id',auth()->user()->id)->where('order_id',null)->get()->toArray();
            $data = [];
            $data['items'] = array_map(function ($item) use($cart) {
                $name=Product::where('id',$item['product_id'])->pluck('title');
                $nam = collect($name)->toArray();
                $name = $nam[0];
                return [
                    'name' =>$name ,
                    'price' => $item['price'],
                    'desc'  => 'Thank you for using flutterwave',
                    'qty' => $item['quantity']
                ];
            }, $cart);
            Order::where('transaction_ref',$reference)->update(['items_detail'=>json_encode($data['items'])]);


            Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => $order->id]);

            request()->session()->flash('success','Your order is placed successfully');
            return redirect()->route('home');
        }

    }

    /**
     * Obtain Rave callback information
     * @return void
     */
    public function callback()
    {

        $status = request()->status;
        $transactionRef = request()->tx_ref;

        //if payment is successful
        if ($status ==  'successful') {

            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data = Flutterwave::verifyTransaction($transactionID);
            //save data
            $save = new PayBeforeDelivery();
            $save_data = [];
//            dd($data);
            $save_data['transaction_id'] = $data['data']['id'];
            $save_data['status'] = $data['data']['status'];
            $save_data['transaction_ref'] = $data['data']['tx_ref'];
            $save_data['charged_amount'] = $data['data']['charged_amount'];
            $save_data['message'] = $data['message'];
            $save_data['data'] = json_encode($data['data']);
            $save->fill($save_data);
            $save->save();
            $update_order = ['online_trx_status'=>$data['data']['status'], 'charged_amount'=>$data['data']['charged_amount'], 'message'=>$data['message'], 'data'=>json_encode($data['data']), 'transaction_id'=>$transactionID];
            $order = Order::where('transaction_ref', $transactionRef)->first();
            $order->update($update_order);
//            request()->session()->flush();
            session()->forget('cart');
            session()->forget('coupon');
            request()->session()->flash('success','Your order is placed successfully');
            return redirect()->route('home');
        }
        elseif ($status ==  'cancelled'){
            request()->session()->flash('error','You cancelled the payment process, please try again');
            return redirect()->back();
            //Put desired action/code after transaction has been cancelled here
        }
        else{
            request()->session()->flash('error','An error occurred');
            return redirect()->back();
            //Put desired action/code after transaction has failed here
        }
        // Get the transaction from your DB using the transaction reference (txref)
        // Check if you have previously given value for the transaction. If you have, redirect to your successpage else, continue
        // Confirm that the currency on your db transaction is equal to the returned currency
        // Confirm that the db transaction amount is equal to the returned amount
        // Update the db transaction record (including parameters that didn't exist before the transaction is completed. for audit purpose)
        // Give value for the transaction
        // Update the transaction to note that you have given value for the transaction
        // You can also redirect to your success page from here

    }

    public function cancel()
    {
        dd('Your payment is canceled. You can create cancel page here.');
    }
}
