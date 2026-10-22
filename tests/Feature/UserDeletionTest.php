<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Jobs\NotifyIdentityChangeJob;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kullanıcı silme: YALNIZCA YÖNETİCİ, kendini silemez; denetim zinciri
 * kırılmaz, jetonlar gider, projeler haber alır.
 */
class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function rolde(string $slug): User
    {
        return User::factory()->rol($slug)->create(['durum' => Status::Etkin]);
    }

    public function test_yonetici_kullaniciyi_siler_zincir_saglam_kalir(): void
    {
        Queue::fake();
        $recorder = app(ActivityRecorder::class);
        $hedef = $this->rolde('muhasebe');
        $satir = $recorder->record('deneme', 'hedefin isi', $hedef);
        $jeton = Str::random(40);
        DB::table('oauth_access_tokens')->insert(['id' => $jeton, 'user_id' => $hedef->id, 'client_id' => (string) Str::uuid(), 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addHour()]);
        DB::table('oauth_refresh_tokens')->insert(['id' => Str::random(40), 'access_token_id' => $jeton, 'revoked' => false, 'expires_at' => now()->addDay()]);

        $this->actingAs($this->rolde('admin'))
            ->delete(route('admin.users.destroy', $hedef))
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('users', ['id' => $hedef->id]);
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('user_id', $hedef->id)->count());
        $this->assertSame(0, DB::table('oauth_refresh_tokens')->where('access_token_id', $jeton)->count());
        $this->assertSame($hedef->id, ActivityLog::find($satir->id)->user_id, 'silinenin satırı olduğu gibi kalmalı');
        $this->assertTrue($recorder->verify()['ok']);
        $this->assertTrue(ActivityLog::where('eylem', 'kullanici-silindi')->exists());
        Queue::assertPushed(NotifyIdentityChangeJob::class, fn ($is) => $is->subject === (string) $hedef->uuid && ! $is->kapsamli);
    }

    public function test_yonetici_olmayan_silemez(): void
    {
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($this->rolde('teknik'))
            ->delete(route('admin.users.destroy', $hedef))
            ->assertForbidden();

        $this->assertNotNull($hedef->fresh());
    }

    public function test_yonetici_kendini_silemez(): void
    {
        $admin = $this->rolde('admin');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_sil_dugmesi_yalniz_yoneticiye_ve_baskalarinda_gorunur(): void
    {
        $admin = $this->rolde('admin');
        $hedef = $this->rolde('muhasebe');

        $this->actingAs($admin)->get(route('admin.users'))
            ->assertOk()
            ->assertSee('action="'.route('admin.users.destroy', $hedef).'"', false)
            ->assertDontSee('action="'.route('admin.users.destroy', $admin).'"', false);
    }
}
