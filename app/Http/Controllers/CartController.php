<?php

namespace App\Http\Controllers;

use App\Mail\OrderAdminEmail;
use App\Mail\OrderEmail;
use App\Models\Category;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Userinfo;
use App\Notifications\NewOrderNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Dompdf\Dompdf;
use Dompdf\Options;
use Barryvdh\DomPDF\Facade\Pdf;

class CartController extends Controller
{
    public function addToCart(Request $request, $id)
    {
        $product = Product::find($id);

        if (!empty($product)) {
            $color = $request->input('color');
            $size = $request->input('size');
            $quantity = $request->input('quantity');

            // Validate the inputs if necessary

            // Check if the product already exists in the cart
            $productAlreadyExist = Cart::search(function ($cartItem, $rowId) use ($product, $color, $size) {
                return $cartItem->id == $product->id && $cartItem->options->color == $color && $cartItem->options->size == $size;
            });

            if ($productAlreadyExist->isNotEmpty()) {
                $cartCount = Cart::count();
                $cartContent = Cart::content();
                $cartSubtotal = Cart::subtotal();
                return response()->json([
                    'message' => $product->name . ' already in cart!', 'type' => 'error',
                    'cartCount' => $cartCount,
                    'cartContent' => $cartContent,
                    'cartSubtotal' => $cartSubtotal,
                ]);
            }

            // Add the item to the cart
            Cart::add($product->id, $product->name, $quantity, $product->price, [
                'image' => $product->featured_image,
                'slug' => $product->slug,
                'color' => $color,
                'size' => $size,
            ]);
            $cartCount = Cart::count();
            $cartContent = Cart::content();
            $cartSubtotal = Cart::subtotal();

            return response()->json([
                'message' => $product->name . ' added to cart successfully', 'type' => 'success',
                'cartCount' => $cartCount,
                'cartContent' => $cartContent,
                'cartSubtotal' => $cartSubtotal,
            ]);
        } else {
            return response()->json(['message' => 'No Product Found', 'type' => 'error']);
        }
    }

    public function cart()
    {
        return view('frontend.cart.cart', [
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'categories' => Category::orderBy('name', 'ASC')->where('status', '1')->with('sub_category')->get(),
            'cartContent' => Cart::content(),
        ]);
    }

    public function updateCart(Request $request)
    {
        try {
            $quantities = $request->input('quantity');

            foreach ($quantities as $rowId => $quantity) {
                // Ensure $rowId exists in the cart before attempting to update
                if (!Cart::get($rowId)) {
                    throw new \Exception("Item not found in the cart.");
                }

                // Update the cart
                Cart::update($rowId, $quantity);
            }

            return redirect()->back()->with('success', 'Cart updated successfully');
        } catch (\Exception $e) {
            \Log::error("Error updating cart: " . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while updating the cart.');
        }
    }

    public function removeFromCart($rowId)
    {
        try {
            // Ensure $rowId exists in the cart before attempting to remove
            if (!Cart::get($rowId)) {
                throw new \Exception("Item not found in the cart.");
            }

            // Remove the item from the cart
            Cart::remove($rowId);

            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error("Error removing product from cart: " . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while removing the product from the cart.');
        }
    }

    public function clearCart()
    {
        try {
            // Get all cart items and remove them
            foreach (Cart::content() as $item) {
                Cart::remove($item->rowId);
            }

            return redirect()->back()->with('success', 'Cart cleared successfully');
        } catch (\Exception $e) {
            \Log::error("Error clearing the cart: " . $e->getMessage());

            return redirect()->back()->with('error', 'An error occurred while clearing the cart.');
        }
    }

    public function getCartDetails()
    {
        $cartContent = Cart::content(); // Retrieve cart content as needed

        // You can use a Blade view to render the cart details HTML
        $cartHtml = view('partials.cart-details', compact('cartContent'))->render();

        return response()->json(['cartHtml' => $cartHtml]);

    }

    public function checkout(){

        if (Auth::check()){
            $userInfo  = Userinfo::where('user_id', Auth::user()->id)->first();
        }else{
            $userInfo = null;
        }


        $subTotal = Cart::subtotal(2,'.','');


        $discount = 0;
        if (session()->has('code')){
            $code = session()->get('code');
            if ($code->type == 'Percentage'){
                $discount = ($code->discount_amount/100)*$subTotal;
            }else{
                $discount = $code->discount_amount;
            }

        }

        $country = Country::where('code', 'BD')->first();
        $states = explode(',', $country->states);

        // Sort the array in ascending order
        sort($states);


        if (Cart::count() == 0){

            if (!session()->has('url.intended')){
                session(['url.intended' => url()->current()]);
            }
            return redirect(route('cart'));
        }
        session()->forget('url.intended');
        return view('frontend.checkout.checkout',[
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'categories' => Category::orderBy('name', 'ASC')->where('status', '1')->with('sub_category')->get(),
            'cartContent' => Cart::content(),
            'countries' => Country::where('status' ,1)->get(),
            'userInfo' => $userInfo,
            'shipping_methods' => Shipping::all(),
            'states' => $states,
            'discount' => $discount,
        ]);
    }

    public static function processCheckout(Request $request){

        $admin = User::where('role', 0)->first();

        $rules=[
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required',
            'country' => 'required',
            'billing_address' => 'required',
            'state' => 'required',
            'payment_option' => 'required',
        ];

        if (!empty($request->create_account) && $request->create_account == 'Yes'){
            $rules['password'] = 'required | min:8';
            $rules['email'] = 'required |unique:users';
        }else{
            $rules['email'] = 'required';
        }

        if (!empty($request->shipping) && $request->shipping == 'Yes'){
            $rules['shipping_first_name'] = 'required';
            $rules['shipping_last_name'] = 'required';
            $rules['shipping_phone'] = 'required';
            $rules['shipping_country'] = 'required';
            $rules['shipping_address'] = 'required';
            $rules['shipping_state'] = 'required';
        }

        $validator = Validator::make($request->all(),$rules);

        if ($validator->passes()){
            if (!Auth::check() && session()->has('code') ){
                $coupon_code = session()->get('code')->code;
                $coupon_code_uses_user = session()->get('code')->max_uses_user;
                $couponUsedByUser = Order::where([ 'coupon_code'=> $coupon_code , 'email'=> $request->email ])->count();
                if ($couponUsedByUser < $coupon_code_uses_user){

                    $orderId = Order::saveInfo($request);
                    $order = Order::find($orderId);

                    // Generate PDF
                    $pdf = PDF::loadView('email.invoice', $order); // Assuming you're using DomPDF or similar library
                    $pdfView = $pdf->stream(); // Removed the filename parameter because it's unnecessary

                    // Save PDF to public directory
                    $pdfFileName = $order->order_number . '.pdf'; // Generate a unique filename
                    $pdfPath = 'invoices/' . $pdfFileName; // Construct the full path

                    file_put_contents(public_path($pdfPath), $pdfView);

                    // Update order with the PDF path
                    $order->invoice = $pdfPath;
                    $order->save();

                    $orderData = [
                        'id' =>   $order->order_number,
                        'pdf' => $pdfView,
                        'order' => $order,
                    ];
                    // Send emails with PDF attached
                    Mail::to($request->email)->send(new OrderEmail($orderData));
                    Mail::to($admin->email)->send(new OrderAdminEmail($orderData));

                    Notification::send($admin, new NewOrderNotification($order));

                    return view('frontend.checkout.thankYou',[
                        'siteSettings' => SiteSetting::where('id', 1)->first(),
                        'categories' => Category::orderBy('name', 'ASC')->where('status', '1')->with('sub_category')->get(),
                        'cartContent' => Cart::content(),
                        'order' => $order,
                    ]);

                }else{
                    return back()->withErrors("Your coupons maximum uses exceeded");
                }
            }
            else{
                $orderId = Order::saveInfo($request);
                $order = Order::find($orderId);

                $pdfData = [
                    'order' => $order,
                ];
                // Generate PDF
                $pdf = PDF::loadView('email.invoice', $pdfData); // Assuming you're using DomPDF or similar library
                $pdfContent = $pdf->output(); // Removed the filename parameter because it's unnecessary

                // Save PDF to public directory
                $pdfFileName = $order->order_number . '.pdf'; // Generate a unique filename
                $pdfPath = 'invoices/' . $pdfFileName; // Construct the full path

                file_put_contents(public_path($pdfPath), $pdfContent);

                // Update order with the PDF path
                $order->invoice = $pdfPath;
                $order->save();

                $orderData = [
                    'id' =>   $order->order_number,
                    'pdf' => $pdfContent,
                    'order' => $order,
                ];
                // Send emails with PDF attached
                Mail::to($request->email)->send(new OrderEmail($orderData));
                Mail::to($admin->email)->send(new OrderAdminEmail($orderData));

                Notification::send($admin, new NewOrderNotification($order));

                return view('frontend.checkout.thankYou',[
                    'siteSettings' => SiteSetting::where('id', 1)->first(),
                    'categories' => Category::orderBy('name', 'ASC')->where('status', '1')->with('sub_category')->get(),
                    'cartContent' => Cart::content(),
                    'order' => $order,
                ]);
            }

        }else{
            return back()->withErrors($validator);
        }
    }

    public static function calculateShipping(Request $request){
        $state = $request->input('state');
        $country = $request->input('country');
        $shippingState = $request->input('shipping_state');
        $shippingCountry = $request->input('shipping_country');
        $differentaddress = $request->input('differentaddress');

        $couponDiv = '';
        $subTotal = Cart::subtotal(2,'.','');
        $charge = 0; // Initialize $charge to zero


        $discount = 0;
        if (session()->has('code')){
            $code = session()->get('code');
            if ($code->type == 'Percentage'){
                $discount = ($code->discount_amount/100)*$subTotal;
            }else{
                $discount = $code->discount_amount;
            }
            $couponDiv = '<div class="d-flex justify-content-center align-items-center" id="remove_coupon_div">
                                                        <p style="font-size: 12px"> COUPON: <strong>'.session()->get('code')->code.'</strong> </p>
                                                        <button class="btn btn-sm btn-danger" id="removeCoupon" style="padding: 0px 2px !important; margin-left: 5px"><i class="bi bi-trash"></i></button>
                                                    </div><p style="color: #046963; text-align: center; font-size: 12px; margin-top: 5px;"> " Coupon has been added successfully! " </p>';
        }

        if ($differentaddress == 'Yes'){
            if ($shippingState == 'Dhaka'){
                $stateshipping = Shipping::where('region' , $shippingState)->first();
                $charge = $stateshipping->price;
            } else {
                $stateshipping = Shipping::where('shipping_area' , 'Outside Dhaka')->first();
                $charge = $stateshipping->price;
            }
        } elseif (!empty($state)){
            if ($state == 'Dhaka'){
                $stateshipping = Shipping::where('region' , $state)->first();
                $charge = $stateshipping->price;
            } else {
                $stateshipping = Shipping::where('shipping_area' , 'Outside Dhaka')->first();
                $charge = $stateshipping->price;
            }
        }


        $grandTotal = ($subTotal-$discount)+$charge;

        return response()->json([
            'status' => true,
            'grandTotal' => number_format($grandTotal,2),
            'discount' => $discount,
            'shippingCharge' => number_format($charge,2),
            'couponDiv' => $couponDiv,
        ]);
    }

    public static function applyCoupon(Request $request){

        $coupon = $request->input('coupons');
        $code = Coupon::where('code', $coupon)->where('status', 1)->first();

        if ($code == null){
            return response()->json([
                'status' => false,
                'message' => 'Invalid Discount Coupon',
            ]);
        }

        $now = Carbon::now();

        if ($code->starts_at != ''){
            $startDate = Carbon::createFromFormat('Y-m-d', $code->starts_at);
            if ($now->lt($startDate)){
                return response()->json([
                    'status' => false,
                    'message' => 'Discount Coupon is not started yet' ,
                ]);
            }
        }

        if ($code->expires_at != ''){
            $startDate = Carbon::createFromFormat('Y-m-d', $code->expires_at);
            if ($now->gt($startDate)){
                return response()->json([
                    'status' => false,
                    'message' => 'Discount Coupon is Expired',
                ]);
            }
        }

        if ($code->max_uses > 0){
            $couponUsed = Order::where('coupon_code', $code->code)->count();
            if ($couponUsed >= $code->max_uses){
                return response()->json([
                    'status' => false,
                    'message' => 'Coupons maximum uses exceeded' ,
                ]);
            }
        }

        if ($code->max_uses_user > 0 && Auth::check()){
            $couponUsedByUser = Order::where([ 'coupon_code'=> $code->code , 'user_id'=> Auth::user()->id ])->count();
            if ($couponUsedByUser >= $code->max_uses_user){
                return response()->json([
                    'status' => false,
                    'message' => 'Your coupons maximum uses exceeded' ,
                ]);
            }
        }

        if ($code->min_amount > 0){
            $subTotal = Cart::subtotal(2,'.','');
            if ($subTotal < $code->min_amount){
                return response()->json([
                    'status' => false,
                    'message' => 'Your minimum amount must be $'.$code->min_amount.'.',
                ]);
            }
        }
        session()->put('code',$code);
        return self::calculateShipping($request);

    }

    public static function removeCoupon(Request $request){
        session()->forget('code');
        return self::calculateShipping($request);
    }

    public static function thankYou(){

    }

}
