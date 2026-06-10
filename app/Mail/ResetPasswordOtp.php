<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtp extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function build()
    {
        return $this->subject('Kode OTP Reset Password - Bank Sampah')
                    ->html("
                        <h3>Halo!</h3>
                        <p>Anda telah meminta untuk mereset kata sandi Anda.</p>
                        <p>Berikut adalah kode OTP Anda: <strong style='font-size: 24px; color: #006948;'>{$this->otp}</strong></p>
                        <p>Kode ini hanya berlaku selama <strong>10 menit</strong>. Jangan berikan kode ini kepada siapa pun.</p>
                    ");
    }
}