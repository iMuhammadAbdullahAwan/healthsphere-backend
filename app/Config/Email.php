<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail  = '';
    public string $fromName   = '';
    public string $recipients = '';

    /**
     * The "user agent"
     */
    public string $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     * Default to SMTP when SMTP_HOST is provided.
     */
    public string $protocol = '';

    /**
     * The server path to Sendmail.
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Hostname
     */
    public string $SMTPHost = '';

    /**
     * SMTP Username
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password
     */
    public string $SMTPPass = '';

    /**
     * SMTP Port
     */
    public int $SMTPPort = 25;

    /**
     * SMTP Timeout (in seconds)
     */
    public int $SMTPTimeout = 30;

    /**
     * Enable persistent SMTP connections
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption.
     *
     * @var string '', 'tls' or 'ssl'. 'tls' will issue a STARTTLS command
     *             to the server. 'ssl' means implicit SSL. Connection on port
     *             465 should set this to ''.
     */
    public string $SMTPCrypto = 'tls';

    public function __construct()
    {
        parent::__construct();

        // Load from environment when present
        $smtpHost = getenv('SMTP_HOST') ?: getenv('MAIL_HOST');
        $smtpPort = getenv('SMTP_PORT') ?: getenv('MAIL_PORT');
        $smtpUser = getenv('SMTP_USER') ?: getenv('MAIL_USERNAME');
        $smtpPass = getenv('SMTP_PASS') ?: getenv('MAIL_PASSWORD');
        $smtpFrom = getenv('SMTP_FROM') ?: getenv('MAIL_FROM_ADDRESS');
        $smtpFromName = getenv('SMTP_FROM_NAME') ?: getenv('MAIL_FROM_NAME');
        $mailDriver = getenv('MAIL_DRIVER') ?: getenv('MAIL_PROTOCOL');

        if ($smtpFrom) {
            $this->fromEmail = $smtpFrom;
        }

        if ($smtpFromName) {
            $this->fromName = $smtpFromName;
        }

        // If SMTP host supplied, configure SMTP
        if ($smtpHost) {
            $this->protocol = 'smtp';
            $this->SMTPHost = $smtpHost;
            $this->SMTPPort = (int) ($smtpPort ?: $this->SMTPPort);
            $this->SMTPUser = $smtpUser ?: $this->SMTPUser;
            $this->SMTPPass = $smtpPass ?: $this->SMTPPass;

            // Choose crypto based on port if not explicitly set
            if (empty($this->SMTPCrypto)) {
                $this->SMTPCrypto = ($this->SMTPPort === 465) ? 'ssl' : 'tls';
            }
        } else {
            // Fallback to mail if no SMTP configured
            $this->protocol = $mailDriver ?: 'mail';
        }
    }

    /**
     * Enable word-wrap
     */
    public bool $wordWrap = true;

    /**
     * Character count to wrap at
     */
    public int $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     */
    public string $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     */
    public bool $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     */
    public int $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     */
    public bool $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     */
    public int $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     */
    public bool $DSN = false;
}
