<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** Receive SQS messages result */
class ReceiveSqsMessagesResult
{
	/** @var SqsMessage[] SQS messages */
	private array $sqsMessages = [];

	/**
	 * Get number messages
	 *
	 * @return int Number of messages
	 */
	public function getNumMessages(): int
	{
		return count($this->sqsMessages);
	}

	/**
	 * Get SQS messages
	 *
	 * @return SqsMessage[] SQS messages
	 */
	public function getSqsMessages(): array
	{
		return $this->sqsMessages;
	}

	/**
	 * Add SQS message
	 *
	 * @param SqsMessage $sqsMessage SQS message
	 *
	 * @return self
	 */
	public function addSqsMessage(SqsMessage $sqsMessage): self
	{
		$this->sqsMessages[] = $sqsMessage;
		return $this;
	}
}
