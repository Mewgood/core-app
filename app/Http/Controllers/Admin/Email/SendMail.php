<?php

namespace App\Http\Controllers\Admin\Email;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class SendMail extends Controller
{
    public function __construct($args)
    {

        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = 3;
            $mail->isSMTP();
            $mail->CharSet = 'utf-8';
            $mail->Host = $args['host'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'tls';
            $mail->Port = $args['port'];
            $mail->Username = $args['user'];
            $mail->Password = $args['pass'];
            $mail->setFrom($args['from'], $args['fromName']);
            $mail->addAddress($args['to']);
            $mail->addReplyTo($args['from']);
            $mail->Subject = $args['subject'];
            $mail->MsgHTML($args['body']);
            $mail->isHtml(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            if (!$mail->send()) {
                echo "Mailer Error: " . $mail->ErrorInfo . PHP_EOL;
            }

        } catch (\Exception $e) {
            dd($e->getMessage());
        }

        dd('success');
    }
}

