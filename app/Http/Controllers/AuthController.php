<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Mail\RegisterMail1;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    private $apiResponse;

    public function __construct()
    {   
        $this->apiResponse = new ApiResponse;
    }

    /**
     * Controller function register 
     * 
     * @param \Illiminate\Http\Request $request
     * return string JSON
     */
    public function register(Request $request) 
    {
        $param = $request->all();
        if ($param['password'] != $param['re_password']) {
            return $this->apiResponse->BadRequest(trans('message.auth.re_password_err'));
        }
        //Check email
        $checkEmail = User::where('email', $param['email'])->first();
        if ($checkEmail) {
            return $this->apiResponse->BadRequest(trans('message.auth.email_already'));
        }
        //Store new User
        $user = new User();
        $user->email = $param['email'];
        $user->password = Hash::make($param['password']);
        $user->name = $param['name'];
        $user->save();
        Mail::to($param['email'])->send(new RegisterMail1($param));
        return $this->apiResponse->success();
    }
}
