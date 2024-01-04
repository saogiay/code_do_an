
<option value="">Chọn màu sắc</option>
@if ($colors)
@foreach ($colors as $color)
<option value="{{$color['id']}}" style="color: {{ $color['color']['code'] }};"> {{$color['color']['name']}}</option>
@endforeach
@endif
