<div class="field {{ $class ?? '' }}">
    <label for="f-{{ $name }}">{{ $label }}@if (! empty($required)) *@endif</label>
    @if (($type ?? 'text') === 'textarea')
        <textarea id="f-{{ $name }}" name="{{ $name }}" @if (! empty($required)) required @endif>{{ old($name) }}</textarea>
    @elseif (($type ?? 'text') === 'select')
        <select id="f-{{ $name }}" name="{{ $name }}" @if (! empty($required)) required @endif>
            @foreach ($options as $value => $text)<option value="{{ $value }}" @selected(old($name, request($name)) === $value)>{{ $text }}</option>@endforeach
        </select>
    @else
        <input id="f-{{ $name }}" type="{{ $type ?? 'text' }}" name="{{ $name }}" value="{{ old($name) }}" @if (! empty($required)) required @endif @isset($placeholder) placeholder="{{ $placeholder }}" @endisset autocomplete="{{ $autocomplete ?? 'on' }}">
    @endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
