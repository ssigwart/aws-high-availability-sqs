<?php

namespace ssigwart\AwsHighAvailabilitySqs;

/** SQS available queue interface */
interface SqsAvailableQueueInterface
{
	/**
	 * Get AWS region
	 *
	 * @return string AWS region
	 */
	public function getAwsRegion(): string;

	/**
	 * Get queue URL
	 *
	 * @return string Queue URL
	 */
	public function getQueueUrl(): string;
}
