{{-- Deliberately standalone rather than <x-guest-layout>: that layout is the
     stock Tailwind/Breeze shell, and the rest of this app (assistant, admin,
     product sheet) is Bootstrap on the Turkuaz palette. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş yap · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-900: #04415f;
            --brand-700: #095a82;
            --brand-500: #0e7fb3;
            --brand-100: #e7f2f8;
            --ink: #17242c;
            --muted: #6b7d88;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem 1rem;
            color: var(--ink);
            background:
                radial-gradient(1000px 500px at 15% -10%, #d9ebf5 0%, rgba(217,235,245,0) 60%),
                radial-gradient(850px 450px at 88% 8%, #e4f0f6 0%, rgba(228,240,246,0) 55%),
                #f2f5f7;
            background-attachment: fixed;
        }

        .login-card {
            width: 100%;
            max-width: 880px;
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 24px 50px -28px rgba(4, 65, 95, .55), 0 2px 6px rgba(4, 65, 95, .06);
        }

        /* Brand panel — hidden on small screens, where it would only push the
           form below the fold. */
        .brand-panel {
            background: linear-gradient(150deg, var(--brand-900), var(--brand-700) 55%, var(--brand-500));
            color: #fff;
            padding: 2.5rem 2rem;
        }
        .brand-mark {
            width: 52px; height: 52px;
            display: grid; place-items: center;
            border-radius: 16px;
            font-size: 1.6rem;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.28);
        }
        .brand-point {
            display: flex; align-items: flex-start; gap: .6rem;
            font-size: .9rem;
            opacity: .92;
        }
        .brand-point i { margin-top: .15rem; }

        .form-label { font-weight: 500; font-size: .88rem; }

        .input-group-text {
            background: #fff;
            border-right: 0;
            color: var(--muted);
        }
        .form-control {
            border-left: 0;
            padding-left: 0;
        }
        .form-control:focus {
            border-color: #dbe4ea;
            box-shadow: none;
        }
        /* The ring belongs on the whole group, not the bare input, or it would
           draw between the icon and the field. */
        .input-group:focus-within {
            border-radius: .375rem;
            box-shadow: 0 0 0 .2rem rgba(14, 127, 179, .15);
        }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control { border-color: var(--brand-500); }

        .btn-brand {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border: 0;
            color: #fff;
            font-weight: 500;
            padding: .6rem 1rem;
            transition: transform .12s ease, opacity .15s ease;
        }
        .btn-brand:hover { color: #fff; transform: translateY(-1px); }
        .btn-brand:disabled { opacity: .6; transform: none; }

        /* Bootstrap's default checkbox is 1em and easy to miss — this one is a
           real target, and shows the brand colour when ticked. */
        .form-check-input {
            width: 1.15rem; height: 1.15rem;
            margin-top: .1rem;
            cursor: pointer;
            border-color: #c6d3dc;
        }
        .form-check-input:checked {
            background-color: var(--brand-500);
            border-color: var(--brand-500);
        }
        .form-check-input:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 .2rem rgba(14, 127, 179, .2);
        }
        .form-check-label { cursor: pointer; user-select: none; }

        .link-brand { color: var(--brand-700); text-decoration: none; }
        .link-brand:hover { color: var(--brand-500); text-decoration: underline; }

        .toggle-password {
            border: 1px solid #dee2e6;
            border-left: 0;
            background: #fff;
            color: var(--muted);
        }
        .toggle-password:hover { color: var(--brand-700); }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="row g-0">
            <div class="col-lg-5 brand-panel d-none d-lg-flex flex-column justify-content-between">
                <div>
                    <div class="brand-mark mb-4"><i class="bi bi-robot"></i></div>
                    <h1 class="h4 fw-semibold mb-2">Cera · Ürün Asistanı</h1>
                    <p class="small mb-0" style="opacity:.85">
                        Turkuaz Seramik ürün kataloğuna erişmek için hesabınıza giriş yapın.
                    </p>
                </div>

                <div class="d-grid gap-3 mt-4">
                    <div class="brand-point">
                        <i class="bi bi-search"></i>
                        <span>Seri, ölçü ve renge göre ürün arayın</span>
                    </div>
                    <div class="brand-point">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Teknik doküman ve montaj kılavuzlarına ulaşın</span>
                    </div>
                    <div class="brand-point">
                        <i class="bi bi-upc-scan"></i>
                        <span>Bayi hesabıyla ürün kodlarını görüntüleyin</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 p-4 p-lg-5">
                <div class="d-lg-none d-flex align-items-center gap-2 mb-4">
                    <i class="bi bi-robot fs-4" style="color: var(--brand-700)"></i>
                    <span class="fw-semibold">Cera · Ürün Asistanı</span>
                </div>

                <h2 class="h5 fw-semibold mb-1">Tekrar hoş geldiniz</h2>
                <p class="text-muted small mb-4">Devam etmek için giriş yapın.</p>

                @if (session('status'))
                    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small d-flex gap-2">
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input id="email" name="email" type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}"
                                   placeholder="ornek@turkuazseramik.com"
                                   required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label">Parola</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="small link-brand">
                                    Parolamı unuttum
                                </a>
                            @endif
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="password" name="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="••••••••"
                                   required autocomplete="current-password">
                            <button class="btn toggle-password" type="button" id="toggle-password"
                                    aria-label="Parolayı göster">
                                <i class="bi bi-eye" id="toggle-password-icon"></i>
                            </button>
                        </div>
                    </div>

                    {{-- name="remember" is what LoginRequest reads via
                         $this->boolean('remember') to pass into Auth::attempt(). --}}
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember_me"
                               value="1" @checked(old('remember'))>
                        <label class="form-check-label small" for="remember_me">
                            Beni hatırla
                            <span class="text-muted d-block" style="font-size:.78rem">
                                Bu cihazda oturumum açık kalsın
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Giriş yap
                    </button>
                </form>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <a href="{{ route('assistant.index') }}" class="small link-brand">
                        <i class="bi bi-arrow-left me-1"></i> Asistana dön
                    </a>
                    @if (Route::has('register'))
                        <span class="small text-muted">
                            Hesabınız yok mu?
                            <a href="{{ route('register') }}" class="link-brand">Kayıt olun</a>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');
        const icon = document.getElementById('toggle-password-icon');

        toggle.addEventListener('click', function () {
            const revealed = password.type === 'text';
            password.type = revealed ? 'password' : 'text';
            icon.className = revealed ? 'bi bi-eye' : 'bi bi-eye-slash';
            toggle.setAttribute('aria-label', revealed ? 'Parolayı göster' : 'Parolayı gizle');
            password.focus();
        });
    </script>
</body>
</html>
