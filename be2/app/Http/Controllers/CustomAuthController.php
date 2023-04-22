<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
class CustomAuthController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function customLogin(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            $id = DB::table('users')->where('email', '=', $request->input('email'))->get('id');
            $id = json_decode($id, true)[0]['id'];
            return view('dashboard', compact('id'));
        }
        return redirect("login")->withSuccess('Login details are not valid');
    }

    public function registration()
    {
        return view('auth.registration');
    }
    
    public function customRegistration(Request $request)
    {
        $request->validate([
            'name' => 'required',
            // 'email' => 'required|email|unique:users',
            // 'password' => 'required|min:6',
            'fileToUpload' => 'required',
            'phone' => 'required',

        ]);
        $data = $request->all();
        $file = $request->file('fileToUpload');
      
        $fileName = $file->getClientOriginalName();
        

        $destinationPath = 'uploads';
        $file->move($destinationPath,$file->getClientOriginalName());

        $data['fileName'] = $fileName;
        $check = $this->create($data);
       
        return redirect("dashboard")->withSuccess("You have signed-in");
    }

    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'image' => $data['fileName'],
            'password' => Hash::make($data['password'])
        ]);
    }

    public function dashboard()
    {
        if(Auth::check()){
            
            return view('dashboard');
        }
        return redirect("login")->withSuccess("You are not allowed to access");
    }

    public function signOut()
    {
        Session::flush();
        Auth::logout();
        return Redirect('login');
    }
}
