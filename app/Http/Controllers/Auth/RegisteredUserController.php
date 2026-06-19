<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        [$question, $answer] = $this->captchaChallenge();
        session(['register_captcha_answer' => $answer]);

        return view('auth.register', [
            'settings' => SiteSetting::query()->first(),
            'captchaQuestion' => $question,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'public_id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_]+$/', 'not_regex:/^staff_/i', 'unique:users,public_id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'captcha_answer' => ['required', 'integer'],
        ]);

        if ((int) $data['captcha_answer'] !== (int) session('register_captcha_answer')) {
            throw ValidationException::withMessages([
                'captcha_answer' => '人机验证答案不正确。',
            ]);
        }

        $user = User::query()->create([
            'public_id' => $data['public_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'avatar_path' => $request->hasFile('avatar')
                ? $request->file('avatar')->store('avatars', 'public_uploads')
                : null,
        ]);

        $request->session()->forget('register_captcha_answer');
        $request->session()->flash('show_registration_onboarding', true);
        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('home');
    }

    /**
     * @return array{0:string,1:int}
     */
    private function captchaChallenge(): array
    {
        $left = random_int(2, 9);
        $right = random_int(2, 9);

        return ["{$left} + {$right} = ?", $left + $right];
    }
}
