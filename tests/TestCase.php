<?php

namespace Tests;

use App\Models\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * YETKİ DAĞITIMINI AÇAR — varsayılanda KAPALIDIR.
     *
     * Merkezin yönetimi yalnızca yöneticide (bkz. config/permissions.php ·
     * yonetim_yalnizca_yonetici). Dağıtım düzeneği silinmedi, kapatıldı:
     * rol şablonu, kişisel izin/red ve yetki ekranları yerinde duruyor.
     *
     * Kapalı diye SINANMAZSA sessizce çürür ve yeniden açıldığı gün kimse
     * güvenemez. Bu yüzden düzeneği sınayan testler ayarı burada açıyor;
     * kapalı hâlin kendisi ayrı testlerde sınanıyor (PermissionTest ve
     * AdminTest'teki "yalnızca yönetici" testleri).
     *
     * İkinci iş, örnek rolü hazırlamak: yerleşik rollerin şablonları boş
     * (göç 2026_09_15_210000). Varsayılan şablona dayanan test varsayılan
     * değişince kırılırdı; okuma yetkisini testin kendisi versin.
     */
    protected function yetkiDagitimiAc(string $ornekRol = 'network', array $yetkiler = ['sistem.gor', 'grup.gor']): void
    {
        config(['permissions.admin_only_management' => false]);

        Role::where('slug', $ornekRol)->first()?->update(['template' => $yetkiler]);
    }
}
