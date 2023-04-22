<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListUserController extends Controller
{
    public function ListUser()
    {
        $pageNow = 1;
        $listUsers = DB::table('users')->offset(0)->limit(3)->get();
        $listUsers = json_decode($listUsers, true);
        $totalPage = ceil(DB::table('users')->count('id') / 3);
        return view('user.listusers', compact('listUsers', 'totalPage', 'pageNow'));
    }

    public function ListUserByPage($page)
    {
        $start = ($page - 1) * 3;
        $listUsers = DB::table('users')->offset($start)->limit(3)->get();
        $listUsers = json_decode($listUsers, true);
        $totalPage = ceil(DB::table('users')->count('id') / 3);
        $pageNow = (int)$page;
        return view('user.listusers', compact('listUsers', 'totalPage', 'pageNow'));
    }
}
