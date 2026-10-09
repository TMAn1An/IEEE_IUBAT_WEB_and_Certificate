{{--
  Adapter: renders Form/Page Builder admin screens inside the IEEE admin
  layout (config('formbuilder.admin.layout') = 'formbuilder-host.admin').
  Package contract: `title`, `wide`, and a `head` slot.
--}}
@props(['title' => 'Builder', 'wide' => false])
<x-layouts.admin :title="$title" :wide="$wide">
  <x-slot:head>{{ $head ?? '' }}</x-slot:head>
  {{ $slot }}
</x-layouts.admin>
