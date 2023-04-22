<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewUserController extends Controller
{
    public function viewUser($id)
    {
        $viewUser = DB::table('users')->where('id', '=', $id)->get();
        $viewUser = json_decode($viewUser)[0];
        return view('auth.viewUser')->with('viewUser', $viewUser);
    }
}
