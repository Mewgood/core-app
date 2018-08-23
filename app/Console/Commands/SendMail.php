<?php

namespace App\Console\Commands;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use Illuminate\Support\Facades\Log;

class SendMail extends CronCommand
{
    protected $name = 'email:send';
    protected $description = 'Send emails to subscribers on a scheduled event';

    private $host;
    private $port;
    private $user;
    private $password;
    private $encryption;

    private $errMessage = '';
    private $email;

    public function fire()
    {
        $emails = \App\EmailSchedule::where('status', 'waiting')->get();
        if ($emails) {

            // update emails status, if we have many servers running, for not send an email many times from many servers

            // TO DO: Refactor 
            // delete this foreach
            // open up a Task with supervisor to treat async email sending
            foreach ($emails as $email) {
                $email->status = 'inProgress';
                $email->update();
            }

            foreach ($emails as $email) {

                $this->email = $email;

                $this->sendEmail();

                $email->mailingDate = gmdate('Y-m-d H:i:s');
                $email->status = 'send';
                $email->info   = 'Sended with success';

                if ($this->errMessage != '') {
                    $email->status = 'error';
                    $email->info   = $this->errMessage;
                }

                $email->update();
            }
        }
        $this->info(json_encode([
            "emails" => $emails, 
            "message" => "Scheduled emails were sent",
            "date" => gmdate('Y-m-d H:i:s')
        ]));
    }

    private function sendEmail()
    {

        // reset errors.
        $this->errMessage = '';

        if ($this->email->provider == 'site') {
            $site = \App\Site::find($this->email->sender);

            $this->host = $site->smtpHost;
            $this->port = $site->smtpPort;
            $this->user = $site->smtpUser;
            $this->password = $site->smtpPassword;
            $this->encryption = $site->smtpEncription;
        }

        if ($this->email->provider == 'packageDailyTips') {
            $this->host = getenv('EMAIL_HOST');
            $this->port = getenv('EMAIL_PORT');
            $this->user = getenv('EMAIL_USER');
            $this->password = getenv('EMAIL_PASS');
            $this->encryption = getenv('EMAIL_ENCRYPTION');
        }

        $mail = new PHPMailer(true);

        try {
            //$mail->SMTPDebug = 3;
            $mail->isSMTP();
            $mail->CharSet = 'utf-8';
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = $this->encryption;
            $mail->Port = $this->port;
            $mail->Username = $this->user;
            $mail->Password = $this->password;
            $mail->setFrom($this->email->from, $this->email->fromName);
            $mail->addAddress($this->email->to);
            $mail->addReplyTo($this->email->from);
            $mail->Subject = $this->email->subject;
            $mail->MsgHTML($this->email->body);
            $mail->isHtml(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            if (!$mail->send()) {
                $this->errMessage .= "Mailer Error: " . $mail->ErrorInfo . PHP_EOL;
            }

        } catch (phpmailerException $e) {
            $this->errMessage .= $e->errorMessage() . PHP_EOL;
        } catch (Exception $e) {
            $this->errMessage .= $e->getMessage() . PHP_EOL;
        }
    }
}