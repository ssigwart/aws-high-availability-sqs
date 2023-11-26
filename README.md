# AWS High Availability SQS

This library makes it easy to write SQS messages with high availability.
It includes the following features:
- Send any size message to SQS. If a message is too large, it will be stored in S3 and a reference to it will be stored in SQS.
- Send a message to one of multiple backup queues if the primary queue is unavailable.

## Setup
You should set up at least 2 SQS queues and 2 S3 buckets in separate regions.
The SQS queues are the messages you want to process.
The S3 buckets will be used for oversized queue messages that are sent.

### S3 Lifecycle Rules
When deleting SQS messages that were backed by an S3 file, the code will attempt to delete the S3 file as well.
However, in some cases, that S3 files may not be deleted.
For example, it it advised to catch and ignore `AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException` exceptions when calling `AwsHighAvailabilitySqsReceiver::deleteMessage`.
Also, any messages that expire from the queue or end up going to the dead letter queue will note have the file deleted.
Therefore, it is advised to set up lifecycle rules on the S3 bucket.
You can find instructions on setting up object expiration at [https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-expire-general-considerations.html](https://docs.aws.amazon.com/AmazonS3/latest/userguide/lifecycle-expire-general-considerations.html).
30 days is a safe bet.

## Usage

The APIs require you to pass in an `\Aws\Sdk` object.
In the examples below, `$awsSdk` is used for this object.

### Sending an SQS Message

1. Create a list of available SQS queues to send to.
	- This is an `SqsAvailableQueues` object, which includes a primary queue and optional alternative queues.
2. Create a list of S3 buckets to back large messages.
	- This is and `S3AvailableUploadFileBucketAndKeyLocations` object.
	- You can add anything that implements `S3FileBucketAndKeyProviderInterface` to the list of locations.
	- The simplest option is to use `S3FileBucketAndKey`, which implements this interface.
3. Set up `SqsMessageSendingMetadata` with metadata for the message to be sent.
4. Create an `AwsHighAvailabilitySqsSender` object and call `sendMessageWithS3LargeMessageBacking`.
	- You can use the returned object to get the queue used and the message ID

```php
// Set up SQS queues
$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/queue_in_us_east_1');
$backupQueue = new SqsAvailableQueue('us-east-2', 'https://sqs.us-east-2.amazonaws.com/123456789012/queue_in_us_east_2');
$availableQueues = new SqsAvailableQueues($primaryQueue);
$availableQueues->addAvailableQueue($backupQueue);

// Set up S3 locations
$primaryLocation = new S3FileBucketAndKey('us-east-1', 'phpunit-test-us-east-1', 'us-east-1/path/to/dir/');
$backupLocation = new S3FileBucketAndKey('us-east-2', 'phpunit-test-us-east-2', 'us-east-2/path/to/dir/');
$s3Locations = new S3AvailableUploadFileBucketAndKeyLocations($primaryLocation);
$s3Locations->addAlternativeLocation($backupLocation);

// Send messages
$sqsSender = new AwsHighAvailabilitySqsSender($awsSdk);
$result = $sqsSender->sendMessageWithS3LargeMessageBacking($availableQueues, $s3Locations, $queueMsgBody, null);
print 'Selected Queue: ' . $result->getSelectedQueue()->getQueueUrl() . PHP_EOL;
print 'Message ID: ' . $result->getSqsMessageId() . PHP_EOL;
```

### `SqsMessageSendingMetadata` Options
The `SqsMessageSendingMetadata` class allows you to customize the following when sending messages:
- SQS delivery delay.
- SQS message attributes.

### Receiving SQS Messages

1. Create an `SqsAvailableQueue` object with the queue you want to receive from.
2. Set up `SqsMessageReceivingMetadata` with metadata for receiving messages.
3. Create an `AwsHighAvailabilitySqsReceiver` object and call `receivedMessagesWithS3LargeMessageBacking`.
	- The returned object includes the SQS messages, which you can get using `getSqsMessages`.

```php
$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/queue_in_us_east_1');

// Set up metadata
$metadata = new SqsMessageReceivingMetadata();
$metadata->setMaxNumMessages(7);
$metadata->setVisibilityTimeout(300);
$metadata->setWaitTime(15);

$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
$sqsMessagesResult = $sqsReceiver->receivedMessagesWithS3LargeMessageBacking($primaryQueue, $metadata);
print 'Number of Messages: ' . $sqsMessagesResult->getNumMessages() . PHP_EOL;
foreach ($sqsMessagesResult->getSqsMessages() as $sqsMessage)
{
	print 'Messages ID: ' . $sqsMessage->getMessageId() . PHP_EOL;
	print 'Receipt Handle: ' . $sqsMessage->getReceiptHandle() . PHP_EOL;
	print 'Message Body: ' . $sqsMessage->getMessage() . PHP_EOL;
}
```

### `SqsMessageReceivingMetadata` Options
The `SqsMessageReceivingMetadata` class allows you to customize the following when receiving messages:
- Maximum number of messages to received (default: 10)
- SQS visibility timeout for messages received.
- SQS wait time when receiving messages.

### Deleting SQS Messages

1. Create an `SqsAvailableQueue` object with the queue you want to delete from.
2. Create an `AwsHighAvailabilitySqsReceiver` object and call `deleteMessage`.
	- For any failures, a `AwsHighAvailabilitySqsDeleteException` will be thrown.
	- However, if a `AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException` exception is thrown, the SQS message will have been successfully deleted, so it's advisable to ignore the exception besides possibly logging it.

```php
$primaryQueue = new SqsAvailableQueue('us-east-1', 'https://sqs.us-east-1.amazonaws.com/123456789012/queue_in_us_east_1');
$sqsReceiver = new AwsHighAvailabilitySqsReceiver($awsSdk);
try {
	$sqsReceiver->deleteMessage($primaryQueue, $sqsMessage);
} catch (AwsHighAvailabilitySqsDeleteS3FileDeletionFailureException $e) {
	error_log($e->getMessage());
} catch (AwsHighAvailabilitySqsDeleteException $e) {
	// Handle error. Possibly try to delete it again or add application code to prevent the message from being processed again later.
}
```
