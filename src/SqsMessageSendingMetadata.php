<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS message sending metadata */
class SqsMessageSendingMetadata
{
	/** @var int|null Delay in seconds */
	private ?int $delayInSeconds = null;

	/**
	 * Get delay in seconds
	 *
	 * @return int|null Delay in seconds
	 */
	public function getDelayInSeconds(): ?int
	{
		return $this->delayInSeconds;
	}

	/**
	 * Set delay in seconds
	 *
	 * @param int|null $delayInSeconds Delay in seconds
	 *
	 * @return self
	 */
	public function setDelayInSeconds(?int $delayInSeconds): self
	{
		$this->delayInSeconds = $delayInSeconds;
		return $this;
	}

	/** @var SqsMessageAttribute[] Message attributes */
	private array $messageAttributes = [];

	/**
	 * Get message attributes
	 *
	 * @return SqsMessageAttribute[] Message attributes
	 */
	public function getMessageAttributes(): array
	{
		return $this->messageAttributes;
	}

	/**
	 * Add message attribute
	 *
	 * @param SqsMessageAttribute $messageAttribute Message attribute
	 *
	 * @return self
	 */
	public function addMessageAttribute(SqsMessageAttribute $messageAttribute): self
	{
		$this->messageAttributes[] = $messageAttribute;
		return $this;
	}
}
