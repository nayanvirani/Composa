<form class="form" method="post" action="{{ route('site.forms.beta') }}" novalidate>
    @csrf
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    @error('form')<p class="error full">{{ $message }}</p>@enderror
    @include('site.partials.field', ['name' => 'store', 'label' => 'Store URL', 'required' => true, 'placeholder' => 'your-store.myshopify.com'])
    @include('site.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
    @include('site.partials.field', ['name' => 'shopify_plan', 'label' => 'Shopify plan', 'type' => 'select', 'options' => ['' => '—', 'basic' => 'Basic', 'shopify' => 'Shopify', 'advanced' => 'Advanced', 'plus' => 'Plus', 'dev' => 'Development store']])
    @include('site.partials.field', ['name' => 'has_variants', 'label' => 'Do your products use variants?', 'type' => 'select', 'options' => ['' => '—', 'yes' => 'Yes', 'some' => 'Some of them', 'no' => 'No']])
    <div class="field full">
        <fieldset>
            <legend>What do you sell?</legend>
            <div class="checks">
                @foreach (\App\Filament\Resources\TemplateResource::CATEGORIES as $value => $text)
                    <label><input type="checkbox" name="categories[]" value="{{ $value }}" @checked(in_array($value, old('categories', []), true))> {{ $text }}</label>
                @endforeach
            </div>
        </fieldset>
    </div>
    @include('site.partials.field', ['name' => 'current_process', 'label' => 'How do customers order custom products today?', 'type' => 'textarea', 'class' => 'full'])
    @include('site.partials.field', ['name' => 'must_have', 'label' => 'What must a configurator do for you?', 'type' => 'textarea', 'class' => 'full'])
    <div class="full"><button class="btn" type="submit">Apply for early access <span class="arrow">→</span></button></div>
</form>
