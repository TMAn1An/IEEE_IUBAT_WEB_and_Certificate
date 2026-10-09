{{--
  Shared dynamic-form renderer: public page AND admin preview.
  Expects:
    $presented   App\Services\Forms\FormPresenter::present() output
    $action      submit URL (null in preview: the form can't be submitted)
    $notice      optional availability message shown instead of the form
    $success     optional success message (form is replaced by it)
  Everything user-authored is escaped with {{ }} except:
    - $presented['styleSheet']  validated/enum-mapped design CSS + scoped custom CSS (`<` escaped)
    - htmlBefore/htmlAfter/field html  sanitized by FormHtmlSanitizer at render time
  See docs/FORM_BUILDER.md §Security.
--}}
@php
  use App\Enums\FormFieldType as T;
  $form = $presented['form'];
  $hasOld = session()->hasOldInput();
@endphp
<style>{!! $presented['styleSheet'] !!}</style>
<div class="ff-form" id="{{ $presented['wrapperId'] }}">
  <h1 class="ff-title">{{ $form->title() }}</h1>
  @if ($form->description)
    <p class="ff-description">{{ $form->description }}</p>
  @endif

  @if (! empty($success))
    <div class="ff-alert ff-alert--success" role="status">{{ $success }}</div>
  @elseif (! empty($notice))
    <div class="ff-alert ff-alert--info" role="status">{{ $notice }}</div>
  @else
    @if ($presented['htmlBefore'] !== '')
      <div class="ff-custom-html ff-custom-html--before ff-html">{!! $presented['htmlBefore'] !!}</div>
    @endif

    @if ($errors->any())
      <div class="ff-alert ff-alert--error" role="alert">
        Please correct the highlighted fields.
        @if ($errors->has('form'))
          <ul><li>{{ $errors->first('form') }}</li></ul>
        @endif
      </div>
    @endif

    <form method="POST" action="{{ $action ?? '#' }}" @if (! $action) onsubmit="return false" @endif>
      @csrf
      <div class="ff-grid">
        @foreach ($presented['fields'] as $f)
          @php
            /** @var \App\Models\FormField $model */
            $model = $f['model'];
            $type = $f['type'];
            $key = $f['key'];
            $error = $errors->first($key) ?: $errors->first($key.'.*');
            $default = $model->setting('default_value');
            $value = $model->usesServerValue() ? $default : old($key, $default);
            $disabled = $model->isDisabled();
            $wrapperClasses = trim('ff-field ff-field--'.$type->value.' '.$f['widthClass'].' '.$f['cssClass'].($error ? ' has-error' : ''));
          @endphp

          @if ($type === T::Hidden)
            <input type="hidden" name="{{ $key }}" value="{{ $default }}">
            @continue
          @endif

          <div class="{{ $wrapperClasses }}" data-ff-key="{{ $key }}" @if ($f['inlineStyle'] !== '') style="{{ $f['inlineStyle'] }}" @endif>
            @switch($type)
              @case(T::Heading)
                @php $level = $f['headingLevel']; @endphp
                <{{ $level }} class="ff-heading" @if ($f['textAlign']) style="text-align:{{ $f['textAlign'] }}" @endif>{{ $model->setting('content', $model->label) }}</{{ $level }}>
                @break

              @case(T::Paragraph)
                <p class="ff-paragraph" @if ($f['textAlign']) style="text-align:{{ $f['textAlign'] }}" @endif>{{ $model->setting('content') }}</p>
                @break

              @case(T::Divider)
                <hr class="ff-divider">
                @break

              @case(T::Section)
                <div class="ff-section">
                  <h2 class="ff-section__title">{{ $model->label }}</h2>
                  @if ($model->setting('content'))
                    <p class="ff-section__desc">{{ $model->setting('content') }}</p>
                  @endif
                </div>
                @break

              @case(T::Html)
                <div class="ff-html">{!! $f['html'] !!}</div>
                @break

              @case(T::Radio)
              @case(T::CheckboxGroup)
                @php
                  $selected = $type === T::CheckboxGroup
                      ? array_map('strval', (array) ($hasOld ? old($key, []) : []))
                      : [(string) $value];
                @endphp
                <fieldset class="ff-choices {{ $model->setting('options_layout') === 'inline' ? 'ff-choices--inline' : '' }}">
                  <legend class="ff-label">{{ $model->label }}@if ($model->required)<span class="ff-req" aria-hidden="true">*</span>@endif</legend>
                  <div class="ff-choices__list">
                    @foreach ($model->options() as $i => $option)
                      <label class="ff-choice">
                        <input type="{{ $type === T::Radio ? 'radio' : 'checkbox' }}" name="{{ $f['name'] }}" value="{{ $option['value'] }}"
                          @checked(in_array((string) $option['value'], $selected, true))
                          @if ($type === T::Radio && $model->required) required @endif
                          @if ($disabled) disabled data-ff-disabled @endif>
                        <span>{{ $option['label'] }}</span>
                      </label>
                    @endforeach
                  </div>
                </fieldset>
                @break

              @case(T::Checkbox)
                @php $checked = $hasOld ? (bool) old($key) : (bool) $default; @endphp
                @if ($model->setting('checkbox_text'))
                  <span class="ff-label">{{ $model->label }}@if ($model->required)<span class="ff-req" aria-hidden="true">*</span>@endif</span>
                @endif
                <label class="ff-choice">
                  <input type="checkbox" name="{{ $key }}" value="1" @checked($checked) @if ($model->required) required @endif @if ($disabled) disabled data-ff-disabled @endif>
                  <span>{{ $model->setting('checkbox_text', $model->label) }}@if ($model->required && ! $model->setting('checkbox_text'))<span class="ff-req" aria-hidden="true">*</span>@endif</span>
                </label>
                @break

              @default
                <label class="ff-label" for="{{ $f['inputId'] }}">{{ $model->label }}@if ($model->required)<span class="ff-req" aria-hidden="true">*</span>@endif</label>
                @if ($type === T::LongText)
                  <textarea class="ff-input" id="{{ $f['inputId'] }}" name="{{ $key }}" rows="{{ $model->setting('rows', 4) }}"
                    placeholder="{{ $model->setting('placeholder') }}"
                    @if ($model->setting('max_length')) maxlength="{{ $model->setting('max_length') }}" @endif
                    @if ($model->required) required @endif @if ($model->isReadOnly()) readonly @endif
                    @if ($disabled) disabled data-ff-disabled @endif>{{ $value }}</textarea>
                @elseif ($type === T::Select)
                  <select class="ff-input" id="{{ $f['inputId'] }}" name="{{ $key }}" @if ($model->required) required @endif @if ($disabled) disabled data-ff-disabled @endif>
                    <option value="">{{ $model->setting('placeholder', 'Select…') }}</option>
                    @foreach ($model->options() as $option)
                      <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                  </select>
                @else
                  <input class="ff-input" id="{{ $f['inputId'] }}" type="{{ $type->htmlInputType() }}" name="{{ $key }}" value="{{ $value }}"
                    placeholder="{{ $model->setting('placeholder') }}"
                    @if ($model->setting('max_length')) maxlength="{{ $model->setting('max_length') }}" @endif
                    @if ($model->setting('min') !== null) min="{{ $model->setting('min') }}" @endif
                    @if ($model->setting('max') !== null) max="{{ $model->setting('max') }}" @endif
                    @if ($model->setting('step')) step="{{ $model->setting('step') }}" @elseif ($type === T::Number) step="any" @endif
                    @if ($model->required) required @endif @if ($model->isReadOnly()) readonly @endif
                    @if ($disabled) disabled data-ff-disabled @endif>
                @endif
            @endswitch

            @if ($type->acceptsUserInput() && $model->setting('help_text'))
              <p class="ff-help">{{ $model->setting('help_text') }}</p>
            @endif
            @if ($error)
              <p class="ff-error">{{ $error }}</p>
            @endif
          </div>
        @endforeach
      </div>

      <div class="ff-actions">
        <button type="submit" class="ff-button" @if (! $action) disabled title="Preview only — submissions are disabled" @endif>{{ $form->setting('submit_label', 'Submit') }}</button>
      </div>
    </form>

    @if ($presented['htmlAfter'] !== '')
      <div class="ff-custom-html ff-custom-html--after ff-html">{!! $presented['htmlAfter'] !!}</div>
    @endif

    <script type="application/json" data-ff-logic>@json($presented['logic'])</script>
  @endif
</div>
