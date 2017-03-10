<?php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class WorkerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sqs:process')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, null, 5)
            ->addOption('processor', null, InputOption::VALUE_REQUIRED, null, null)
            ->addOption('cache', null, InputOption::VALUE_REQUIRED, null, null)
            ->addOption('max_number_messages', null, InputOption::VALUE_REQUIRED, null, 10)
            ->addOption('wait_time_seconds', null, InputOption::VALUE_REQUIRED, null, 20)
            ->setDescription('Process AWS SQS messages');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getContainer()->get('aws.sqs.helper');
        $maxNumberOfMessages = intval($input->getOption('max_number_messages'));
        $waitTimeSeconds = intval($input->getOption('wait_time_seconds'));
        $sleepTime = intval($input->getOption('sleep'));
        $processorName = $input->getOption('processor');

        $cache = $this->getContainer()->get('cache.factory')->getCache($input->getOption('cache'));
        $url = $client->getQueueUrl($this->getQueueName($processorName));
        $lastRestart = $cache->getItem('last_restart_date')->get();

        while (true) {
            try {
                $result = $client->receiveMessage($url, $maxNumberOfMessages, $waitTimeSeconds);
                if ($result->get('Messages')) {
                    $processor = $this->createProcessor($processorName);
                    $this->getContainer()->get('messenger')->send($processor, $url, $result->get('Messages'));
                } else {
                    sleep($sleepTime);
                }

                $this->stopIfNecessary($lastRestart, $cache);
            } catch (\Exception $e) {
                $this->getContainer()->get('logger')->error($e->getMessage());
            }
        }
    }

    private function createProcessor($name)
    {
        switch ($name) {
            case 'confirmation_email':
                return $this->getContainer()->get('processor.confirmation.email');
            case 'mailchimp':
                return $this->getContainer()->get('processor.mailchimp');
        }

        throw new \InvalidArgumentException('Unsupported Processor');
    }

    private function getQueueName($name)
    {
        switch ($name) {
            case 'confirmation_email':
                return $this->getContainer()->getParameter('confirmation_queue');
            case 'mailchimp':
                return  $this->getContainer()->getParameter('mailchimp_queue');
        }

        throw new \InvalidArgumentException('Unsupported Queue');
    }

    protected function stopIfNecessary($lastRestart, $cache)
    {
        if ($this->queueShouldRestart($lastRestart, $cache)) {
            $this->stop();
        }
    }

    private function stop($status = 0)
    {
        exit($status);
    }

    protected function queueShouldRestart($lastRestart, $cache)
    {
        return $this->getTimestampOfLastQueueRestart($cache) != $lastRestart;
    }

    protected function getTimestampOfLastQueueRestart(AbstractAdapter $cache)
    {
        if ($cache) {
            return $cache->getItem('last_restart_date')->get();
        }

        return null;
    }
}