{{--
  Adapter: renders public builder forms/pages inside the IEEE site layout
  (header, IEEE meta-nav and required footer links untouched), via
  config('formbuilder.public.layout') = 'formbuilder-host.public'.
  Package contract: `title`/`description` are PLAIN TEXT, so they are
  escaped here -- the site layout prints :page-title / :page-desc raw.
--}}
@props(['title' => '', 'description' => '', 'url' => null])
<x-layouts.app
    :page-title="e($title).' &mdash; '.e(config('site.site.name'))"
    :page-desc="e(\Illuminate\Support\Str::limit($description !== '' ? $description : $title, 150))"
    :page-url="$url ?? url()->current()"
    current=""
>
{{ $head ?? '' }}
{{ $slot }}
<x-slot:scripts>
{{ $scripts ?? '' }}
</x-slot:scripts>
</x-layouts.app>
