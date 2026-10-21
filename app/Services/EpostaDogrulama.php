<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ayar;
use App\Models\User;
use App\Services\Posta\PostaAyarlari;

/**
 * "E-posta doğrulanmış sayılır mı?" sorusunun TEK yanıtı.
 *
 * VARSAYILAN: doğrulama zorunlu DEĞİL. Merkezde kimseye doğrulama postası
 * gitmez; hesabı bir insan inceleyip etkinleştirir ve onayı veren kişi zaten
 * adresin o kişiye ait olduğuna kefil olur. Şart olan yönetici onayıdır.
 *
 * SONRADAN AÇILABİLİR: kurulum büyüyüp onay veren kişi adresi tanımaz hâle
 * geldiğinde ayar açılır; o andan sonra adresi kanıtlayan şey yöneticinin
 * kefaleti değil, kullanıcının tıkladığı bağlantı olur.
 *
 * KURAL BURADA DURUR, KOPYALANMAZ. Aynı bilgiyi yazan iki yol var —
 * yöneticinin onayı ve kullanıcının tıkladığı bağlantı — ve ikisi de buradan
 * geçer. Kural kopyalansaydı yollardan biri sessizce eski davranışta kalırdı.
 */
class EpostaDogrulama
{
    public const AYAR = 'eposta_dogrulama_zorunlu';

    public function __construct(private PostaAyarlari $posta) {}

    public function zorunluMu(): bool
    {
        return Ayar::bool(self::AYAR);
    }

    /**
     * Ayar AÇILABİLİR mi?
     *
     * Posta gidemiyorken zorunluluk açılırsa, doğrulama bağlantısı kimseye
     * ulaşmaz ve yeni kayıtların tamamı sessizce kilitlenir — üstelik bunu
     * fark edecek kimse olmaz, çünkü kilitlenen kişi zaten içeri giremiyordur.
     */
    public function acilabilirMi(): bool
    {
        return $this->posta->sinandiMi();
    }

    /**
     * Yönetici onayı adresi doğrulanmış sayar MI?
     *
     * Zorunluluk kapalıyken evet: onayı veren kişi kefildir. Açıkken hayır —
     * yoksa ayar hiçbir şey değiştirmezdi.
     */
    public function onayDogrularMi(): bool
    {
        return ! $this->zorunluMu();
    }

    /** Bu hesap, doğrulama açısından girebilir durumda mı? */
    public function girebilirMi(User $kullanici): bool
    {
        return ! $this->zorunluMu() || $kullanici->email_verified_at !== null;
    }
}
