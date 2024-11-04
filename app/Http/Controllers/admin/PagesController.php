<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSetting;
use App\Models\Offer;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PagesController extends Controller
{
    public static function home(){
        return view('admin.pages.home',[
            'admin' => Auth::guard('admin')->user(),
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'offer1' => Offer::where('id', 1)->first(),
            'offer2' => Offer::where('id', 2)->first(),
            'offer3' => Offer::where('id', 3)->first(),
            'offer4' => Offer::where('id', 4)->first(),
            'offer5' => Offer::where('id', 5)->first(),
            'offer6' => Offer::where('id', 6)->first(),
            'offer7' => Offer::where('id', 7)->first(),
            'offer8' => Offer::where('id', 8)->first(),
            'offer9' => Offer::where('id', 9)->first(),
            'offer10' => Offer::where('id', 10)->first(),
            'homeSettings' => HomeSetting::where('id',1)->first(),
            'slider2' => HomeSetting::where('id',2)->first(),
            'slider3' => HomeSetting::where('id',3)->first(),
            'category1' => HomeSetting::where('id',4)->first(),
            'category2' => HomeSetting::where('id',5)->first(),
            'category3' => HomeSetting::where('id',6)->first(),
            'categories' => Category::where('status', 1)->get(),
        ]);
    }

    public static function homeSettingUpdate(Request $request){
        HomeSetting::updateInfo($request);
        return back();
    }
    public static function homeSettingShow($id){
        HomeSetting::statusCheck($id);
        return back();
    }
    public static function aboutPage(){
        return view('admin.pages.about',[
            'admin' => Auth::guard('admin')->user(),
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'about' => Page::find(1),
        ]);
    }
    public static function contactPage(){
        return view('admin.pages.contact',[
            'admin' => Auth::guard('admin')->user(),
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'contact' => Page::find(2),
        ]);
    }
    public static function privacy_policy(){
        return view('admin.pages.privacy',[
            'admin' => Auth::guard('admin')->user(),
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'privacy' => Page::find(3),
        ]);
    }
    public static function terms_and_condition(){
        return view('admin.pages.terms',[
            'admin' => Auth::guard('admin')->user(),
            'siteSettings' => SiteSetting::where('id', 1)->first(),
            'terms' => Page::find(4),
        ]);
    }
    public static function updateAboutPage(Request $request){
        Page::updateInfo($request);
        return back();
    }
    public static function updateContactPage(Request $request){
        Page::updateInfo($request);
        return back();
    }
    public static function updatePrivacyPage(Request $request){
        Page::updateInfo($request);
        return back();
    }
    public static function updateTermsPage(Request $request){
        Page::updateInfo($request);
        return back();
    }
}
