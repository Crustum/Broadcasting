<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Crustum\Broadcasting\Broadcasting;
use Exception;
use Interop\Queue\Processor as InteropProcessor;

/**
 * Broadcast Job
 *
 * Handles broadcast event execution from queue messages using the Broadcasting facade.
 * Implements CakePHP Queue's JobInterface for integration with the queue system.
 *
 * @package Crustum\Broadcasting\Job
 */
class BroadcastJob implements JobInterface
{
    /**
     * Execute the broadcast job
     *
     * @param \Cake\Queue\Job\Message $message Queue message
     * @return string Job execution result
     */
    public function execute(Message $message): string
    {
        $eventName = $message->getArgument('eventName');
        $channels = $message->getArgument('channels');
        $payload = $message->getArgument('payload', []);
        $config = $message->getArgument('config', 'default');
        $socket = $message->getArgument('socket');

        if (empty($eventName) || empty($channels)) {
            return InteropProcessor::REJECT;
        }

        try {
            $pending = Broadcasting::to($channels)
                ->event($eventName)
                ->data($payload)
                ->connection($config);

            if ($socket !== null) {
                $pending->setSocket($socket);
            }

            $pending->send();

            return InteropProcessor::ACK;
        } catch (Exception $e) {
            return InteropProcessor::REQUEUE;
        }
    }
}
