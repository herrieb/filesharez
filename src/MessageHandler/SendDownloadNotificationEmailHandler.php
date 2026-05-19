<?php

namespace App\MessageHandler;

use App\Message\SendDownloadNotificationEmail;
use App\Repository\TransferRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class SendDownloadNotificationEmailHandler
{
    public function __construct(
        private TransferRepository $transferRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendDownloadNotificationEmail $message): void
    {
        $transfer = $this->transferRepository->find($message->transferId);
        if (!$transfer) {
            return;
        }

        $transferUrl = $this->urlGenerator->generate(
            'app_dashboard',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $fileCount = $transfer->getFileCount();
        $subject = $fileCount === 1
            ? 'Your file was downloaded'
            : 'Your ' . $fileCount . ' files were downloaded';

        $email = (new TemplatedEmail())
            ->to($transfer->getUser()->getEmail())
            ->subject($subject)
            ->htmlTemplate('emails/download_notification.html.twig')
            ->context([
                'transfer' => $transfer,
                'transferUrl' => $transferUrl,
                'fileCount' => $fileCount,
                'downloaderEmail' => $message->downloaderEmail,
                'downloaderName' => $message->downloaderName,
            ]);

        $this->mailer->send($email);
    }
}