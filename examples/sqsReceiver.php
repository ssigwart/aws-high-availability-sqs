<?php

/**
 * The following uses LocalStack (https://github.com/localstack/localstack). See
 * `sqsSender.php` for more details.
 */

namespace Examples;

use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException;
use ssigwart\AwsHighAvailabilitySqs\AwsHighAvailabilitySqsReceiver;
use ssigwart\AwsHighAvailabilitySqs\SqsAvailableQueue;
use ssigwart\AwsHighAvailabilitySqs\SqsMessageReceivingMetadata;

require(__DIR__ . '/../vendor/autoload.php');

(function() {
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

	$primaryQueue = new SqsAvailableQueue('us-east-1', 'http://sqs.us-east-1.localhost.localstack.cloud:4566/000000000000/example');

	// Set up metadata
	$metadata = new SqsMessageReceivingMetadata();
	$metadata->setMaxNumMessages(10);
	$metadata->setVisibilityTimeout(5);
	$metadata->setWaitTime(1);

	$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
	$sqsMessagesResult = $sqsReceiver->receivedMessagesWithS3LargeMessageBacking($primaryQueue, $metadata);
	print 'Number of Messages: ' . $sqsMessagesResult->getNumMessages() . PHP_EOL;
	foreach ($sqsMessagesResult->getSqsMessages() as $sqsMessage)
	{
		print 'Messages ID: ' . $sqsMessage->getMessageId() . PHP_EOL;
		print 'Receipt Handle: ' . $sqsMessage->getReceiptHandle() . PHP_EOL;
		print 'Message Length: ' . strlen($sqsMessage->getMessage()) . PHP_EOL;

		try {
			$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
		} catch (AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException $e) {
			error_log($e->getMessage());
		}
	}
})();
