<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS available queues */
class SqsAvailableQueues
{
	/** @var SqsAvailableQueueInterface[] Available queues */
	private array $availableQueues = [];

	/**
	 * Construct
	 *
	 * @param SqsAvailableQueueInterface $primaryQueue Primary queue
	 */
	public function __construct(SqsAvailableQueueInterface $primaryQueue)
	{
		$this->availableQueues[] = $primaryQueue;
	}

	/**
	 * Get available queues
	 *
	 * @return SqsAvailableQueueInterface[] Available queues
	 */
	public function getAvailableQueues(): array
	{
		return $this->availableQueues;
	}

	/**
	 * Add available queue
	 *
	 * @param SqsAvailableQueueInterface $availableQueue Available queue
	 *
	 * @return self
	 */
	public function addAvailableQueue(SqsAvailableQueueInterface $availableQueue): self
	{
		$this->availableQueues[] = $availableQueue;
		return $this;
	}
}
