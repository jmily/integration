<?php
namespace AppBundle\Handler;

use AppBundle\Model\Command;

class ConfirmationEmailHandler implements Handler
{
    private $mailer;

    public function __construct(\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function handle(Command $command)
    {
        $emailBody = \Swift_Message::newInstance()
            ->setSubject('Email Confirmation')
            ->setFrom('admin@escapestring.com')
            ->setTo($command->getEmail())
            ->setBody('Your account has been confirmed');

        $this->mailer->getTransport()->start();
        $this->mailer->send($emailBody);
        $this->mailer->getTransport()->stop();
    }
}