@php $kind = $data['kind'] ?? 'contact'; @endphp
@if (session('form_sent') === $kind)
    <p class="sent" role="status">{{ $data['success'] ?? 'Thanks — we’ll reply within one business day.' }}</p>
@else
    @include('site.partials.form-'.$kind)
@endif
