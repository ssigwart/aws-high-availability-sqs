<?php

namespace ssigwart\AwsHighAvailabilitySqs;

use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use TypeError;

/** SQS message */
class SqsMessage
{
	/** @var array Raw message from AWS SDK */
	private array $msg;

	/** @var S3FileBucketAndKey|null S3 file */
	private ?S3FileBucketAndKey $s3File = null;

	/**
	 * Constructor
	 *
	 * @param array $msg Message
	 */
	public function __construct(array $msg)
	{
		$this->msg = $msg;
	}

	/**
	 * Get S3 file
	 *
	 * @return S3FileBucketAndKey|null S3 file
	 */
	public function getS3File(): ?S3FileBucketAndKey
	{
		return $this->s3File;
	}

	/**
	 * Set S3 file
	 *
	 * @param S3FileBucketAndKey|null $s3File S3 file
	 *
	 * @return self
	 */
	public function setS3File(?S3FileBucketAndKey $s3File): self
	{
		$this->s3File = $s3File;
		return $this;
	}

	/**
	 * Get message ID
	 *
	 * @return string Message ID
	 */
	public function getMessageId(): string
	{
		return $this->msg['MessageId'];
	}

	/**
	 * Get receipt handle
	 *
	 * @return string Receipt handle
	 */
	public function getReceiptHandle(): string
	{
		return $this->msg['ReceiptHandle'];
	}

	/**
	 * Get message
	 *
	 * @return string Message
	 */
	public function getMessage(): string
	{
		return $this->msg['Body'];
	}

	/**
	 * Get string message attribute
	 *
	 * @param string $attribute Attribute to get
	 *
	 * @return string|null Value
	 */
	public function getStringMessageAttribute(string $attribute): ?string
	{
		$attr = $this->msg['MessageAttributes'][$attribute] ?? null;
		if ($attr === null)
			return null;
		// Allow either a string or number since both can be returned as a string
		if ($attr['DataType'] !== 'String' && $attr['DataType'] !== 'Number')
			throw new TypeError('Attribute "' . $attribute . '" has an invalid data type of "' . $attr['DataType'] . '".');
		return $attr['StringValue'] ?? null;
	}

	/**
	 * Get int message attribute
	 *
	 * @param string $attribute Attribute to get
	 *
	 * @return int|null Value
	 */
	public function getIntMsgAttribute(string $attribute): ?int
	{
		$attr = $this->msg['MessageAttributes'][$attribute] ?? null;
		if ($attr === null)
			return null;
		if ($attr['DataType'] !== 'Number')
			throw new TypeError('Attribute "' . $attribute . '" has an invalid data type of "' . $attr['DataType'] . '".');
		$str = $attr['StringValue'] ?? null;
		if ($str === null)
			return null;
		if (!preg_match('/^[0-9]+$/AD', $str))
			throw new TypeError('Attribute "' . $attribute . '" value of "' . $str . '" is not an int.');
		return (int)$str;
	}

	/**
	 * Get message SQS attributes
	 *
	 * @return array SQS message attributes
	 */
	public function getMessageSqsAttributes(): array
	{
		return $this->msg['Attributes'];
	}

	/**
	 * Get send timestamp
	 *
	 * @return int Send timestamp
	 */
	public function getSentTimestamp(): int
	{
		return (int)floor($this->getSentTimestampInMs() / 1000);
	}

	/**
	 * Get send timestamp in milliseconds
	 *
	 * @return int Send timestamp in milliseconds
	 */
	public function getSentTimestampInMs(): int
	{
		return (int)$this->msg['Attributes']['SentTimestamp'];
	}

	/**
	 * Get approximate receive count
	 *
	 * @return int Approximate receive count
	 */
	public function getApproximateReceiveCount(): int
	{
		return (int)$this->msg['Attributes']['ApproximateReceiveCount'];
	}

	/**
	 * Get approximate first receive timestamp
	 *
	 * @return int Approximate first receive timestamp
	 */
	public function getApproximateFirstReceiveTimestamp(): int
	{
		return (int)floor($this->getApproximateFirstReceiveTimestampInMs() / 1000);
	}

	/**
	 * Get approximate first receive timestamp in milliseconds
	 *
	 * @return int Approximate first receive timestamp in milliseconds
	 */
	public function getApproximateFirstReceiveTimestampInMs(): int
	{
		return (int)$this->msg['Attributes']['ApproximateFirstReceiveTimestamp'];
	}
}
