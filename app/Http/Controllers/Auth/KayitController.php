<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Durum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\KayitRequest;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\EpostaDogrulamaBildirimi;
use App\Services\ActivityRecorder;
use App\Services\EpostaDogrulama;
use App\Services\KayitAcik;
use Illuminate\Http\RedirectResponse;

/**
 * Kayıt.
 *
 * Yetkilendirme: oturumsuzlara açıktır — bilinçli. Güvenliği sağlayan şey
 * kaydın kapalı olması değil, hesabın BEKLEMEDE doğmasıdır.
 *
 * Kayıt olan kullanıcı İÇERİ ALINMAZ. Alınsaydı "yönetici onaylayana kadar
 * erişemezsiniz" kuralı ekranda yalan söylerdi.
 */
class KayitController extends Controller
{
    public function store(
        KayitRequest $request,
        ActivityRecorder $recorder,
        EpostaDogrulama $dogrulama,
        KayitAcik $kayit,
    ): RedirectResponse {
        /*
         * KAYIT KAPALIYSA burada durur.
         *
         * Ekrandaki bağlantı da çizilmiyor ama uç açık kalsaydı adresi bilen
         * biri yine hesap açabilirdi — "gizlemek" kapatmak değildir.
         */
        abort_unless($kayit->acikMi(), 404);

        $kullanici = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            /*
             * Varsayılan rol yoksa hesap ROLSÜZ doğar ve hiçbir şey yapamaz.
             * Eksik yapılandırma yetki üretmemeli.
             */
            'rol_id' => Rol::varsayilan()?->id,
        ]);

        /*
         * Durum toplu atamaya kapalı (bkz. User::$fillable): açıkça yazılır.
         * Kayıt olan hiç kimse kendini etkinleştiremez.
         */
        $kullanici->durumuAyarla(Durum::Beklemede);

        $recorder->record('kayit', $kullanici->email.' kayıt oldu (onay bekliyor)', $kullanici);

        /*
         * ŞART OLAN YÖNETİCİ ONAYIDIR; doğrulama postası isteğe bağlıdır.
         *
         * Kapalıyken kimseye posta gitmez — kurulumun posta sunucusu
         * olmayabilir ve olmadığında kayıt akışı çalışmaya devam etmeli.
         * Açıkken bağlantı gider ve onay TEK BAŞINA yetmez.
         */
        if ($dogrulama->zorunluMu()) {
            $kullanici->notify(new EpostaDogrulamaBildirimi);

            return redirect()
                ->route('hizli-erisim')
                ->with('status', 'Hesabınız açıldı. Adresinize gönderdiğimiz bağlantıyı onaylayın; yönetici onayından sonra giriş yapabilirsiniz.');
        }

        return redirect()
            ->route('hizli-erisim')
            ->with('status', 'Hesabınız açıldı. Yönetici onayladıktan sonra giriş yapabilirsiniz.');
    }
}
