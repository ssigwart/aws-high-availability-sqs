<?php

namespace ssigwart\AwsHighAvailabilitySqs;

use ssigwart\AwsHighAvailabilityS3\AwsHighAvailabilityS3Uploader;
use ssigwart\AwsHighAvailabilityS3\S3AvailableUploadFileBucketAndKeyLocations;
use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use Throwable;

/** AWS high availability SQS sender */
class AwsHighAvailabilitySqsSender
{
	/** @var \Aws\Sdk AWS SDK */
	private \Aws\Sdk $awsSdk;

	/**
	 * Constructor
	 *
	 * @param \Aws\Sdk $awsSdk AWS SDK
	 */
	public function __construct(\Aws\Sdk $awsSdk)
	{
		$this->awsSdk = $awsSdk;
	}

	/** @var AwsHighAvailabilityS3Uploader|null S3 uploader */
	private ?AwsHighAvailabilityS3Uploader $s3Uploader = null;

	/**
	 * Set S3 uploader
	 *
	 * @param AwsHighAvailabilityS3Uploader|null $s3Uploader S3 uploader
	 *
	 * @return self
	 */
	public function setS3Uploader(?AwsHighAvailabilityS3Uploader $s3Uploader): self
	{
		$this->s3Uploader = $s3Uploader;
		return $this;
	}

	/**
	 * Set up S3 uploader
	 */
	private function setUpS3Uploader(): void
	{
		$this->s3Uploader ??= new AwsHighAvailabilityS3Uploader($this->awsSdk);
	}

	/** @var int Max SQS message size */
	const SQS_MAX_MESSAGE_SIZE = 262144;

	/**
	 * Send message with S3 large message backing
	 *
	 * @param SqsAvailableQueues $availableQueues Available queues
	 * @param S3AvailableUploadFileBucketAndKeyLocations $s3DirLocations S3 locations to store message bodies if too big. This could be a directory ending with /. Files will be stored in by date in YYYYMMDD/<MSG-MD5>
	 * @param string $msgBody Message body
	 * @param SqsMessageSendingMetadata|null $metadata Metadata
	 *
	 * @return SentSqsMessageResult
	 */
	public function sendMessageWithS3LargeMessageBacking(SqsAvailableQueues $availableQueues, S3AvailableUploadFileBucketAndKeyLocations $s3DirLocations, string $msgBody, ?SqsMessageSendingMetadata $metadata = null): SentSqsMessageResult
	{
		$attrs = $metadata?->getMessageAttributes() ?? [];

		// Check if the message is too large
		if (strlen($msgBody) > self::SQS_MAX_MESSAGE_SIZE)
		{
			try
			{
				// Build final S3 locations
				$subPath = date('Ymd') . '/' . md5($msgBody);
				$fullS3Locations = [];
				foreach ($s3DirLocations->getLocations() as $location)
					$fullS3Locations[] = new S3FileBucketAndKey($location->getS3BucketRegion(), $location->getS3Bucket(), $location->getS3Key() . $subPath);
				$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations(array_shift($fullS3Locations));
				foreach ($fullS3Locations as $fullS3Location)
					$s3Locations->addAlternativeLocation($fullS3Location);

				// Save in S3, clear body and set message attribute
				$this->setUpS3Uploader();
				$finalLocation = $this->s3Uploader->uploadPrivateFileToS3($s3Locations, $msgBody, 'text/plain', null);
				$msgBody = ' ';
				$attrs['HA-SQS.S3_FILE'] = new SqsMessageAttribute(SqsMessageAttribute::DATA_TYPE_STRING, $finalLocation->getS3BucketRegion() . ':' . $finalLocation->getS3Bucket() . ':' . $finalLocation->getS3Key());
			} catch (Throwable $e) {
				// Use this to help with unit testing
				// if (str_contains(get_class($e), 'PHPUnit'))
				// 	throw $e;
				// else if (str_contains(get_class($e->getPrevious()), 'PHPUnit'))
				// 	throw $e->getPrevious();

				throw new AwsHighAvailabilitySqsSenderException('Failed to store message in S3.', 0, $e);
			}
		}

		// Build request
		$req = [
			'MessageBody' => $msgBody
		];

		// Add delay
		$delayInSec = $metadata?->getDelayInSeconds();
		if ($delayInSec !== null)
			$req['DelaySeconds'] = $delayInSec;

		// Add attributes
		if (!empty($attrs))
		{
			$req['MessageAttributes'] = array_map(function(SqsMessageAttribute $attr) {
				return [
					'DataType' => $attr->getDataType(),
					'StringValue' => $attr->getStringValue()
				];
			}, $attrs);
		}

		// Try available locations
		$firstException = null;
		foreach ($availableQueues->getAvailableQueues() as $queue)
		{
			try
			{
				// Set location
				$req['QueueUrl'] = $queue->getQueueUrl();

				// Make request
				$result = $this->awsSdk->createSqs([
					'region' => $queue->getAwsRegion()
				])->sendMessage($req);
				return new SentSqsMessageResult($result['MessageId'], $queue);
			} catch (Throwable $e) {
				// Use this to help with unit testing
				// if (str_contains(get_class($e), 'PHPUnit'))
				// 	throw $e;
				// else if (str_contains(get_class($e->getPrevious()), 'PHPUnit'))
				// 	throw $e->getPrevious();

				$firstException ??= $e;
			}
		}

		// Throw exception
		throw new AwsHighAvailabilitySqsSenderException('Failed to send message. Attempted ' . number_format(count($availableQueues->getAvailableQueues())) . ' queues.', 0, $firstException);
	}
}
