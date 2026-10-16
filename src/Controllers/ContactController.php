<?php
namespace App\Controllers;

use App\Support\Settings;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

final class ContactController
{
    public function __construct(private PDO $db, private Settings $settings) {}

    public function submit(Request $req, Response $res): Response
    {
        $d = (array) $req->getParsedBody();
        $clean = fn(string $k, int $max) => mb_substr(trim((string) ($d[$k] ?? '')), 0, $max);
        $name = $clean('name', 120);
        $email = $clean('email', 160);

        // Honeypot: pretend success so bots learn nothing.
        if ($clean('website', 200) !== '') {
            return $this->json($res, true, 'Thanks! We will get back to you within one business day.');
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json($res, false, 'Please enter your name and a valid work email.', 422);
        }

        // Rate limit: max 5 submissions per IP-less global window is too blunt; limit per email + overall flood.
        $recent = $this->db->prepare("SELECT COUNT(*) FROM messages WHERE created_at > datetime('now','-1 hour') AND (email = ? OR 1 = 0)");
        $recent->execute([$email]);
        $flood = $this->db->query("SELECT COUNT(*) FROM messages WHERE created_at > datetime('now','-1 minute')")->fetchColumn();
        if ($recent->fetchColumn() >= 3 || $flood >= 10) {
            return $this->json($res, false, 'Too many requests. Please try again later.', 429);
        }

        $row = ['name' => $name, 'company' => $clean('company', 160), 'email' => $email, 'phone' => $clean('phone', 40),
            'service' => $clean('service', 160), 'message' => $clean('message', 4000)];
        $this->db->prepare('INSERT INTO messages (name,company,email,phone,service,message) VALUES (:name,:company,:email,:phone,:service,:message)')
            ->execute($row);

        $this->notify($row);
        return $this->json($res, true, 'Thanks! We will get back to you within one business day.');
    }

    private function notify(array $r): void
    {
        $to = $_ENV['MAIL_TO'] ?? $this->settings->all()['contact_email'] ?? null;
        $dsn = $_ENV['MAIL_DSN'] ?? 'null://null';
        if (!$to) {
            return;
        }
        try {
            $mail = (new Email())
                ->from($_ENV['MAIL_FROM'] ?? $to)->to($to)->replyTo($r['email'])
                ->subject('New enquiry from ' . $r['name'] . ($r['service'] ? ' · ' . $r['service'] : ''))
                ->text(implode("\n", [
                    "Name: {$r['name']}", "Company: {$r['company']}", "Email: {$r['email']}", "Phone: {$r['phone']}",
                    "Interested in: {$r['service']}", '', $r['message'],
                ]));
            (new Mailer(Transport::fromDsn($dsn)))->send($mail);
        } catch (\Throwable $e) {
            error_log('Contact mail failed: ' . $e->getMessage()); // message is already saved in DB
        }
    }

    private function json(Response $res, bool $ok, string $message, int $status = 200): Response
    {
        $res->getBody()->write(json_encode(['ok' => $ok, 'message' => $message]));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
