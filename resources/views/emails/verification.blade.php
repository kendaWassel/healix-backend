<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Healix</title>
</head>
<body>
    <div>
        <h1>Welcome to Healix!</h1>
    </div>
    
    <div>
        <h2>Hello {{ $user->full_name }},</h2>
        
        <p>Thank you for registering with Healix. To complete your registration and start using our platform, please verify your email address by clicking the button below:</p>
        
        <div>
            <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>
        </div>
        
        <p>If the button doesn't work, you can also copy and paste this link into your browser:</p>
        <p style="wAord-break: break-all; background-color: #e9e9e9; padding: 10px; border-radius: 4px;">
            {{ $verificationUrl }}
        </p>
    </div>
    
</body>
</html>


