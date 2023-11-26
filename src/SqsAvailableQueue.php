<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS available queue */
class SqsAvailableQueue implements SqsAvailableQueueInterface
{
	/** @var string AWS region */
	private string $awsRegion;

	/** @var string Queue URL */
	private string $queueUrl;

	/**
	 * Constructor
	 *
	 * @param string $awsRegion AWS region
	 * @param string $queueUrl Queue URL
	 */
	public function __construct(string $awsRegion, string $queueUrl)
	{
		$this->awsRegion = $awsRegion;
		$this->queueUrl = $queueUrl;
	}

	/**
	 * Get AWS region
	 *
	 * @return string AWS region
	 */
	public function getAwsRegion(): string
	{
		return $this->awsRegion;
	}

	/**
	 * Get queue URL
	 *
	 * @return string Queue URL
	 */
	public function getQueueUrl(): string
	{
		return $this->queueUrl;
	}
}
