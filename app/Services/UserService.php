<?php

namespace App\Services;

use App\Models\User;
use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;

class UserService
{
    public function changeStatus($request)
    {
        User::find($request->user_id)->update([
            'status' => $request->status
        ]);
    }

    public function getInfoUser()
    {
        return Auth::user();
    }

    public function updateAddress($request)
    {
        $city = $request->city;
        $district =$request->district;
        $ward = $request->ward;
        $number = $request->number;

        $address = '';

        if ($city) {
            $address .= $city . ', ';
        }

        if ($district) {
            $address .= $district . ', ';
        }

        if ($ward) {
            $address .= $ward . ', ';
        }

        if ($number) {
            $address .= $number;
        }

        // Xóa dấu phẩy ở cuối nếu có
        $address = rtrim($address, ', ');

        $user = Auth::user();
        $user->address = $address;
        $user->save();
    }

    public function updateUser($request)
    {
        $user = Auth::user();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->registered_pro = $request->registered_pro;
        $user->save();
    }

    public function getCountUsers($request)
    {
        $query = User::query();
        // Lọc dữ liệu theo ngày bắt đầu nếu được cung cấp
        if ($request->has('start_date') && $request->filled('start_date')) {
            $start_date = $request->input('start_date');
            $query->where('created_at', '>=', $start_date);
        }

        // Lọc dữ liệu theo ngày kết thúc nếu được cung cấp
        if ($request->has('end_date'  && $request->filled('end_date')) ) {
            $end_date = $request->input('end_date');
            $query->where('created_at', '<=', $end_date);
        }

        return $query->where('status', '1')->get();
    }
}
