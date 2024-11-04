<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function CheckoutLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required | email ',
            'password' => 'required | min:8 ',
        ]);

        if($validator->passes()) {
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password],$request->get('remember'))){

                return redirect(route('checkout'))->with('success', 'You are Successfully Logged in.');
            }else{
                return redirect(route('checkout'))->with('error', 'Either Email/Password is incorrect!');
            }
        }else{
            return redirect()->route('checkout')
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }
    }
}
