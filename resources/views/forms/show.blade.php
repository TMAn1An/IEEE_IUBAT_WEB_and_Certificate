{{--
  Public form page (and, with $previewBackUrl set, the admin preview).
  The page title is passed pre-escaped because the site layout prints
  :page-title raw (it carries entities like &mdash; elsewhere).
--}}
<x-layouts.app
    :page-title="e($presented['form']->title()).' &mdash; '.e(config('site.site.name'))"
    :page-desc="e(\Illuminate\Support\Str::limit($presented['form']->description ?? $presented['form']->title(), 150))"
    :page-url="url()->current()"
    current=""
>
<link rel="stylesheet" href="/css/forms.css">

@isset($previewBackUrl)
  <div class="ff-preview-banner">
    Preview &mdash; this is how the form looks to visitors. Submissions are disabled here.
    <a href="{{ $previewBackUrl }}">Back to the builder</a>
  </div>
@endisset

<main id="main" class="ff-page">
  @include('forms._renderer')
</main>

<x-slot:scripts>
  <script src="/js/forms/form-logic.js"></script>
  <script src="/js/forms/form-runtime.js"></script>
</x-slot:scripts>
</x-layouts.app>
