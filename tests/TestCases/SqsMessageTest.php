<?php

declare(strict_types=1);

use ssigwart\AwsHighAvailabilitySqs\SqsMessage;
use TestAuxFiles\UnitTestCase;

/**
 * SQS message test
 */
class SqsMessageTest extends UnitTestCase
{
	/**
	 * Test get message ID
	 */
	public function testGetMessageId(): void
	{
		$messageId = 'abcd-1234';
		$msg = new SqsMessage([
			'MessageId' => $messageId
		]);
		self::assertEquals($messageId, $msg->getMessageId());
	}

	/**
	 * Test get receipt handle
	 */
	public function testGetReceiptHandle(): void
	{
		$messageId = 'abcd-1234';
		$receiptHandle = 'receiptHandle1234';
		$msg = new SqsMessage([
			'MessageId' => $messageId,
			'ReceiptHandle' => $receiptHandle
		]);
		self::assertEquals($receiptHandle, $msg->getReceiptHandle());
	}

	/**
	 * Get message body
	 */
	public function testGetMessageBody(): void
	{
		$messageId = 'abcd-1234';
		$body = 'body-1234';
		$msg = new SqsMessage([
			'MessageId' => $messageId,
			'Body' => $body
		]);
		self::assertEquals($body, $msg->getMessage());
	}

	/**
	 * Get message attributes
	 */
	public function testGetMessageAttributes(): void
	{
		$messageId = 'abcd-1234';
		$msg = new SqsMessage([
			'MessageId' => $messageId,
			'Attributes' => [
				'SenderId' => '000000000000',
				'SentTimestamp' => '1704133701867',
				'ApproximateReceiveCount' => '1',
				'ApproximateFirstReceiveTimestamp' => '1704133701936'
			],
			'MessageAttributes' => [
				'string_1' => [
					'DataType' => 'String',
					'StringValue' => 'String 1'
				],
				'string_2' => [
					'DataType' => 'String',
					'StringValue' => 'String 2'
				],
				'num_1' => [
					'DataType' => 'Number',
					'StringValue' => '1234'
				],
				'num_as_string' => [
					'DataType' => 'String',
					'StringValue' => '1234'
				]
			]
		]);

		self::assertEquals(1704133701, $msg->getSentTimestamp());
		self::assertEquals(1704133701867, $msg->getSentTimestampInMs());
		self::assertEquals(1704133701, $msg->getApproximateFirstReceiveTimestamp());
		self::assertEquals(1704133701936, $msg->getApproximateFirstReceiveTimestampInMs());
		self::assertEquals(1, $msg->getApproximateReceiveCount());

		self::assertEquals('String 1', $msg->getStringMessageAttribute('string_1'));
		self::assertEquals('String 2', $msg->getStringMessageAttribute('string_2'));
		self::assertEquals(null, $msg->getStringMessageAttribute('string_3'));
		self::assertEquals(1234, $msg->getIntMsgAttribute('num_1'));
		self::assertEquals(null, $msg->getIntMsgAttribute('num_2'));
		try {
			$msg->getIntMsgAttribute('num_as_string');
			self::fail('Expected exception');
		} catch (TypeError $e) {
			self::assertEquals('Attribute "num_as_string" has an invalid data type of "String".', $e->getMessage());
		}
	}
}
