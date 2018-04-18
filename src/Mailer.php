<?php namespace Nymphaion\Mail;

use Exception;

/**
 * Class Smtp
 */
class Smtp
{
    protected $smtpHostname;
    protected $smtpUsername;
    protected $smtpPassword;
    protected $smtpPort = 25;

    protected $to;
    protected $replyTo;
    protected $sender;
    protected $from;
    protected $html;
    protected $text;
    protected $subject = '';
    protected $attachments = [];

    const SMTP_TIMEOUT = 5;
    const MAX_ATTEMPTS = 3;

    /**
     * Mailer constructor.
     *
     * @param string $smtpHostname
     * @param string $smtpUsername
     * @param string $smtpPassword
     * @param int $smtpPort
     */
    public function __construct(string $smtpHostname, string $smtpUsername, string $smtpPassword, $smtpPort = 25)
    {
        $this->smtpHostname = $smtpHostname;
        $this->from = $this->smtpUsername = $smtpUsername;
        $this->smtpPassword = $smtpPassword;
        $this->smtpPort = $smtpPort;
    }

    /**
     * @param $handle
     * @param int|array $expectedStatusCode
     * @param string $message
     * @param int $counter
     * @return string
     * @throws Exception
     */
    protected function handleReply($handle, $expectedStatusCode, string $message, int $counter = 0)
    {
        $expectedStatusCodes = (array)$expectedStatusCode;
        $reply = '';

        fputs($handle, $message . "\r\n");

        while (($line = fgets($handle, 1024)) !== false) {
            $reply .= $line;

            //some SMTP servers respond with 220 code before responding with 250. hence, we need to ignore 220 response string
            if (substr($reply, 0, 3) == 220 && substr($line, 3, 1) == ' ') {
                $reply = '';
                continue;
            } else {
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
        }

        // Handle slowish server responses (generally due to policy servers)
        if (!$line && empty($reply) && $counter < static::MAX_ATTEMPTS) {
            sleep(1);
            $counter++;

            return $this->handleReply($handle, $expectedStatusCodes, $message, $counter);
        }

        $errorCode = (int)substr($reply, 0, 3);

        if (!in_array($errorCode, $expectedStatusCodes)) {
            throw new \Exception('Error: [' . $message . '] not accepted from server!', $errorCode, new \Exception(substr($reply, 3), $errorCode));
        }

        return $reply;
    }

    /**
     * @param array|string $to
     * @return $this
     */
    public function to($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * @param string $replyTo
     * @return $this
     */
    public function replyTo(string $replyTo)
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @param string $sender
     * @return $this
     */
    public function sender(string $sender)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * @param string $subject
     * @return $this
     */
    public function subject(string $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param string $html
     * @return $this
     */
    public function html(string $html)
    {
        $this->html = $html;

        return $this;
    }

    /**
     * @param string $text
     * @return $this
     */
    public function text(string $text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function attachment(string $filename)
    {
        $this->attachments[] = $filename;

        return $this;
    }

    /**
     * @param array $files
     * @return $this
     */
    public function attachments(array $files)
    {
        foreach ($files as $file) {
            $this->attachment($file);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function send()
    {
        if (is_array($this->to)) {
            $to = implode(',', $this->to);
        } else {
            $to = $this->to;
        }

        if (!$this->subject) {
            $this->subject = '[no-subject]';
        }

        $boundary = '----=_NextPart_' . md5(time());

        $header = 'MIME-Version: 1.0' . PHP_EOL;
        $header .= 'To: <' . $to . '>' . PHP_EOL;
        $header .= 'Subject: =?UTF-8?B?' . base64_encode($this->subject) . '?=' . PHP_EOL;
        $header .= 'Date: ' . date('D, d M Y H:i:s O') . PHP_EOL;
        $header .= 'From: =?UTF-8?B?' . base64_encode($this->sender) . '?= <' . $this->from . '>' . PHP_EOL;

        if (!$this->replyTo) {
            $header .= 'Reply-To: =?UTF-8?B?' . base64_encode($this->sender) . '?= <' . $this->from . '>' . PHP_EOL;
        } else {
            $header .= 'Reply-To: =?UTF-8?B?' . base64_encode($this->replyTo) . '?= <' . $this->replyTo . '>' . PHP_EOL;
        }

        $header .= 'Return-Path: ' . $this->from . PHP_EOL;
        $header .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;
        $header .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . PHP_EOL . PHP_EOL;

        if (!$this->html) {
            $message = '--' . $boundary . PHP_EOL;
            $message .= 'Content-Type: text/plain; charset="utf-8"' . PHP_EOL;
            $message .= 'Content-Transfer-Encoding: 8bit' . PHP_EOL . PHP_EOL;
            $message .= $this->text . PHP_EOL;
        } else {
            $message = '--' . $boundary . PHP_EOL;
            $message .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '_alt"' . PHP_EOL . PHP_EOL;
            $message .= '--' . $boundary . '_alt' . PHP_EOL;
            $message .= 'Content-Type: text/plain; charset="utf-8"' . PHP_EOL;
            $message .= 'Content-Transfer-Encoding: 8bit' . PHP_EOL . PHP_EOL;

            if ($this->text) {
                $message .= $this->text . PHP_EOL;
            } else {
                $message .= 'This is a HTML email and your email client software does not support HTML email!' . PHP_EOL;
            }

            $message .= '--' . $boundary . '_alt' . PHP_EOL;
            $message .= 'Content-Type: text/html; charset="utf-8"' . PHP_EOL;
            $message .= 'Content-Transfer-Encoding: 8bit' . PHP_EOL . PHP_EOL;
            $message .= $this->html . PHP_EOL;
            $message .= '--' . $boundary . '_alt--' . PHP_EOL;
        }

        foreach ($this->attachments as $attachment) {
            if (file_exists($attachment)) {
                $handle = fopen($attachment, 'r');

                $content = fread($handle, filesize($attachment));

                fclose($handle);

                $message .= '--' . $boundary . PHP_EOL;
                $message .= 'Content-Type: application/octet-stream; name="' . basename($attachment) . '"' . PHP_EOL;
                $message .= 'Content-Transfer-Encoding: base64' . PHP_EOL;
                $message .= 'Content-Disposition: attachment; filename="' . basename($attachment) . '"' . PHP_EOL;
                $message .= 'Content-ID: <' . urlencode(basename($attachment)) . '>' . PHP_EOL;
                $message .= 'X-Attachment-Id: ' . urlencode(basename($attachment)) . PHP_EOL . PHP_EOL;
                $message .= chunk_split(base64_encode($content));
            }
        }

        $message .= '--' . $boundary . '--' . PHP_EOL;

        if (substr($this->smtpHostname, 0, 3) == 'tls') {
            $hostname = substr($this->smtpHostname, 6);
        } else {
            $hostname = $this->smtpHostname;
        }

        $handle = fsockopen($hostname, $this->smtpPort, $errno, $errstr, static::SMTP_TIMEOUT);

        if (!$handle) {
            throw new \Exception('Error: ' . $errstr . ' (' . $errno . ')');
        } else {
            if (substr(PHP_OS, 0, 3) != 'WIN') {
                socket_set_timeout($handle, static::SMTP_TIMEOUT, 0);
            }

            while ($line = fgets($handle, 1024)) {
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }

            $this->handleReply($handle, 250, 'EHLO ' . getenv('SERVER_NAME'));

            if (substr($this->smtpHostname, 0, 3) == 'tls') {
                $this->handleReply($handle, 220, 'STARTTLS');

                stream_socket_enable_crypto($handle, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }

            if (!empty($this->smtpUsername) && !empty($this->smtpPassword)) {
                //$this->handleReply($handle, 250, 'EHLO ' . getenv('SERVER_NAME'));
                $this->handleReply($handle, 334, 'AUTH LOGIN');
                $this->handleReply($handle, 334, base64_encode($this->smtpUsername));
                $this->handleReply($handle, 235, base64_encode($this->smtpPassword));
            } else {
                $this->handleReply($handle, 250, 'HELO ' . getenv('SERVER_NAME'));
            }

            $this->handleReply($handle, 250, 'MAIL FROM: <' . $this->from . '>');

            foreach ((array)$this->to as $recipient) {
                $this->handleReply($handle, [250, 251], 'RCPT TO: <' . $recipient . '>');
            }

            $this->handleReply($handle, 354, 'DATA');

            // According to rfc 821 we should not send more than 1000 including the CRLF
            $message = str_replace("\r\n", "\n", $header . $message);
            $message = str_replace("\r", "\n", $message);

            $lines = explode("\n", $message);

            foreach ($lines as $line) {
                $results = str_split($line, 998);

                foreach ($results as $result) {
                    if (substr(PHP_OS, 0, 3) != 'WIN') {
                        fputs($handle, $result . "\r\n");
                    } else {
                        fputs($handle, str_replace("\n", "\r\n", $result) . "\r\n");
                    }
                }
            }

            $this->handleReply($handle, 250, '.');
            $this->handleReply($handle, 221, 'QUIT');

            fclose($handle);
        }
    }
}