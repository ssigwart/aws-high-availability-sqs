<?php

declare(strict_types=1);

namespace TestAuxFiles;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Unit test case */
class UnitTestCase extends TestCase
{
	/** @var string[] Expected AWS SDK call function names */
	private array $expectedSdkCallFunctionName = [];

	/** @var array[] Expected AWS SDK call parameters */
	private array $expectedSdkCallParams = [];

	/** @var MockObject[] Expected AWS SDK call return values */
	private array $expectedSdkCallReturns = [];

	/**
	 * Get mock AWS SDK
	 *
	 * @return \Aws\Sdk|MockObject Mock AWS SDK
	 */
	protected function getMockAwsSdk(): \Aws\Sdk|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\Sdk::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		return $mock;
	}

	/**
	 * Add expected createSqs call
	 *
	 * @param array $expectedParams Expected parameters
	 * @param \Aws\Sqs\SqsClient|MockObject $rtn Rtn
	 */
	protected function addExpectedCreateSqsCall(array $expectedParams, \Aws\Sqs\SqsClient|MockObject $rtn): void
	{
		$this->expectedSdkCallFunctionName[] = 'createSqs';
		$this->expectedSdkCallParams[] = $expectedParams;
		$this->expectedSdkCallReturns[] = $rtn;
	}

	/**
	 * Add expected createS3 call
	 *
	 * @param array $expectedParams Expected parameters
	 * @param \Aws\S3\S3Client|MockObject $rtn Rtn
	 */
	protected function addExpectedCreateS3Call(array $expectedParams, \Aws\S3\S3Client|MockObject $rtn): void
	{
		$this->expectedSdkCallFunctionName[] = 'createS3';
		$this->expectedSdkCallParams[] = $expectedParams;
		$this->expectedSdkCallReturns[] = $rtn;
	}

	/**
	 * Finalize mock AWS SDK
	 *
	 * @param \Aws\Sdk|MockObject $mockAwsSdk Mock AWS SDK
	 */
	protected function finalizeMockAwsSdk(\Aws\Sdk|MockObject $mockAwsSdk): void
	{
		$mockAwsSdk->expects(self::exactly(count($this->expectedSdkCallFunctionName)))->method('__call')->with(
			self::callback(function($arg) {
				$funcName = array_shift($this->expectedSdkCallFunctionName);
				self::assertEquals($funcName, $arg);
				return true;
			}),
			self::callback(function($args) {
				$expectedParams = array_shift($this->expectedSdkCallParams);
				self::assertEquals([$expectedParams], $args);
				return true;
			})
		)->willReturnCallback(function() {
			return array_shift($this->expectedSdkCallReturns);
		});
	}

	/** @var string SQS message ID */
	protected const SQS_MESSAGE_ID = '12345-67890-abcde';

	/**
	 * Get mock SQS client for send message
	 *
	 * @param array $expectedSendMessageArg Expected arguments for SendMessage call
	 * @param Throwable|null $sendMessageException Exception to throw for SendMessage call
	 *
	 * @return \Aws\Sqs\SqsClient|MockObject Mock SQS client
	 */
	protected function getMockSqsClientForSendMessage(array $expectedSendMessageArg, ?Throwable $sendMessageException): \Aws\Sqs\SqsClient|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\Sqs\SqsClient::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$sendMessageCall = $mock->expects(self::exactly(1))->method('__call')->with('sendMessage', $expectedSendMessageArg);
		if ($sendMessageException !== null)
			$sendMessageCall->willThrowException($sendMessageException);
		else
			$sendMessageCall->willReturn(['MessageId' => self::SQS_MESSAGE_ID]);

		return $mock;
	}

	/**
	 * Get mock SQS client for receive message
	 *
	 * @param array $expectedReceiveMessageArg Expected arguments for ReceiveMessage call
	 * @param array $returnedMessages Returned messages
	 *
	 * @return \Aws\Sqs\SqsClient|MockObject Mock SQS client
	 */
	protected function getMockSqsClientForReceiveMessage(array $expectedReceiveMessageArg, array $returnedMessages): \Aws\Sqs\SqsClient|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\Sqs\SqsClient::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$receiveMessageCall = $mock->expects(self::exactly(1))->method('__call')->with('receiveMessage', $expectedReceiveMessageArg);
		$receiveMessageCall->willReturn(['Messages' => $returnedMessages]);

		return $mock;
	}

	/**
	 * Get mock SQS client for delete message
	 *
	 * @param array $expectedDeleteMessageArg Expected arguments for DeleteMessage call
	 * @param Throwable|null $triggeredException Triggered exception
	 *
	 * @return \Aws\Sqs\SqsClient|MockObject Mock SQS client
	 */
	protected function getMockSqsClientForDeleteMessage(array $expectedDeleteMessageArg, ?Throwable $triggeredException): \Aws\Sqs\SqsClient|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\Sqs\SqsClient::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$deleteMessageCall = $mock->expects(self::exactly(1))->method('__call')->with('deleteMessage', $expectedDeleteMessageArg);
		if ($triggeredException !== null)
			$deleteMessageCall->willThrowException($triggeredException);

		return $mock;
	}

	/**
	 * Get mock S3 client for upload
	 *
	 * @param array $expectedPutObjectArg Expected arguments for PutObject call
	 * @param Throwable|null $putObjectException Exception to throw for PutObject call
	 *
	 * @return \Aws\S3\S3Client|MockObject Mock S3 client
	 */
	protected function getMockS3ClientForUpload(array $expectedPutObjectArg, ?Throwable $putObjectException): \Aws\S3\S3Client|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\S3\S3Client::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$putObjectCall = $mock->expects(self::exactly(1))->method('__call')->with('putObject', $expectedPutObjectArg);
		if ($putObjectException !== null)
			$putObjectCall->willThrowException($putObjectException);

		return $mock;
	}

	/**
	 * Get mock S3 client for download
	 *
	 * @param array $expectedGetObjectArg Expected arguments for GetObject call
	 * @param string|null $fileContents File contents
	 * @param Throwable|null $getObjectException Exception to throw for GetObject call
	 *
	 * @return \Aws\S3\S3Client|MockObject Mock S3 client
	 */
	protected function getMockS3ClientForDownload(array $expectedGetObjectArg, ?string $fileContents, ?Throwable $getObjectException): \Aws\S3\S3Client|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\S3\S3Client::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$getObjectCall = $mock->expects(self::exactly(1))->method('__call')->with('getObject', $expectedGetObjectArg);
		if ($fileContents !== null)
		{
			$getObjectCall->willReturn(new class($fileContents) {
				function __construct(protected $fileContents) {
				}
				function get() {
					return $this->fileContents;
				}
			});
		}
		if ($getObjectException !== null)
			$getObjectCall->willThrowException($getObjectException);

		return $mock;
	}

	/**
	 * Get mock S3 client for delete
	 *
	 * @param array $expectedDeleteObjectArg Expected arguments for DeleteObject call
	 * @param Throwable|null $deleteObjectException Exception to throw for DeleteObject call
	 *
	 * @return \Aws\S3\S3Client|MockObject Mock S3 client
	 */
	protected function getMockS3ClientForDelete(array $expectedDeleteObjectArg, ?Throwable $deleteObjectException): \Aws\S3\S3Client|MockObject
	{
		$mockBuilder = $this->getMockBuilder(\Aws\S3\S3Client::class);
		$mockBuilder->disableOriginalConstructor();
		$mockBuilder->disableAutoReturnValueGeneration();
		$mock = $mockBuilder->getMock();

		$deleteObjectCall = $mock->expects(self::exactly(1))->method('__call')->with('deleteObject', $expectedDeleteObjectArg);
		if ($deleteObjectException !== null)
			$deleteObjectCall->willThrowException($deleteObjectException);

		return $mock;
	}
}
