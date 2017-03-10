<?php
namespace AppBundle\Service;

use AppBundle\Messaging\MessageProcessInterface;
use AppBundle\Service\Aws\SQS;

class Messenger
{
    private $client;

    public function __construct(SQS $client)
    {
        $this->client = $client;
    }

    public function send(MessageProcessInterface $processor, $queueUrl, $messages = array())
    {
        foreach ($messages as $message) {
            $processor->process($message);
            $receiptHandle = $message['ReceiptHandle'];
            $this->client->deleteMessage($queueUrl, $receiptHandle);
        }
    }
}
