<form class="form" method="post" action="{{ route('site.forms.contact') }}" novalidate>
    @csrf
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    @error('form')<p class="error full">{{ $message }}</p>@enderror
    @include('site.partials.field', ['name' => 'topic', 'label' => 'Topic', 'type' => 'select', 'required' => true, 'class' => 'full', 'options' => ['support' => 'Help with my configurator', 'sales' => 'Plans & pricing', 'partners' => 'Agencies & partners', 'press' => 'Press', 'privacy' => 'Privacy & data']])
    @include('site.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'autocomplete' => 'name'])
    @include('site.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
    @include('site.partials.field', ['name' => 'store', 'label' => 'Store URL', 'placeholder' => 'your-store.myshopify.com'])
    @include('site.partials.field', ['name' => 'extra', 'label' => 'Order or ticket number'])
    @include('site.partials.field', ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'class' => 'full'])
    <div class="full"><button class="btn" type="submit">Send message <span class="arrow">→</span></button></div>
</form>
