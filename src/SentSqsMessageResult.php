<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** Sent SQS message result */
class SentSqsMessageResult
{
	/** @var string SQS message ID */
	private string $sqsMessageId;

	/** @var SqsAvailableQueueInterface Selected queue */
	private SqsAvailableQueueInterface $selectedQueue;

	/**
	 * Constructor
	 *
	 * @param string $sqsMessageId SQS message ID
	 * @param SqsAvailableQueueInterface $selectedQueue Selected queue
	 */
	public function __construct(string $sqsMessageId, SqsAvailableQueueInterface $selectedQueue)
	{
		$this->sqsMessageId = $sqsMessageId;
		$this->selectedQueue = $selectedQueue;
	}

	/**
	 * Get SQS message ID
	 *
	 * @return string SQS message ID
	 */
	public function getSqsMessageId(): string
	{
		return $this->sqsMessageId;
	}

	/**
	 * Get selected queue
	 *
	 * @return SqsAvailableQueueInterface Selected queue
	 */
	public function getSelectedQueue(): SqsAvailableQueueInterface
	{
		return $this->selectedQueue;
	}
}
