@extends('admin.main')
@section('link')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection
@section('content')
    <div class="container mt-5">
        <div class="col-md-12 mt-4">
            <form action="{{ route('admin.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-5">
                        <label for="start_date" class="form-label">Từ ngày:</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="{{ request()->input('start_date') }}">
                    </div>
                    <div class="col-md-5">
                        <label for="end_date" class="form-label">Đến ngày:</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="{{ request()->input('end_date') ?? now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Lọc</button>
                    </div>
                </div>
            </form>
        </div>
        {{-- @dd($orders); --}}
        <div class="row mt-5">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h3 class="card-title">{{ count($orders) }}</h3>
                        <p class="card-text">Tổng đơn hàng</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3 class="card-title">{{ $revenue }} VND</h3>
                        <p class="card-text">Tổng doanh thu</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h3 class="card-title">{{ count($users) }}</h3>
                        <p class="card-text">Số người dùng mới</p>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Sản phẩm bán chạy nhất</h5>
                    </div>
                    <div class="card-body">
                        @if(isset($topProducts) && count($topProducts) > 0)
                            <div class="row">
                                @foreach($topProducts as $product)
                                    <div class="col-md-6 mb-4">
                                        <div class="row">
                                            <div class="col-md-5">
                                                <div class="block2-pic hov-img0">
                                                    <img src="{{ $product->getThumb() }}" alt="{{ $product->name }}" style="max-width: 100%;">
                                                </div>
                                            </div>
                                            <div class="col-md-7">
                                                <div>
                                                    <h3>{{ $product->name ?? 'N/A' }}</h3>
                                                    <p class="card-text">Giá: {{ $product->price ?? 'N/A' }} VND</p>
                                                    <p class="card-text">Mô tả: {{ $product->description ?? 'N/A' }}</p>
                                                    <p class="card-text">Số lượng đã bán: {{ $product->total_sold ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="card-text">Không có thông tin về sản phẩm bán chạy nhất</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-4">
                <canvas id="orderStatusChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    <script>
        // Dữ liệu mẫu
        const orders = {!! $orders !!}; // $orders chứa dữ liệu của bạn

        // Đếm số lượng đơn hàng cho mỗi trạng thái
        const statusCount = { 'Đã đặt hàng': 0, 'Đã giao': 0, 'Đã hủy': 0 };
        orders.forEach(order => {
            if (order.status === '0') {
                statusCount['Đã đặt hàng']++;
            } else if (order.status === '1') {
                statusCount['Đã giao']++;
            } else if (order.status === '2') {
                statusCount['Đã hủy']++;
            }
        });

        const ctx = document.getElementById('orderStatusChart').getContext('2d');
        const myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(statusCount),
                datasets: [{
                    label: 'Số lượng đơn hàng',
                    data: Object.values(statusCount),
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
@endsection
