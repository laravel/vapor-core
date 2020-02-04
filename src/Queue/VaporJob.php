<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Queue\Jobs\SqsJob;

class VaporJob extends SqsJob
{
    /**
     * Release the job back into the queue.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->released = true;

        $payload = $this->payload();

        $payload['attempts']++;

        $this->sqs->deleteMessage([
            'QueueUrl' => $this->queue,
            'ReceiptHandle' => $this->job['ReceiptHandle'],
        ]);

        $this->sqs->sendMessage([
            'QueueUrl' => $this->queue,
            'MessageBody' => json_encode($payload),
            'DelaySeconds' => $this->secondsUntil($delay),
        ]);
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return ($this->payload()['attempts'] ?? null) + 1;
    }
}
