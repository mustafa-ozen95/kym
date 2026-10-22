{{--
    Yeni uygulama bağlama penceresi.

    PENCERE, sayfada duran bir form DEĞİL: bağlamak nadir bir iştir ve sayfanın
    asıl işi "hangi uygulamalar bağlı" sorusunu yanıtlamak. Sürekli açık bir
    form, listeyi aşağı itip ekranı kalabalıklaştırıyordu.

    Pencerenin ADRESİ vardır (?window=add): doğrulama hatasında sunucu onu
    açık basar ve kullanıcı formunu geri açmak için tıklamak zorunda kalmaz.
    Betik yüklenmese de çalışır.
--}}
<x-ui.modal id="client-add" title="Yeni uygulama bağla" :open="$window === 'add' || $errors->any()">
    <form method="POST" action="{{ route('admin.clients.store') }}">
        @csrf

        <div class="modal__body">
            <p class="text-sm text-muted">
                Bağlamak, o siteye “bu kullanıcı kim” sorusunu sorma hakkı vermektir.
                Sır burada BİR KEZ gösterilir.
            </p>

            <x-form.field name="name" label="Uygulama adı" hint="Kullanıcıya görünen ad.">
                <x-form.input name="name" required />
            </x-form.field>

            {{--
                Dönüş adresi TAM ve https olmak zorunda: Passport yalnızca
                birebir eşleşeni kabul eder ve kodun başka bir yere
                gönderilmesini engelleyen asıl koruma budur.
            --}}
            <x-form.field name="addresses" label="Dönüş adresleri"
                          hint="Her satıra bir adres; https zorunlu. Örnek: https://ornek.ornek.test/login/center/callback">
                <x-form.textarea name="addresses" class="input--mono" />
            </x-form.field>
        </div>

        <x-ui.modal-actions submit="Uygulamayı bağla" />
    </form>
</x-ui.modal>
