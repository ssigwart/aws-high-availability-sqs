<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS message receiving metadata */
class SqsMessageReceivingMetadata
{
	/** @var int Max num messages */
	private int $maxNumMessages = 10;

	/**
	 * Get max num messages
	 *
	 * @return int Max num messages
	 */
	public function getMaxNumMessages(): int
	{
		return $this->maxNumMessages;
	}

	/**
	 * Set max num messages
	 *
	 * @param int $maxNumMessages Max num messages
	 *
	 * @return self
	 */
	public function setMaxNumMessages(int $maxNumMessages): self
	{
		$this->maxNumMessages = $maxNumMessages;
		return $this;
	}

	/** @var int|null Visibility timeout in seconds */
	private ?int $visibilityTimeout = null;

	/**
	 * Get visibility timeout in seconds
	 *
	 * @return int|null Visibility timeout in seconds
	 */
	public function getVisibilityTimeout(): ?int
	{
		return $this->visibilityTimeout;
	}

	/**
	 * Set visibility timeout in seconds
	 *
	 * @param int|null $visibilityTimeout Visibility timeout in seconds
	 *
	 * @return self
	 */
	public function setVisibilityTimeout(?int $visibilityTimeout): self
	{
		$this->visibilityTimeout = $visibilityTimeout;
		return $this;
	}

	/** @var int|null Wait time in seconds */
	private ?int $waitTime = null;

	/**
	 * Get wait time in seconds
	 *
	 * @return int|null Wait time in seconds
	 */
	public function getWaitTime(): ?int
	{
		return $this->waitTime;
	}

	/**
	 * Set wait time in seconds
	 *
	 * @param int|null $waitTime Wait time in seconds
	 *
	 * @return self
	 */
	public function setWaitTime(?int $waitTime): self
	{
		$this->waitTime = $waitTime;
		return $this;
	}
}
