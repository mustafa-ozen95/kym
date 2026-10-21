<x-layout.panel title="Erişim izni">
    <div class="page">
        <div class="page__inner">
            <x-ui.page-head :title="$client->name.' erişim istiyor'"
                            subtitle="Bu uygulamaya kimliğinizin doğrulanması için izin veriyorsunuz." />

            <x-ui.panel>
                <p class="text-sm text-muted">
                    İzin verirseniz <strong>{{ $client->name }}</strong> yalnızca adınızı ve e-posta adresinizi
                    öğrenir. Parolanız o uygulamaya hiçbir zaman gitmez.
                </p>

                <div class="row row--end row--ustu-bosluklu">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn">Vazgeç</button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn btn--primary">İzin ver</button>
                    </form>
                </div>
            </x-ui.panel>
        </div>
    </div>
</x-layout.panel>
