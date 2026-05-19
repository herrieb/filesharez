<?php

namespace App\MessageHandler;

use App\Message\SendFileRequestUploadEmail;
use App\Repository\TransferRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class SendFileRequestUploadEmailHandler
{
    public function __construct(
        private TransferRepository $transferRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendFileRequestUploadEmail $message): void
    {
        $transfer = $this->transferRepository->find($message->transferId);
        if (!$transfer) {
            return;
        }

        $downloadUrl = $this->urlGenerator->generate(
            'app_download',
            ['token' => $message->rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $senderLine = $message->senderName
            ? $message->senderName . ' (' . $message->senderEmail . ')'
            : $message->senderEmail;

        $fileCount = $transfer->getFileCount();

        $email = (new TemplatedEmail())
            ->to($message->ownerEmail)
            ->subject($senderLine . ' uploaded ' . ($fileCount === 1 ? 'a file' : $fileCount . ' files') . ' via your request')
            ->htmlTemplate('emails/file_request_upload.html.twig')
            ->context([
                'transfer' => $transfer,
                'downloadUrl' => $downloadUrl,
                'senderName' => $message->senderName,
                'senderEmail' => $message->senderEmail,
                'fileCount' => $fileCount,
            ]);

        $this->mailer->send($email);
    }
}