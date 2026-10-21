<?php
declare(strict_types=1);
namespace Lumina\Mail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpMailer implements MailerInterface
{
    /** @param array{host:string,port:int,username:string,password:string,encryption:string,from_email:string,from_name:string,reply_to?:string} $config */
    public function __construct(private readonly array $config)
    {
    }
    public function send(string $to, string $subject, string $textBody, array $headers = [], ?string $htmlBody = null): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->Port = $this->config['port'];
            $mail->Timeout = 20;
            $mail->CharSet = 'UTF-8';
            $mail->SMTPSecure = $this->config['encryption'] === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            if (!empty($this->config['reply_to'])) {
                $mail->addReplyTo($this->config['reply_to']);
            }
            $mail->addAddress($to);
            foreach ($headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }
            $mail->Subject = $subject;
            $mail->Body = $textBody;
            $mail->AltBody = $textBody;
            $mail->isHTML(false);
            $mail->send();
        } catch (Exception $exception) {
            throw new RuntimeException('Envoi SMTP impossible.', 0, $exception);
        }
    }
}