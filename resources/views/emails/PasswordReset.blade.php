<!DOCTYPE html>
<html lang="en-US">
  <head>
    <meta charset="utf-8" />
  </head>
  <body>
    <h2>{{ 'Dear '.$name }}</h2>
    <p>{{ 'You are receiving this email because we received a password reset request for your account.'}}</p>
    <p>{{ 'please click button below to reset your password:'}}</p>
    <button><a href="{{ $url }}">Reset Password</a></button>
    <p>{{ 'If you did not request a password reset, no further action is required.'}}</p>
  </body>
</html>
