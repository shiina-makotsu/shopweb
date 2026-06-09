@props(['title' => null, 'wide' => false])

@include('components.layouts.app', ['title' => $title, 'wide' => $wide, 'slot' => $slot])
