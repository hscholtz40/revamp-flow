@php
    $value = $text ?? '';
@endphp
@if(filled($value))
{!! nl2br(e((string) $value), false) !!}
@endif
