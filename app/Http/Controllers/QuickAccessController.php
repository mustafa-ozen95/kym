<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SystemResource;
use App\Models\Group;
use App\Models\System;
use App\Services\Permission\AdminSections;
use App\Services\RegistrationOpen;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hızlı Erişim sayfası.
 *
 * Yetkilendirme: sayfa bilinçli olarak herkese açıktır — sistemleri görmek
 * için giriş şart değil.
 *
 * Kimin neyi göreceği SAYFA düzeyinde değil, KUTUCUK düzeyinde ayarlanır:
 * her kutucuğun ve salonun görünürlüğü "herkes / girişli / yetkili" olabilir
 * (bkz. System::gorebilirMi). Sayfanın tamamını oturum arkasına almak
 * gerekirse rotaya `auth` eklenir; bunu yapan bir ayar YOKTUR.
 */
class QuickAccessController extends Controller
{
    /** Adresten açılabilen pencereler; başka bir değer yok sayılır. */
    private const PENCERELER = ['giris', 'kayit', 'dogrulama'];

    public function __invoke(Request $request, AdminSections $bolumler): View
    {
        return $this->sayfa($request, $bolumler, null);
    }

    /**
     * Salonun KENDİ SAYFASI.
     *
     * Bazı salonlar ana sayfaya karışmamalı: orası "hangi sisteme
     * gidiyorsun" sorusunun yanıtı, ayrı sayfalı salon ise ayrı bir iş. Ayrı
     * sayfası olan salon ana sayfada bölüm olarak çizilmez; oraya bir
     * kutucukla gidilir.
     *
     * ŞİFRE YOK: salonun görünürlüğü kutucuklarınkiyle aynı üç düzeyde ve
     * yetkisi olmayan sayfayı hiç açamaz.
     */
    public function salon(Request $request, Group $grup, AdminSections $bolumler): View
    {
        abort_unless($grup->gorebilirMi($request->user()), 404);

        return $this->sayfa($request, $bolumler, $grup);
    }

    private function sayfa(Request $request, AdminSections $bolumler, ?Group $grup): View
    {
        $salonlar = Group::sirali()->when($grup === null, fn ($q) => $q->sayfada())->get();

        $sistemler = System::icinGorunenler($request->user());

        /*
         * Kutucuk YALNIZCA çizilecek salonlardan gelir.
         *
         * Ana sayfada kendi sayfası olan salonların kutucukları gelmez: o
         * salonun bölümü çizilmediği için sahipsiz kalır ve ilk salonun altına
         * düşerlerdi.
         */
        $sistemler = $grup !== null
            ? $sistemler->where('grup', $grup->anahtar)->values()
            : $sistemler->whereIn('grup', $salonlar->pluck('anahtar'))->values();

        return view('quick-access', [
            /* Kayıt kapalıysa pencere ve bağlantı hiç çizilmez. */
            'kayitAcik' => app(RegistrationOpen::class)->acikMi(),

            'baslik' => $grup?->ad,
            'altBaslik' => $grup?->not,

            /*
             * Salon KENDİ SAYFASINDA açıldığında ana sayfaya dönüş bağlantısı.
             *
             * Kendi sayfası olan salon ana listede görünmez; dönüş bağlantısı
             * olmadan kullanıcı tarayıcının geri düğmesine mahkûm kalır ve
             * kutucuğa yeni sekmede gelmişse o da işe yaramaz.
             */
            'anaSayfa' => $grup !== null ? route('hizli-erisim') : null,
            /*
             * "Yönetim" bağlantısı, kullanıcının AÇABİLECEĞİ ilk bölüme gider.
             * Sabit adres, o bölüme yetkisi olmayan herkesi 403'e götürürdü.
             * Yetkisi hiç yoksa null döner ve bağlantı çizilmez.
             */
            'yonetimAdresi' => $request->user() ? $bolumler->ilkAdres() : null,

            /*
             * SÜZME VERİTABANINDA: "hepsini getir sonra ekranda gizle"
             * güvenlik değildir — kaynak koda bakan kutucuğun adını, adresini
             * ve hangi kabinde durduğunu yine görürdü.
             */
            'sistemler' => SystemResource::collection($sistemler),

            /*
             * Salonlar da veriden gelir ve YALNIZCA DOLU olanlar yazılır.
             *
             * Kutucuklar yetkiye göre süzülür; yetkisi olmayan için bir salon
             * tümüyle boş kalabilir ve geriye yalnız bir başlık kalırdı —
             * "burada bir şey var ama sana yok" demenin en açık hâli. Eleme sunucuda yapılır ki boş salonun adı kaynak kodda da
             * durmasın.
             */
            'gruplar' => $salonlar
                ->whereIn('anahtar', $sistemler->pluck('grup')->unique())
                ->map(fn (Group $salon): array => [
                    'id' => $salon->anahtar,

                    /*
                     * Salonun KENDİ sayfasında bölüm başlığı yazılmaz: sayfanın
                     * başlığı zaten o salonun adı; aynı satır iki kez
                     * görünmesin.
                     */
                    'label' => $grup === null ? $salon->ad : '',
                    'note' => $grup === null ? $salon->not : '',
                ])
                ->values(),

            /*
             * Pencerenin ADRESİ vardır: ?pencere=giris ile doğrudan gelinebilir.
             * Betik yüklenmese de çalışır.
             */
            'pencere' => in_array($request->query('pencere'), self::PENCERELER, true) ? $request->query('pencere') : null,
        ]);
    }

    /**
     * Hangi pencere açık gelecek?
     *
     * ADRES yetmez. Pencereyi bağlantıdan açan kullanıcının adresinde
     * ?pencere= yazmaz (betik açar); giriş başarısız olunca Laravel "geri"
     * döndürür ve o adreste pencere kapalı gelir. Böylece ör. "Hesabınız
     * beklemede" iletisi kullanıcıya hiç görünmezdi.
     *
     * Bu yüzden ikinci kaynak: DOĞRULAMA HATASI. Hata hangi pencereye aitse o
     * pencere kendini açar. Sıra önemli — "email" hem girişte hem kayıtta
     * var; kayıt yalnızca kendi alanlarıyla ayırt edilir.
     */
    private function acilacakPencere(Request $request): ?string
    {
        $hatalar = session('errors');

        if ($hatalar !== null) {
            if ($hatalar->has('kod')) {
                return 'dogrulama';
            }

            if ($hatalar->has('name') || $hatalar->has('password_confirmation')) {
                return 'kayit';
            }

            if ($hatalar->has('email') || $hatalar->has('password')) {
                return 'giris';
            }
        }

        return in_array($request->query('pencere'), self::PENCERELER, true)
            ? $request->query('pencere')
            : null;
    }
}
