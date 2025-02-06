<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the users
     *
     * @param  \App\Models\User  $model
     * @return \Illuminate\View\View
     */
    public function index(User $model)
    {
        return view('users.index', ['users' => $model->paginate(15)]);
    }
    public function toggleAutoUpdate(Request $request)
    {
        $user = auth()->user();
        $currentSetting = DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'is_auto_update_enable_spot')->first();

        if ($currentSetting) {
            DB::table('user_meta')->where('user_id', auth()->user()->id)->where('meta_key', 'is_auto_update_enable_spot')->update([
                'meta_value' =>   $currentSetting->meta_value == 'on' ? 'off' : 'on'
            ]);
        } else {
            DB::table('user_meta')->insert([
                'meta_key' => 'is_auto_update_enable_spot',
                'meta_value' => 'on',
                'user_id' => auth()->user()->id
            ]);
        }

        return back()->with('success', 'Auto-update setting toggled.');
    }
}
