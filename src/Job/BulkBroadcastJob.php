<?php
declare(strict_types=1);

namespace Crustum\Broadcasting\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Crustum\Broadcasting\Broadcasting;
use Exception;
use Interop\Queue\Processor as InteropProcessor;

/**
 * Bulk Broadcast Job
 *
 * Queues a chunk of already-normalized personalized broadcasts and sends
 * them through `Broadcasting::bulk()`.
 *
 * @package Crustum\Broadcasting\Job
 */
class BulkBroadcastJob implements JobInterface
{
    /**
     * Resolve a display name for custom job identification.
     *
     * @param array<string, mixed> $data Job payload
     * @return string
     */
    public static function displayName(array $data): string
    {
        $count = 0;
        if (isset($data['broadcasts']) && is_array($data['broadcasts'])) {
            $count = count($data['broadcasts']);
        }

        return self::class . '#' . $count;
    }

    /**
     * Execute the bulk broadcast job.
     *
     * @param \Cake\Queue\Job\Message $message Queue message
     * @return string Job execution result
     */
    public function execute(Message $message): string
    {
        $broadcasts = $message->getArgument('broadcasts', []);
        $connection = $message->getArgument('connection', 'default');
        $chunkSize = (int)$message->getArgument('chunkSize', 100);

        if (!is_array($broadcasts) || $broadcasts === []) {
            return InteropProcessor::REJECT;
        }

        if (!is_string($connection) || $connection === '') {
            $connection = 'default';
        }

        if ($chunkSize < 1) {
            $chunkSize = 100;
        }

        try {
            Broadcasting::bulk($broadcasts, $connection, $chunkSize);

            return InteropProcessor::ACK;
        } catch (Exception) {
            return InteropProcessor::REQUEUE;
        }
    }
}
