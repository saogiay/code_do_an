<?php

namespace App\Services;

use App\Jobs\SendOrderInvoice;
use App\Models\Cart;
use App\Models\DetailOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\BaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class OrderService extends BaseService
{
    protected $data = [];

    public function getModel()
    {
        return Order::class;
    }

    //get data payment(banking)
    public function getDataPayment($request)
    {
        $vnpayOrderId = $request->session()->get('vnpay_order_id');
        $order = Order::findOrFail($vnpayOrderId);
        $this->data['id'] = Auth::user()->carts->max('id');
        $items = Auth::user()->carts;
        $total = 0;
        foreach ($items as $item) {
            $total += $item->quantity * $item->details->product->price;
        }
        if ($order->voucher_id != null) {
            $total = $total - ($total * ($order->voucher->discount / 100));
        }
        $this->data['total'] = $total;
        return $this->data;
    }

    //lưu thông tin order
    public function createPayment($request)
    {
        try {
            DB::beginTransaction();
            // Lấy thông tin khách hàng từ request
            $address = $this->formatAddress($request);
            $voucher_id = null;
            if ($request->old('coupon')) {
                $voucher = Voucher::where('code', $request->old('coupon'))->get();
                $voucher_id = $voucher[0]->id;
            }

            // Tạo một order mới
            $order = Order::create([
                'name' => auth()->user()->name,
                'address' => $address,
                'phone' => auth()->user()->phone,
                'payment' => $request->old('payment'),
                'status' => 0,
                'voucher_id' => $voucher_id,
                'user_id' => auth()->user()->id,
            ]);

            // Lưu mã đơn hàng của VnPay vào session để xử lý khi thanh toán thành công
            $request->session()->put('vnpay_order_id', $order->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Logger($e);
        }
    }


    //format lại địa chỉ trước khi lưu
    public function formatAddress($request)
    {
        $city = $request->old('city');
        $district = $request->old('district');
        $ward = $request->old('ward');
        $number = $request->old('number');

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

        return $address;
    }

    public function storeOrder($request)
    {
        try {
            DB::beginTransaction();
            // Lấy mã đơn hàng của VnPay từ session
            $vnpayOrderId = $request->session()->get('vnpay_order_id');

            // Tìm order tương ứng trong CSDL và cập nhật trạng thái thanh toán của nó
            $order = Order::findOrFail($vnpayOrderId);
            $order->save();

            // lấy thông tin sản phẩm trong giỏ hàng
            $carts = Auth::user()->carts;

            //lưu thông tin vào detail_orders
            foreach ($carts as $key => $cart) {
                $orderDetail = DetailOrder::create([
                    'order_id' => $vnpayOrderId,
                    'detail_product_id' => $cart->detail_product_id,
                    'quantity' => $cart->quantity,
                    'price' => $cart->details->product->price
                ]);

                $orderDetail->save();

                $detailProduct = $cart->details;
                $detailProduct->quantity -= $cart->quantity;
                $detailProduct->save();
            }

            //Xóa giỏ hàng
            $userId = Auth::id();
            Cart::where('user_id', $userId)->delete();
            SendOrderInvoice::dispatch($order);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Lỗi: ' . $e->getMessage());
        }
    }

    //Lấy danh sách hóa đơn của khách hàng
    public function getAllOrder()
    {
        return Order::where('user_id',auth()->user()->id)->paginate(8);
    }
    //
    public function getAllOrders($request)
    {
        $result = Order::where('deleted_at', null)->with('orderDetails','orderDetails.details','orderDetails.details.product');
        if ($request->input('order_id') != '') {
            $result->where('id', $request->input('order_id'));
        }
        if ($request->input('status') != '') {
            $result->where('status', $request->input('search_product'));
        }
        if ($request->input('user_name') != '') {
            $result->where('name', 'LIKE', '%' . $request->input('user_name') . '%');
        }
        if ($request->input('payment') != '') {
            $result->where('payment',$request->input('payment'));
        }
        return $result->paginate(200);
    }

    public function changeStatus($request)
    {
        Order::find($request->order_id)->update([
            'status' => $request->new_status
        ]);
    }

    public function getAll($request)
    {
        $query = Order::query()->with('orderDetails');

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

        $orders = $query->get();

        // Tính toán revenue chỉ dựa trên các đơn hàng có status là 1
        $revenue = 0;

        // Lặp qua danh sách các đơn hàng để tính toán revenue
        foreach ($orders as $order) {
            // Chỉ tính toán revenue cho các đơn hàng có status là 1
            if ($order->status == 1) {
                foreach ($order->orderDetails as $orderDetail) {
                    $revenue += $orderDetail->price * $orderDetail->quantity;
                }
            }
        }

        // Lấy danh sách ID của các đơn hàng đã lọc
        $orderIds = $orders->pluck('id')->toArray();

        // Tìm top product bán chạy nhất từ danh sách các đơn hàng đã lọc
        $bestSellingProduct = Product::select('products.*')
            ->join('detail_products', 'products.id', '=', 'detail_products.product_id')
            ->join('detail_order', 'detail_products.id', '=', 'detail_order.detail_product_id')
            ->selectRaw('products.*, SUM(detail_order.quantity) as total_sold')
            ->whereIn('detail_order.order_id', $orderIds)
            ->groupBy('products.id')
            ->orderByDesc('total_sold')
            ->get(2);

        return [
            "orders" => $orders ?? null,
            "revenue" => $revenue ?? null,
            "topProducts" => $bestSellingProduct ?? null,
        ];
    }

    // public function getRevenue($request)
    // {
    //     $query = Order::query();
    //     if ($request->has('start_date')) {
    //         $start_date = $request->input('start_date');

    //         $query->where('created_at', '>=', $start_date);
    //     }

    //     // Lọc dữ liệu theo ngày kết thúc nếu được cung cấp
    //     if ($request->has('end_date')) {
    //         $end_date = $request->input('end_date');

    //         $query->where('created_at', '<=', $end_date);
    //     }

    //     $query->where('status',1);
    //     return $query->sum(function ($column) {
    //         return $column->price * $column->quantity;
    //     });
    // }

    // function getTopProduct($request) {

    // }
}
