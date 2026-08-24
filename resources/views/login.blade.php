<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            background: #f4f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 2.5rem;
            max-width: 380px;
            width: 100%;
        }

        h1 {
            font-size: 1.4rem;
            margin: 0 0 1.5rem;
            text-align: center;
        }

        label {
            display: block;
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 0.3rem;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 0.65rem 0.8rem;
            margin-bottom: 1.2rem;
            border: 1px solid #dcdce3;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        input:focus {
            outline: none;
            border-color: #7b7bf0;
        }

        button {
            width: 100%;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
            cursor: pointer;
        }

        button:hover {
            background: #4338ca;
        }

        .signup-link {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.85rem;
            color: #666;
            text-decoration: none;
        }

        .signup-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1>Login</h1>
        @if ($errors->any())
        <div style="background: #fdecea; color: #c0392b; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem;">
            {{ $errors->first() }}
        </div>
        @endif
        <form method="POST" action="/login">
            @csrf
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <a href="/register" class="signup-link">Don't have an account yet? Sign up here</a>
    </div>

</body>
</html>
