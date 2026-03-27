@php($href = $url())
@if($href !== '')
    <a href="{{ $href }}"
       class="inline-flex items-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
        {{ $label }}
    </a>
@endif
