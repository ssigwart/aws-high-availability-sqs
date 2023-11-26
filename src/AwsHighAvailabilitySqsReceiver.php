<?php

namespace ssigwart\AwsHighAvailabilitySqs;

use ssigwart\AwsHighAvailabilityS3\AwsHighAvailabilityS3Downloader;
use ssigwart\AwsHighAvailabilityS3\S3AvailableDownloadFileBucketAndKeyLocations;
use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use Throwable;

/** AWS high availability SQS receiver */
class AwsHighAvailabilitySqsReceiver
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

	/** @var AwsHighAvailabilityS3Downloader|null S3 downloader */
	private ?AwsHighAvailabilityS3Downloader $s3Downloader = null;

	/**
	 * Set S3 downloader
	 *
	 * @param AwsHighAvailabilityS3Downloader|null $s3Downloader S3 downloader
	 *
	 * @return self
	 */
	public function setS3Uploader(?AwsHighAvailabilityS3Downloader $s3Downloader): self
	{
		$this->s3Downloader = $s3Downloader;
		return $this;
	}

	/**
	 * Set up S3 downloader
	 */
	private function setUpS3Downloader(): void
	{
		$this->s3Downloader ??= new AwsHighAvailabilityS3Downloader($this->awsSdk);
	}

	/**
	 * Receive message with S3 large message backing
	 *
	 * @param SqsAvailableQueue $sqsQueue Queue to read from
	 * @param SqsMessageReceivingMetadata|null $metadata Metadata
	 *
	 * @return ReceiveSqsMessagesResult
	 */
	public function receivedMessagesWithS3LargeMessageBacking(SqsAvailableQueue $sqsQueue, ?SqsMessageReceivingMetadata $metadata): ReceiveSqsMessagesResult
	{
		// Build request
		$req = [
			'AttributeNames' => ['All'],
			'MessageAttributeNames' => ['All'],
			'MaxNumberOfMessages' => $metadata?->getMaxNumMessages() ?? 1,
			'QueueUrl' => $sqsQueue->getQueueUrl(),
		];

		// Add visibility timeout
		$visibilityTimeout = $metadata?->getVisibilityTimeout();
		if ($visibilityTimeout !== null)
			$req['VisibilityTimeout'] = $visibilityTimeout;

		// Add wait time
		$waitTimeSeconds = $metadata?->getWaitTime();
		if ($waitTimeSeconds !== null)
			$req['WaitTimeSeconds'] = $waitTimeSeconds;

		try
		{
			// Make request
			$result = $this->awsSdk->createSqs([
				'region' => $sqsQueue->getAwsRegion()
			])->receiveMessage($req);
			$rtn = new ReceiveSqsMessagesResult();
			foreach ($result['Messages'] as $msg)
			{
				$sqsMessage = new SqsMessage($msg);

				// Download from S3 if needed
				$s3FileInfo = $sqsMessage->getStringMessageAttribute('HA-SQS.S3_FILE');
				if ($s3FileInfo !== null)
				{
					if (!preg_match('/^([^:]+):([^:]+):(.*)$/AD', $s3FileInfo, $match))
						throw new AwsHighAvailabilitySqsReceiverException('Unexpected S3 file attribute with value "' . $s3FileInfo . '".');
					$this->setUpS3Downloader();
					$sqsMessage = null;
					$s3File = new S3FileBucketAndKey($match[1], $match[2], $match[3]);
					$msg['Body'] = $this->s3Downloader->downloadFileFromS3(new S3AvailableDownloadFileBucketAndKeyLocations(
						$s3File
					));
					$sqsMessage = new SqsMessage($msg);
					$sqsMessage->setS3File($s3File);
				}

				// Add to list of messages
				$rtn->addSqsMessage($sqsMessage);
			}

			return $rtn;
		} catch (Throwable $e) {
			// Use this to help with unit testing
			// if (str_contains(get_class($e), 'PHPUnit'))
			// 	throw $e;
			// else if (str_contains(get_class($e->getPrevious()), 'PHPUnit'))
			// 	throw $e->getPrevious();

			throw new AwsHighAvailabilitySqsReceiverException('Failed to receive messages.', 0, $e);
		}
	}

	/**
	 * Delete message
	 *
	 * @param SqsAvailableQueue $sqsQueue SQS queue
	 * @param SqsMessage $sqsMessage SQS message
	 *
	 * @throws AwsHighAvailabilitySqsDeleteException
	 */
	public function deleteMessage(SqsAvailableQueue $sqsQueue, SqsMessage $sqsMessage): void
	{
		// Delete SQS message
		try
		{
			$req = [
				'QueueUrl' => $sqsQueue->getQueueUrl(),
				'ReceiptHandle' => $sqsMessage->getReceiptHandle()
			];
			$this->awsSdk->createSqs([
				'region' => $sqsQueue->getAwsRegion()
			])->deleteMessage($req);
		} catch (Throwable $e) {
			throw new AwsHighAvailabilitySqsDeleteException('Failed to delete SQS message ' . $sqsMessage->getMessageId() . '.', 0, $e);
		}

		// Delete from S3 if needed
		$s3File = $sqsMessage->getS3File();
		if ($s3File !== null)
		{
			try
			{
				$req = [
					'Bucket' => $s3File->getS3Bucket(),
					'Key' => $s3File->getS3Key()
				];
				$this->awsSdk->createS3([
					'region' => $s3File->getS3BucketRegion()
				])->deleteObject($req);
			} catch (Throwable $e) {
				throw new AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException('Failed to delete S3 file (' . $s3File->getS3Bucket() . ':' . $s3File->getS3Key() . ') for SQS message ' . $sqsMessage->getMessageId() . '.', 0, $e);
			}
		}
	}
}
