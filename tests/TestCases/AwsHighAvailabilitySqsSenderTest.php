<?php

declare(strict_types=1);

use ssigwart\AwsHighAvailabilityS3\S3AvailableUploadFileBucketAndKeyLocations;
use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsSender;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueues;
use TestAuxFiles\UnitTestCase;

/**
 * AWS high availability SQS sender test
 */
class AwsHighAvailabilitySqsSenderTest extends UnitTestCase
{
	/**
	 * Test normal size primary location success
	 */
	public function testNormalSizePrimaryLocationSuccess(): void
	{
		$queueMsgBody = 'Small Message';

		// Set up queues
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$backupQueue = new SqsAvailableQueue('us-east-2', 'https://sqs.us-east-2.amazonaws.com/123456789012/USEast2');
		$availableQueues = new SqsAvailableQueues($primaryQueue);
		$availableQueues->addAvailableQueue($backupQueue);

		// Set up possible locations
		$primaryLocation = new S3FileBucketAndKey('us-east-1', 'phpunit-test-us-east-1', 'us-east-1/path/to/dir/');
		$backupLocation = new S3FileBucketAndKey('us-east-2', 'phpunit-test-us-east-2', 'us-east-2/path/to/dir/');
		$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
		$s3Locations->addAlternativeLocation($backupLocation);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForSendMessage([
			[
				'MessageBody' => $queueMsgBody,
				'QueueUrl' => $primaryQueue->getQueueUrl()
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
		$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
		self::assertEquals(self::SQS_MESSAGE_ID, $result->getSqsMessageId());
		self::assertEquals($primaryQueue, $result->getSelectedQueue());
	}

	/**
	 * Test normal size backups location success
	 */
	public function testNormalSizeBackupLocationSuccess(): void
	{
		$queueMsgBody = 'Small Message';

		// Set up queues
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$backupQueue = new SqsAvailableQueue('us-east-2', 'https://sqs.us-east-2.amazonaws.com/123456789012/USEast2');
		$availableQueues = new SqsAvailableQueues($primaryQueue);
		$availableQueues->addAvailableQueue($backupQueue);

		// Set up possible locations
		$primaryLocation = new S3FileBucketAndKey('us-east-1', 'phpunit-test-us-east-1', 'us-east-1/path/to/dir/');
		$backupLocation = new S3FileBucketAndKey('us-east-2', 'phpunit-test-us-east-2', 'us-east-2/path/to/dir/');
		$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
		$s3Locations->addAlternativeLocation($backupLocation);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$sqsUsEast1 = $this->getMockSqsClientForSendMessage([
			[
				'MessageBody' => $queueMsgBody,
				'QueueUrl' => $primaryQueue->getQueueUrl()
			]
		], new \Aws\Exception\CredentialsException());
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$sqsUsEast2 = $this->getMockSqsClientForSendMessage([
			[
				'MessageBody' => $queueMsgBody,
				'QueueUrl' => $backupQueue->getQueueUrl()
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $backupQueue->getAwsRegion()
		], $sqsUsEast2);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
		$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
		self::assertEquals(self::SQS_MESSAGE_ID, $result->getSqsMessageId());
		self::assertEquals($backupQueue, $result->getSelectedQueue());
	}

	/**
	 * Test oversized primary location primary S3 success
	 */
	public function testOversizedPrimaryLocationPrimaryS3Success(): void
	{
		$queueMsgBody = str_repeat('x', AwsHighAvailabilitySqsSender::SQS_MAX_MESSAGE_SIZE + 1);

		// Set up queues
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$backupQueue = new SqsAvailableQueue('us-east-2', 'https://sqs.us-east-2.amazonaws.com/123456789012/USEast2');
		$availableQueues = new SqsAvailableQueues($primaryQueue);
		$availableQueues->addAvailableQueue($backupQueue);

		// Set up possible locations
		$primaryLocation = new S3FileBucketAndKey('us-east-1', 'phpunit-test-us-east-1', 'us-east-1/path/to/dir/');
		$backupLocation = new S3FileBucketAndKey('us-east-2', 'phpunit-test-us-east-2', 'us-east-2/path/to/dir/');
		$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
		$s3Locations->addAlternativeLocation($backupLocation);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$s3UsEast1 = $this->getMockS3ClientForUpload([
			[
				'ACL' => 'private',
				'Body' => $queueMsgBody,
				'ContentType' => 'text/plain',
				'Bucket' => $primaryLocation->getS3Bucket(),
				'Key' => $primaryLocation->getS3Key() . date('Ymd') . '/' . md5($queueMsgBody)
			]
		], null);
		$this->addExpectedCreateS3Call([
			'region' => $primaryLocation->getS3BucketRegion()
		], $s3UsEast1);
		$sqsUsEast1 = $this->getMockSqsClientForSendMessage([
			[
				'MessageBody' => '',
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'MessageAttributes' => [
					'HA-SQS.S3_FILE' => [
						'DataType' => 'String',
						'StringValue' => $primaryLocation->getS3BucketRegion() . ':' . $primaryLocation->getS3Bucket() . ':' . $primaryLocation->getS3Key() . date('Ymd') . '/' . md5($queueMsgBody)
					]
				]
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
		$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
		self::assertEquals(self::SQS_MESSAGE_ID, $result->getSqsMessageId());
		self::assertEquals($primaryQueue, $result->getSelectedQueue());
	}

	/**
	 * Test oversized primary location backup S3 success
	 */
	public function testOversizedPrimaryLocationBackupS3Success(): void
	{
		$queueMsgBody = str_repeat('x', AwsHighAvailabilitySqsSender::SQS_MAX_MESSAGE_SIZE + 1);

		// Set up queues
		$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/USEast1');
		$backupQueue = new SqsAvailableQueue('us-east-2', 'https://sqs.us-east-2.amazonaws.com/123456789012/USEast2');
		$availableQueues = new SqsAvailableQueues($primaryQueue);
		$availableQueues->addAvailableQueue($backupQueue);

		// Set up possible locations
		$primaryLocation = new S3FileBucketAndKey('us-east-1', 'phpunit-test-us-east-1', 'us-east-1/path/to/dir/');
		$backupLocation = new S3FileBucketAndKey('us-east-2', 'phpunit-test-us-east-2', 'us-east-2/path/to/dir/');
		$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
		$s3Locations->addAlternativeLocation($backupLocation);

		// Set up mock AWS
		$awsSdk = $this->getMockAwsSdk();
		$s3UsEast1 = $this->getMockS3ClientForUpload([
			[
				'ACL' => 'private',
				'Body' => $queueMsgBody,
				'ContentType' => 'text/plain',
				'Bucket' => $primaryLocation->getS3Bucket(),
				'Key' => $primaryLocation->getS3Key() . date('Ymd') . '/' . md5($queueMsgBody)
			]
		], new \Aws\Exception\CredentialsException());
		$this->addExpectedCreateS3Call([
			'region' => $primaryLocation->getS3BucketRegion()
		], $s3UsEast1);
		$s3UsEast2 = $this->getMockS3ClientForUpload([
			[
				'ACL' => 'private',
				'Body' => $queueMsgBody,
				'ContentType' => 'text/plain',
				'Bucket' => $backupLocation->getS3Bucket(),
				'Key' => $backupLocation->getS3Key() . date('Ymd') . '/' . md5($queueMsgBody)
			]
		], null);
		$this->addExpectedCreateS3Call([
			'region' => $backupLocation->getS3BucketRegion()
		], $s3UsEast2);
		$sqsUsEast1 = $this->getMockSqsClientForSendMessage([
			[
				'MessageBody' => '',
				'QueueUrl' => $primaryQueue->getQueueUrl(),
				'MessageAttributes' => [
					'HA-SQS.S3_FILE' => [
						'DataType' => 'String',
						'StringValue' => $backupLocation->getS3BucketRegion() . ':' . $backupLocation->getS3Bucket() . ':' . $backupLocation->getS3Key() . date('Ymd') . '/' . md5($queueMsgBody)
					]
				]
			]
		], null);
		$this->addExpectedCreateSqsCall([
			'region' => $primaryQueue->getAwsRegion()
		], $sqsUsEast1);
		$this->finalizeMockAwsSdk($awsSdk);

		$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
		$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
		self::assertEquals(self::SQS_MESSAGE_ID, $result->getSqsMessageId());
		self::assertEquals($primaryQueue, $result->getSelectedQueue());
	}
}
