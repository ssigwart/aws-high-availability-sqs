<?php

declare(strict_types=1);

use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsDeleteException;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsReceiver;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use ssigwart\AwsHighAvailabilitySqs\SqsMessageReceivingMetadata;
use TestAuxFiles\UnitTestCase;

/**
 * AWS high availability SQS receiver test
 */
class AwsHighAvailabilitySqsReceiverTest extends UnitTestCase
{
	/**
	 * Test receive messages
	 */
	public function testReceiveMessages(): void
	{
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');

		// Set up metadata
		$metadata = new SqsMessageReceivingMetadata();
		$metadata->setMaxNumMessages(7);
		$metadata->setVisibilityTimeout(300);
		$metadata->setWaitTime(15);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForReceiveMessage([
			[
				'AttributeNames' => 'All',
				'MaxNumberOfMessages' => $metadata->getMaxNumMessages(),
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'VisibilityTimeout' => $metadata->getVisibilityTimeout(),
				'WaitTimeSeconds' => $metadata->getWaitTime()
			]
		], [
			[
				'Body' => 'Body 1',
				'MessageAttributes' => [],
				'MessageId' => 'message-id-1'
			],
			[
				'Body' => 'Body 2',
				'MessageAttributes' => [],
				'MessageId' => 'message-id-2'
			],
			[
				'Body' => '',
				'MessageAttributes' => [
					'HA-SQS.S3_FILE' => [
						'DataType' => 'String',
						'StringValue' => 'us-east-1:my-bucket:path/to/file/20230123/MD5-3'
					]
				],
				'MessageId' => 'message-id-3'
			],
			[
				'Body' => '',
				'MessageAttributes' => [
					'HA-SQS.S3_FILE' => [
						'DataType' => 'String',
						'StringValue' => 'us-east-1:my-bucket:path/to/file/20230123/MD5-4'
					]
				],
				'MessageId' => 'message-id-4'
			]
		]);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		// S3 for message 3
		$s3UsEast1 = $this->getMockS3ClientForDownload([
			[
				'Bucket' => 'my-bucket',
				'Key' => 'path/to/file/20230123/MD5-3'
			]
		], 'Body 3', null);
		$this->addExpectedCreateS3Call([
			'region' => 'us-east-1'
		], $s3UsEast1);
		// S3 for message 4
		$s3UsEast1 = $this->getMockS3ClientForDownload([
			[
				'Bucket' => 'my-bucket',
				'Key' => 'path/to/file/20230123/MD5-4'
			]
		], 'Body 4', null);
		$this->addExpectedCreateS3Call([
			'region' => 'us-east-1'
		], $s3UsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		$sqsMessagesResult = $sqsReceiver->receivedMessagesWithS3LargeMessageBacking($primaryQueue, $metadata);
		self::assertEquals(4, $sqsMessagesResult->getNumMessages());
		// Check message 1
		$sqsMessage = $sqsMessagesResult->getSqsMessages()[0];
		self::assertEquals('message-id-1', $sqsMessage->getMessageId());
		self::assertEquals('Body 1', $sqsMessage->getMessage());
		self::assertEquals(null, $sqsMessage->getStringMessageAttribute('HA-SQS.S3_FILE'));
		// Check message 2
		$sqsMessage = $sqsMessagesResult->getSqsMessages()[1];
		self::assertEquals('message-id-2', $sqsMessage->getMessageId());
		self::assertEquals('Body 2', $sqsMessage->getMessage());
		self::assertEquals(null, $sqsMessage->getStringMessageAttribute('HA-SQS.S3_FILE'));
		// Check message 3
		$sqsMessage = $sqsMessagesResult->getSqsMessages()[2];
		self::assertEquals('message-id-3', $sqsMessage->getMessageId());
		self::assertEquals('Body 3', $sqsMessage->getMessage());
		// Check message 4
		$sqsMessage = $sqsMessagesResult->getSqsMessages()[3];
		self::assertEquals('message-id-4', $sqsMessage->getMessageId());
		self::assertEquals('Body 4', $sqsMessage->getMessage());
	}

	/**
	 * Test delete message with S3 backing
	 */
	public function testDeleteMessage(): void
	{
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$sqsMessage = new SqsMessage([
			'MessageId' => 'MESSAGE_ID_1',
			'Body' => 'body',
			'ReceiptHandle' => 'RECEIPT_HANDLE_1'
		]);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForDeleteMessage([
			[
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'ReceiptHandle' => 'RECEIPT_HANDLE_1'
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
	}

	/**
	 * Test delete message with S3 backing
	 */
	public function testDeleteMessageWithS3Backing(): void
	{
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$sqsMessage = new SqsMessage([
			'MessageId' => 'MESSAGE_ID_1',
			'Body' => 'body',
			'ReceiptHandle' => 'RECEIPT_HANDLE_1'
		]);
		$s3File = new S3FileBucketAndKey('us-east-1', 'my-bucket', 'path/to/file/20230123/MD5-1');
		$sqsMessage->setS3File($s3File);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForDeleteMessage([
			[
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'ReceiptHandle' => 'RECEIPT_HANDLE_1'
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		// S3 delete
		$s3UsEast1 = $this->getMockS3ClientForDelete([
			[
				'Bucket' => 'my-bucket',
				'Key' => 'path/to/file/20230123/MD5-1'
			]
		], null);
		$this->addExpectedCreateS3Call([
			'region' => 'us-east-1'
		], $s3UsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
	}

	/**
	 * Test delete message with S3 backing failure
	 */
	public function testDeleteMessageWithS3BackingFailure(): void
	{
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$sqsMessage = new SqsMessage([
			'MessageId' => 'MESSAGE_ID_1',
			'Body' => 'body',
			'ReceiptHandle' => 'RECEIPT_HANDLE_1'
		]);
		$s3File = new S3FileBucketAndKey('us-east-1', 'my-bucket', 'path/to/file/20230123/MD5-1');
		$sqsMessage->setS3File($s3File);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForDeleteMessage([
			[
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'ReceiptHandle' => 'RECEIPT_HANDLE_1'
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		// S3 delete
		$s3UsEast1 = $this->getMockS3ClientForDelete([
			[
				'Bucket' => 'my-bucket',
				'Key' => 'path/to/file/20230123/MD5-1'
			]
		], new \Aws\Exception\CredentialsException());
		$this->addExpectedCreateS3Call([
			'region' => 'us-east-1'
		], $s3UsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		try {
			$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
			self::fail('Expected AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException.');
		} catch (AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException $e) {
			self::assertEquals('Failed to delete S3 file (my-bucket:path/to/file/20230123/MD5-1) for SQS message MESSAGE_ID_1.', $e->getMessage());
		}
	}

	/**
	 * Test delete message failure with S3 backing
	 */
	public function testDeleteMessageFailureWithS3Backing(): void
	{
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$sqsMessage = new SqsMessage([
			'MessageId' => 'MESSAGE_ID_1',
			'Body' => 'body',
			'ReceiptHandle' => 'RECEIPT_HANDLE_1'
		]);
		$s3File = new S3FileBucketAndKey('us-east-1', 'my-bucket', 'path/to/file/20230123/MD5-1');
		$sqsMessage->setS3File($s3File);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForDeleteMessage([
			[
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'ReceiptHandle' => 'RECEIPT_HANDLE_1'
			]
		], new \Aws\Exception\CredentialsException());
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
		try {
			$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
			self::fail('Expected AwsHighAvailabilitySqsDeleteException.');
		} catch (AwsHighAvailabilitySqsDeleteException $e) {
			self::assertEquals('Failed to delete SQS message MESSAGE_ID_1.', $e->getMessage());
		}
	}
}
