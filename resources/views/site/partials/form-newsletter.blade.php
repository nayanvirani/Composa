<form class="newsletter" method="post" action="{{ route('site.forms.newsletter') }}" style="max-width:480px">
    @csrf
    <input type="hidden" name="source" value="page">
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label class="hp" for="nl-page-email">Email</label>
    <input id="nl-page-email" type="email" name="email" placeholder="you@store.com" required>
    <button type="submit">Subscribe</button>
</form>
