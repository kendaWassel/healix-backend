<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>
    <h1>Login</h1>
    @if($errors->any())
        <div style="color:red;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <input type="email" name="email" placeholder="البريد الإلكتروني" value="{{ old('email') }}" required><br><br>
        <input type="password" name="password" placeholder="كلمة المرور" required><br><br>
        <button type="submit">دخول</button>
    </form>
</body>
</html>
