<?php

namespace Laravel\Vapor\Queue;

use Illuminate\Queue\Jobs\SqsJob;

class VaporJob extends SqsJob
{
    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return max(
            ($this->payload()['attempts'] ?? 0) + 1,
            $this->container->make(JobAttempts::class)->get($this)
        );
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->container
             ->make(JobAttempts::class)
             ->forget($this);
    }

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

        $payload['attempts'] = $this->attempts();

        $this->sqs->deleteMessage([
            'QueueUrl' => $this->queue,
            'ReceiptHandle' => $this->job['ReceiptHandle'],
        ]);

        $jobId = $this->sqs->sendMessage([
            'QueueUrl' => $this->queue,
            'MessageBody' => $this->releasedMessageBody($payload),
            'DelaySeconds' => $this->secondsUntil($delay),
        ])->get('MessageId');

        $this->container
             ->make(JobAttempts::class)
             ->transfer($this, $jobId);
    }

    /**
     * Get the message body for the released job.
     *
     * @param  array  $payload
     * @return string
     */
    protected function releasedMessageBody(array $payload)
    {
        $body = json_encode($payload);

        if (! method_exists($this, 'overflowPointer') || ! method_exists($this, 'overflowStore')) {
            return $body;
        }

        if (! $pointer = $this->overflowPointer()) {
            return $body;
        }

        $this->overflowStore()->put($pointer, $body);

        return json_encode(['@pointer' => $pointer]);
    }
}
