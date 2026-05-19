<?php

namespace App\MessageHandler;

use App\Message\SendTransferEmail;
use App\Repository\TransferRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class SendTransferEmailHandler
{
    public function __construct(
        private TransferRepository $transferRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendTransferEmail $message): void
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

        $fileCount = $transfer->getFileCount();
        $subjectSuffix = $fileCount === 1
            ? $transfer->getOriginalFilename()
            : $fileCount . ' files';

        $email = (new TemplatedEmail())
            ->to($message->recipientEmail)
            ->subject($transfer->getUser()->getName() . ' sent you ' . $subjectSuffix)
            ->htmlTemplate('emails/transfer.html.twig')
            ->context([
                'transfer' => $transfer,
                'downloadUrl' => $downloadUrl,
                'senderName' => $transfer->getUser()->getName(),
                'message' => $message->message,
                'fileCount' => $fileCount,
            ]);

        $this->mailer->send($email);
    }
}