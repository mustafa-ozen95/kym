{{--
    Yeni uygulama bağlama penceresi.

    PENCERE, sayfada duran bir form DEĞİL: bağlamak nadir bir iştir ve sayfanın
    asıl işi "hangi uygulamalar bağlı" sorusunu yanıtlamak. Sürekli açık bir
    form, listeyi aşağı itip ekranı kalabalıklaştırıyordu.

    Pencerenin ADRESİ vardır (?pencere=ekle): doğrulama hatasında sunucu onu
    açık basar ve kullanıcı formunu geri açmak için tıklamak zorunda kalmaz.
    Betik yüklenmese de çalışır.
--}}
<x-ui.modal id="istemci-ekle" title="Yeni uygulama bağla" :open="$pencere === 'ekle' || $errors->any()">
    <form method="POST" action="{{ route('yonetim.istemciler.ekle') }}">
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
            <x-form.field name="adresler" label="Dönüş adresleri"
                          hint="Her satıra bir adres; https zorunlu. Örnek: https://ornek.ornek.test/giris/merkez/geri">
                <x-form.textarea name="adresler" class="input--mono" />
            </x-form.field>
        </div>

        <x-ui.modal-actions submit="Uygulamayı bağla" />
    </form>
</x-ui.modal>
