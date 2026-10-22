<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\ActivityRecorder;
use App\Services\PasswordRule;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kullanıcının kendi hesabı.
 *
 * Yetkilendirme: herkes YALNIZCA kendi hesabını düzenler. Kullanıcı
 * oturumdan gelir, adresten değil — başkasının profiline giden bir yol yok.
 *
 * ROL VE DURUM BURADA DEĞİŞTİRİLEMEZ: kendi rolünü yükseltmek ya da askıdan
 * çıkmak bu ekranın işi değil. O karar yöneticide durur.
 */
class ProfileController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('auth')];
    }

    public function index(#[CurrentUser] $kullanici): View
    {
        return view('account.profile', ['kullanici' => $kullanici]);
    }

    public function update(Request $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($kullanici)],
        ], attributes: ['name' => 'ad', 'email' => 'e-posta']);

        $eskiEposta = $kullanici->email;
        $kullanici->update($veri);

        /* E-posta kimliğin kendisi: değiştiğinde kayda geçmeli. */
        if ($eskiEposta !== $kullanici->email) {
            $recorder->record('eposta-degisti', $eskiEposta.' → '.$kullanici->email, $kullanici);
        }

        return back()->with('status', 'Bilgileriniz kaydedildi.');
    }

    public function password(Request $request, ActivityRecorder $recorder, #[CurrentUser] $kullanici): RedirectResponse
    {
        $veri = $request->validate([
            /* Mevcut parola şart: oturumu ele geçiren biri parolayı değiştirip hesabı devralamasın. */
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::kural()],
        ], attributes: ['current_password' => 'mevcut parola', 'password' => 'yeni parola']);

        $kullanici->update(['password' => $veri['password']]);

        /*
         * Parola değişince DİĞER OTURUMLAR DÜŞER.
         *
         * Parola değiştirmenin asıl sebeplerinden biri "başkası girmiş
         * olabilir" şüphesidir; eski oturumlar açık kalsaydı değişiklik o
         * kişiyi dışarı atmazdı.
         */
        Auth::logoutOtherDevices($veri['password']);
        $request->session()->regenerate();

        $recorder->record('parola-degisti', $kullanici->email.' parolasını değiştirdi', $kullanici);

        return back()->with('status', 'Parolanız değiştirildi; diğer oturumlarınız kapatıldı.');
    }
}
