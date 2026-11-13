<!doctype html>
<html lang="es">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
 <title>Iniciar sesión · Estacionamiento</title>
 @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column parking-login">
 <main class="page page-center"><div class="container container-tight login-shell py-4">
  <div class="login-identity"><span class="brand-mark"><x-icon name="parking" /></span><span class="brand-copy"><span class="brand-name">Sistema de Estacionamiento</span><span class="brand-caption">Gestión y control</span></span></div>
  <form class="card card-md login-card" action="{{ route('login.store') }}" method="POST">
   @csrf
   <div class="card-body">
    <h2 class="card-title text-center mb-2">Acceso al sistema</h2>
    <p class="text-secondary text-center mb-4">Ingresa con tu cuenta autorizada.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="mb-3"><label for="email" class="form-label">Correo electrónico</label><input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label for="password" class="form-label">Contraseña</label><input id="password" class="form-control" type="password" name="password" required autocomplete="current-password"></div>
    <label class="form-check"><input type="checkbox" class="form-check-input" name="remember" value="1" @checked(old('remember'))><span class="form-check-label">Recordarme</span></label>
    <div class="form-footer"><button type="submit" class="btn btn-primary w-100">Ingresar</button></div>
   </div>
  </form>
  <p class="login-footnote">Acceso exclusivo para personal autorizado</p>
 </div></main>
</body></html>
