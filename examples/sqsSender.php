<?php

/**
 * The following uses LocalStack (https://github.com/localstack/localstack). From
 * the CLI, you can set up the queues. The S3 buckets are created below if
 * needed.
 *
 * ```sh
 * aws --endpoint-url="http://sqs.us-east-1.localhost.localstack.cloud:4566" sqs create-queue --queue-name=example
 * aws --endpoint-url="http://sqs.us-east-2.localhost.localstack.cloud:4566" sqs create-queue --queue-name=example_backup
 * ```
 */

namespace Examples;

use ssigwart\AwsHighAvailabilityS3\S3AvailableUploadFileBucketAndKeyLocations;
use ssigwart\AwsHighAvailabilityS3\S3FileBucketAndKey;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsSender;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueues;

require(__DIR__ . '/../vendor/autoload.php');

(function() {
	$queueMsgBody = str_repeat('X', AwsHighAvailabilitySqsSender::SQS_MAX_MESSAGE_SIZE) . '-TooLong';

	// Set up AWS
	$awsSdk = new \Aws\Sdk([
		'credentials' => [
			'key' => 'AKIAIOSFODNN7EXAMPLE',
			'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'
		],
		'Sqs' => [
			'endpoint' => 'http://127.0.0.1:4566'
		],
		'S3' => [
			'endpoint' => 'http://127.0.0.1:4566'
		]
	]);

	// Create buckets
	$s3UsEast1 = $awsSdk->createS3([
		'region' => 'us-east-1'
	]);
	try {
		$s3UsEast1->getBucketLocation([
			'Bucket' => 'example-s3-primary'
		]);
	} catch (\Aws\S3\Exception\S3Exception $e) {
		$s3UsEast1->createBucket([
			'Bucket' => 'example-s3-primary'
		]);
	}
	$s3UsEast2 = $awsSdk->createS3([
		'region' => 'us-east-2'
	]);
	try {
		$s3UsEast2->getBucketLocation([
			'Bucket' => 'example-s3-backup'
		]);
	} catch (\Aws\S3\Exception\S3Exception $e) {
		$s3UsEast2->createBucket([
			'Bucket' => 'example-s3-backup'
		]);
	}

	// Set up SQS queues
	$primaryQueue = new SqsAvailableQueue('us-east-1', 'http://sqs.us-east-1.localhost.localstack.cloud:4566/000000000000/example');
	$backupQueue = new SqsAvailableQueue('us-east-2', 'http://sqs.us-east-1.localhost.localstack.cloud:4566/000000000000/example_backup');
	$availableQueues = new SqsAvailableQueues($primaryQueue);
	$availableQueues->addAvailableQueue($backupQueue);

	// Set up S3 locations
	$primaryLocation = new S3FileBucketAndKey('us-east-1', 'example-s3-primary', 'sqs/');
	$backupLocation = new S3FileBucketAndKey('us-east-2', 'example-s3-backup', 'sqs/');
	$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
	$s3Locations->addAlternativeLocation($backupLocation);

	// Receive messages
	$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
	$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
	print 'Selected Queue: ' . $result->getSelectedQueue()->getQueueUrl() . PHP_EOL;
	print 'Message ID: ' . $result->getSqsMessageId() . PHP_EOL;
})();
