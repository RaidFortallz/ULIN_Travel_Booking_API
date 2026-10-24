<p>Hi {{ $user->name }},</p>
<p>Your Ulin password reset code is:</p>
<h2>{{ $code }}</h2>
<p>This code expires in {{ $ttlMinutes }} minutes. If you did not request a password reset, you can ignore this email.</p>